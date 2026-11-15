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
  String module = 'overview';
  static const forest = Color(0xFF123E32);
  static const lime = Color(0xFFC4EF9A);
  static const red = Color(0xFFC93636);

  void selectModule(BuildContext drawerContext, String value) {
    Navigator.pop(drawerContext);
    setState(() => module = value);
  }

  void openPage(BuildContext drawerContext, Widget page) {
    Navigator.pop(drawerContext);
    Navigator.push(context, MaterialPageRoute(builder: (_) => page));
  }

  Future<void> logout(BuildContext drawerContext) async {
    Navigator.pop(drawerContext);
    final yes = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Do you want to log out?'),
        actions: [
          OutlinedButton(
            style: OutlinedButton.styleFrom(
              backgroundColor: Colors.white,
              foregroundColor: forest,
            ),
            onPressed: () => Navigator.pop(c, false),
            child: const Text('No'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: red,
              foregroundColor: Colors.white,
            ),
            onPressed: () => Navigator.pop(c, true),
            child: const Text('Yes, log out'),
          ),
        ],
      ),
    );
    if (yes == true) await widget.session.logout();
  }

  Widget menuItem(
    String label,
    IconData icon,
    VoidCallback onTap, {
    bool selected = false,
  }) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
    child: ListTile(
      dense: true,
      selected: selected,
      selectedTileColor: lime,
      selectedColor: forest,
      iconColor: const Color(0xFFCAE0D4),
      textColor: const Color(0xFFE6F1EA),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      leading: Icon(icon, size: 20),
      title: Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
      onTap: onTap,
    ),
  );

  Widget group(String title, IconData icon, List<Widget> children,
      {bool expanded = false}) => ExpansionTile(
    key: PageStorageKey(title),
    initiallyExpanded: expanded,
    iconColor: const Color(0xFFCAE0D4),
    collapsedIconColor: const Color(0xFFCAE0D4),
    textColor: Colors.white,
    collapsedTextColor: Colors.white,
    shape: const Border(),
    collapsedShape: const Border(),
    leading: Icon(icon, size: 20),
    title: Text(title, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
    childrenPadding: const EdgeInsets.only(left: 19, bottom: 10),
    children: children,
  );

  Widget navigation() => Builder(
    builder: (drawerContext) => Drawer(
      backgroundColor: forest,
      child: SafeArea(
        child: ListView(
          padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 8),
          children: [
            const Padding(
              padding: EdgeInsets.fromLTRB(18, 12, 18, 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('LokaShop', style: TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold)),
                  Text('Sorting Center', style: TextStyle(color: Color(0xFFCAE0D4))),
                ],
              ),
            ),
            menuItem('Overview', Icons.dashboard_outlined,
                () => selectModule(drawerContext, 'overview'),
                selected: module == 'overview'),
            group('Finance & Reports', Icons.bar_chart_outlined, [
              menuItem('Wallet', Icons.account_balance_wallet_outlined,
                  () => openPage(drawerContext, WalletScreen(
                    centerDesign: true,
                    fetch: (query) => widget.session.api.get('/api/mobile/wallet', query),
                    transfer: (fields) => widget.session.api.post('/api/mobile/wallet/transfer', fields),
                  ))),
              menuItem('Financial report', Icons.assessment_outlined,
                  () => selectModule(drawerContext, 'finance'),
                  selected: module == 'finance'),
              menuItem('COD Collections & Remittances', Icons.payments_outlined,
                  () => selectModule(drawerContext, 'cod'),
                  selected: module == 'cod'),
              menuItem('Filtered report', Icons.filter_alt_outlined,
                  () => selectModule(drawerContext, 'reports'),
                  selected: module == 'reports'),
            ], expanded: ['finance', 'cod', 'reports'].contains(module)),
            group('Support', Icons.support_agent_outlined, [
              menuItem('Messages', Icons.chat_bubble_outline,
                  () => openPage(drawerContext, MessagesScreen(
                    session: widget.session, basePath: '/api/center',
                  ))),
            ]),
            group('Account', Icons.person_outline, [
              menuItem('Account', Icons.person_outline,
                  () => openPage(drawerContext, ProfileScreen(session: widget.session))),
            ]),
            const Divider(color: Color(0x33FFFFFF), indent: 12, endIndent: 12),
            Padding(
              padding: const EdgeInsets.all(12),
              child: FilledButton.icon(
                style: FilledButton.styleFrom(backgroundColor: red, foregroundColor: Colors.white),
                onPressed: () => logout(drawerContext),
                icon: const Icon(Icons.logout),
                label: const Text('Log out'),
              ),
            ),
          ],
        ),
      ),
    ),
  );

  @override
  Widget build(BuildContext context) => CenterPage(
    key: ValueKey(module),
    session: widget.session,
    module: module,
    navigationDrawer: navigation(),
  );
}
