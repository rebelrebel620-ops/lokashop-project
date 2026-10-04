import 'package:flutter/material.dart';

import '../session.dart';
import 'assignments_screen.dart';
import 'earnings_screen.dart';
import 'profile_screen.dart';
import 'scan_screen.dart';

class HomeShell extends StatefulWidget {
  final Session session;
  const HomeShell({super.key, required this.session});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  void _go(int i) => setState(() => _index = i);

  // Only the selected tab is built, so the camera is released when you leave
  // the Scan tab and lists reload when you come back to them.
  Widget _page() {
    switch (_index) {
      case 0:
        return AssignmentsScreen(session: widget.session, onScan: () => _go(1));
      case 1:
        return ScanScreen(session: widget.session);
      case 2:
        return EarningsScreen(session: widget.session);
      default:
        return ProfileScreen(session: widget.session);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: _page(),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: _go,
        destinations: const [
          NavigationDestination(icon: Icon(Icons.local_shipping_outlined), selectedIcon: Icon(Icons.local_shipping), label: 'Jobs'),
          NavigationDestination(icon: Icon(Icons.qr_code_scanner), label: 'Scan'),
          NavigationDestination(icon: Icon(Icons.payments_outlined), selectedIcon: Icon(Icons.payments), label: 'Earnings'),
          NavigationDestination(icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person), label: 'Profile'),
        ],
      ),
    );
  }
}
