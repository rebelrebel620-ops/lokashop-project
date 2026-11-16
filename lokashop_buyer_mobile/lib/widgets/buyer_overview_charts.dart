import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import 'overview_charts.dart';
import 'auto_refresh.dart';

class BuyerOverviewCharts extends StatelessWidget {
  const BuyerOverviewCharts({super.key});
  @override
  Widget build(BuildContext context) => Consumer<AppState>(builder: (context, app, _) => !app.isLoggedIn ? const SizedBox.shrink() : _BuyerChartData(key: ValueKey(app.user?['id'])));
}
class _BuyerChartData extends StatefulWidget {
  const _BuyerChartData({super.key});
  @override
  State<_BuyerChartData> createState() => _BuyerChartDataState();
}
class _BuyerChartDataState extends State<_BuyerChartData> with AutoRefresh<_BuyerChartData> {
  Map? data;
  bool busy = false, failed = false;
  @override
  bool get autoRefreshEnabled => !busy;
  @override
  Future<void> refreshAutomatically() => load();
  @override
  void initState(){super.initState();load();}
  Future<void> load() async {
    if(busy)return;busy=true;
    try {final result=await context.read<AppState>().api.get('/overview/charts');if(mounted)setState(() {data=Map.from(result);failed=false;});}
    catch(_){if(mounted)setState(()=>failed=true);}
    finally{busy=false;}
  }
  @override
  Widget build(BuildContext context) => data != null ? OverviewChartsPanel(data:data!) : failed ? TextButton(onPressed:load,child:const Text('Retry overview charts')) : const Padding(padding:EdgeInsets.all(16),child:LinearProgressIndicator());
}
