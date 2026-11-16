import 'package:flutter/material.dart';
import '../session.dart';
import '../widgets/auto_refresh.dart';
import '../widgets/overview_charts.dart';
import '../widgets/logistics_background.dart';
class StatsScreen extends StatefulWidget {
  final Session session;
  const StatsScreen({super.key, required this.session});
  @override
  State<StatsScreen> createState()=>_StatsScreenState();
}
class _StatsScreenState extends State<StatsScreen> with AutoRefresh<StatsScreen> {
  Map? charts;
  bool busy=false;
  String? error;
  @override
  bool get autoRefreshEnabled=>!busy;
  @override
  Future<void> refreshAutomatically()=>load();
  @override
  void initState(){super.initState();load();}
  Future<void> load() async {
    if(busy)return;busy=true;
    try {final data=await widget.session.api.get(widget.session.rider?.isCenter==true?'/api/center/overview':'/api/rider/overview');if(mounted)setState((){charts=data['charts'] is Map?Map.from(data['charts']):null;error=charts==null?'Stats are unavailable. Please try again later.':null;});}
    catch(e){if(mounted)setState(()=>error='$e');}
    finally{busy=false;}
  }
  @override
  Widget build(BuildContext context)=>Scaffold(appBar:AppBar(title:const Text('Stats')),body:LogisticsBackground(asset:LogisticsBackground.forUser(widget.session.rider),child:RefreshIndicator(onRefresh:load,child:ListView(physics:const AlwaysScrollableScrollPhysics(),padding:const EdgeInsets.all(18),children:[
    if(charts!=null)OverviewChartsPanel(data:charts!),
    if(error!=null)Card(child:Padding(padding:const EdgeInsets.all(18),child:Column(children:[Text(error!),TextButton(onPressed:load,child:const Text('Retry'))]))),
    if(charts==null&&error==null)const Padding(padding:EdgeInsets.all(30),child:Center(child:CircularProgressIndicator())),
  ]))));
}
