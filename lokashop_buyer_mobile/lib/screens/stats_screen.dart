import 'package:flutter/material.dart';
import '../widgets/buyer_overview_charts.dart';
import '../widgets/design.dart';
class StatsScreen extends StatelessWidget {
  const StatsScreen({super.key});
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Stats')),
    body: const PageBody(child: SingleChildScrollView(padding: EdgeInsets.all(18), child: BuyerOverviewCharts())),
  );
}
