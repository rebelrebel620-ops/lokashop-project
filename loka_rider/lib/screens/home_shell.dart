import 'dart:async';
import 'package:flutter/material.dart';
import '../session.dart';
import 'assignments_screen.dart';
import 'scan_screen.dart';
import 'features.dart';

class HomeShell extends StatefulWidget {
  final Session session;
  const HomeShell({super.key, required this.session});
  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> with WidgetsBindingObserver {
  int index = 0;
  Timer? timer;
  bool foreground = true;
  bool refreshing = false;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    timer = Timer.periodic(const Duration(seconds: 45), (_) => refresh());
  }

  Future<void> refresh() async {
    if (!foreground || refreshing) return;
    refreshing = true;
    try {
      widget.session.updateRider((await widget.session.api.me()).rider);
    } catch (_) {
    } finally {
      refreshing = false;
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    foreground = state == AppLifecycleState.resumed;
    if (foreground) refresh();
  }

  @override
  void dispose() {
    timer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  Widget page() => switch (index) {
    0 => OverviewScreen(
      session: widget.session,
      onJobs: () => setState(() => index = 1),
      onScan: () => setState(() => index = 2),
    ),
    1 => AssignmentsScreen(
      session: widget.session,
      onScan: () => setState(() => index = 2),
    ),
    2 => ScanScreen(session: widget.session),
    3 => CodScreen(session: widget.session),
    _ => AccountScreen(session: widget.session),
  };
  static const labels = ['Overview', 'Jobs', 'Scan', 'COD', 'Account'];
  static const icons = [
    Icons.dashboard_outlined,
    Icons.local_shipping_outlined,
    Icons.qr_code_scanner,
    Icons.account_balance_wallet_outlined,
    Icons.person_outline,
  ];
  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, box) {
      final wide = box.maxWidth >= 850;
      final body = KeyedSubtree(key: ValueKey(index), child: page());
      return Scaffold(
        body: SafeArea(
          child: Row(
            children: [
              if (wide)
                NavigationRail(
                  selectedIndex: index,
                  onDestinationSelected: (v) => setState(() => index = v),
                  labelType: NavigationRailLabelType.all,
                  destinations: List.generate(
                    5,
                    (i) => NavigationRailDestination(
                      icon: Icon(icons[i]),
                      label: Text(labels[i]),
                    ),
                  ),
                ),
              if (wide) const VerticalDivider(width: 1),
              Expanded(
                child: Align(
                  alignment: Alignment.topCenter,
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 1100),
                    child: body,
                  ),
                ),
              ),
            ],
          ),
        ),
        bottomNavigationBar: wide
            ? null
            : NavigationBar(
                selectedIndex: index,
                onDestinationSelected: (v) => setState(() => index = v),
                destinations: List.generate(
                  5,
                  (i) => NavigationDestination(
                    icon: Icon(icons[i]),
                    label: labels[i],
                  ),
                ),
              ),
      );
    },
  );
}
