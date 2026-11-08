import 'package:flutter/material.dart';
import '../../session.dart';
import '../../widgets/wallet_screen.dart';
import '../profile_screen.dart';
import '../features.dart';
import 'center_page.dart';

class CenterShell extends StatefulWidget {
  final Session session;
  const CenterShell({super.key, required this.session});
  @override
  State<CenterShell> createState() => _CenterShellState();
}

class _CenterShellState extends State<CenterShell> {
  int index = 0;
  final modules = const ['overview', 'pickups', 'transfers', 'deliveries'];
  void open(Widget child) =>
      Navigator.push(context, MaterialPageRoute(builder: (_) => child));
  Widget more() => Scaffold(
    appBar: AppBar(title: const Text('Sorting center tools')),
    body: ListView(
      children: [
        for (final entry in const {
          'areas': 'Service areas',
          'couriers': 'Riders and drivers',
          'applications': 'Courier applications',
          'appeals': 'Courier appeals',
          'rates': 'Shipping rates',
          'cod': 'COD remittances',
          'returns': 'Returns',
          'tracking': 'Parcel tracking',
          'reports': 'Operations reports',
          'finance': 'Financial report',
          'notifications': 'Notifications',
          'address': 'Registered address',
        }.entries)
          ListTile(
            title: Text(entry.value),
            leading: const Icon(Icons.chevron_right),
            onTap: () =>
                open(CenterPage(session: widget.session, module: entry.key)),
          ),
        ListTile(
          leading: const Icon(Icons.account_balance_wallet_outlined),
          title: const Text('LokaShop Wallet'),
          onTap: () => open(
            WalletScreen(
              fetch: (query) =>
                  widget.session.api.get('/api/mobile/wallet', query),
              transfer: (fields) => widget.session.api.post(
                '/api/mobile/wallet/transfer',
                fields,
              ),
            ),
          ),
        ),
        ListTile(
          leading: const Icon(Icons.chat_bubble_outline),
          title: const Text('Messages'),
          onTap: () => open(
            MessagesScreen(session: widget.session, basePath: '/api/center'),
          ),
        ),
        ListTile(
          leading: const Icon(Icons.person_outline),
          title: const Text('Profile and security'),
          onTap: () => open(ProfileScreen(session: widget.session)),
        ),
        ListTile(
          leading: const Icon(Icons.logout),
          title: const Text('Log out'),
          onTap: () async {
            final yes = await showDialog<bool>(
              context: context,
              builder: (c) => AlertDialog(
                title: const Text('Do you want to log out?'),
                actions: [
                  TextButton(
                    onPressed: () => Navigator.pop(c, false),
                    child: const Text('No'),
                  ),
                  FilledButton(
                    onPressed: () => Navigator.pop(c, true),
                    child: const Text('Yes, log out'),
                  ),
                ],
              ),
            );
            if (yes == true) await widget.session.logout();
          },
        ),
      ],
    ),
  );
  @override
  Widget build(BuildContext context) => Scaffold(
    body: index == 4
        ? more()
        : CenterPage(
            key: ValueKey(modules[index]),
            session: widget.session,
            module: modules[index],
          ),
    bottomNavigationBar: NavigationBar(
      selectedIndex: index,
      onDestinationSelected: (v) => setState(() => index = v),
      destinations: const [
        NavigationDestination(
          icon: Icon(Icons.dashboard_outlined),
          label: 'Overview',
        ),
        NavigationDestination(
          icon: Icon(Icons.inventory_2_outlined),
          label: 'Pickups',
        ),
        NavigationDestination(
          icon: Icon(Icons.local_shipping_outlined),
          label: 'Transfers',
        ),
        NavigationDestination(
          icon: Icon(Icons.delivery_dining_outlined),
          label: 'Deliveries',
        ),
        NavigationDestination(
          icon: Icon(Icons.grid_view_outlined),
          label: 'More',
        ),
      ],
    ),
  );
}
