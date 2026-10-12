import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';
import '../services/app_state.dart';
import 'login_screen.dart';

class VerifyEmailScreen extends StatefulWidget {
  final String token, email;
  final int initialCooldown;
  const VerifyEmailScreen({super.key, required this.token, required this.email, this.initialCooldown=0});
  @override
  State<VerifyEmailScreen> createState()=>_VerifyEmailScreenState();
}
class _VerifyEmailScreenState extends State<VerifyEmailScreen> {
  final _code=TextEditingController();
  Timer? _timer;
  int _seconds=0;
  bool _busy=false;
  String? _error, _message;
  @override
  void initState(){super.initState();_cooldown(widget.initialCooldown);}
  void _cooldown(int seconds){
    _timer?.cancel();_seconds=seconds;
    if(seconds>0)_timer=Timer.periodic(const Duration(seconds:1),(timer){
      if(!mounted){timer.cancel();return;}
      setState(()=>_seconds--);if(_seconds<=0)timer.cancel();
    });
  }
  @override
  void dispose(){_timer?.cancel();_code.dispose();super.dispose();}
  Future<void> _action(bool resend) async {
    if(_busy || (resend && _seconds>0))return;
    if(!resend && !RegExp(r'^\d{6}$').hasMatch(_code.text)){
      setState(()=>_error='Enter the six-digit code.');return;
    }
    setState((){_busy=true;_error=null;_message=null;});
    try{
      final result=await context.read<AppState>().api.post(
        resend ? '/registration/resend' : '/registration/verify',
        {'verification_token':widget.token,if(!resend)'otp':_code.text});
      if(!mounted)return;
      if(resend){setState((){_message='${result['message']}';_code.clear();_cooldown(60);});}
      else{
        await showDialog<void>(context:context,builder:(context)=>AlertDialog(
          title:const Text('Email verified'),content:const Text('Your buyer account is ready. You can now log in.'),
          actions:[TextButton(onPressed:()=>Navigator.pop(context),child:const Text('Continue'))]));
        if(!mounted)return;
        Navigator.of(context).pushAndRemoveUntil(MaterialPageRoute(builder:(_)=>const LoginScreen()),(route)=>false);
      }
    } on ApiException catch(e){
      if(mounted)setState((){_error=e.message;if(resend && e.data?['retry_after'] != null)_cooldown(60);});
    } catch(_){if(mounted)setState(()=>_error='Could not reach the server. Try again.');}
    finally{if(mounted)setState(()=>_busy=false);}
  }
  @override
  Widget build(BuildContext context)=>Scaffold(
    appBar:AppBar(title:const Text('Verify email')),
    body:SingleChildScrollView(padding:const EdgeInsets.all(24),child:Column(
      crossAxisAlignment:CrossAxisAlignment.stretch,children:[
        Text('Enter the code sent to ${widget.email}. It expires in five minutes.'),
        const SizedBox(height:12),
        const Text('If you leave this screen, log in with your email and password to continue verification.'),
        const SizedBox(height:24),
        TextField(controller:_code,enabled:!_busy,keyboardType:TextInputType.number,maxLength:6,
          inputFormatters:[FilteringTextInputFormatter.digitsOnly],
          decoration:InputDecoration(labelText:'Verification code',errorText:_error)),
        if(_message!=null)Text(_message!),
        ElevatedButton(onPressed:_busy?null:()=>_action(false),child:Text(_busy?'Please wait…':'Verify email')),
        TextButton(onPressed:_busy||_seconds>0?null:()=>_action(true),
          child:Text(_seconds>0?'Resend in ${_seconds}s':'Resend code')),
      ])));
}
