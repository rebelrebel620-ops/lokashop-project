import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import '../screens/notifications_screen.dart';
import 'auto_refresh.dart';
class NotificationBell extends StatefulWidget {
  const NotificationBell({super.key});
  @override
  State<NotificationBell> createState() => _NotificationBellState();
}
class _NotificationBellState extends State<NotificationBell> with AutoRefresh<NotificationBell> {
  int unread=0;
  bool busy=false;
  @override
  bool get autoRefreshEnabled => !busy && context.read<AppState>().isLoggedIn;
  @override
  Future<void> refreshAutomatically()=>load();
  @override
  void initState(){super.initState();load();}
  Future<void> load() async {
    if(busy||!context.read<AppState>().isLoggedIn)return;busy=true;
    try{final data=await context.read<AppState>().api.get('/notifications/count');if(mounted)setState(()=>unread=int.tryParse('${data['unread']}')??0);}catch(_){}finally{busy=false;}
  }
  @override
  Widget build(BuildContext context)=>IconButton(tooltip:'Notifications, $unread unread',icon:Badge(isLabelVisible:unread>0,label:Text(unread>99?'99+':'$unread'),child:const Icon(Icons.notifications_none)),onPressed:() async {await Navigator.push(context,MaterialPageRoute(builder:(_)=>const NotificationsScreen()));if(mounted)await load();});
}
