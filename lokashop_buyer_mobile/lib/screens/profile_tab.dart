import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';
import '../services/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';
import 'login_screen.dart';
import 'notifications_screen.dart';
import 'messages_screen.dart';
import 'complaints_screen.dart';
import 'addresses_screen.dart';

class ProfileTab extends StatefulWidget {
  const ProfileTab({super.key});
  @override
  State<ProfileTab> createState() => _ProfileTabState();
}

class _ProfileTabState extends State<ProfileTab> {
  Map? me;
  bool loading = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    final app = context.read<AppState>();
    if (!app.isLoggedIn) {
      setState(() => loading = false);
      return;
    }
    try {
      final res = await app.api.get('/me');
      setState(() => me = Map.from(res));
    } catch (_) {
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _editProfile() async {
    final nameCtrl = TextEditingController(text: me?['name']);
    final phoneCtrl = TextEditingController(text: me?['phone']);
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom),
        child: Container(
          decoration: const BoxDecoration(
              color: AppColors.bg,
              borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
          padding: const EdgeInsets.fromLTRB(20, 18, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Edit profile',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
              const SizedBox(height: 16),
              TextField(
                  controller: nameCtrl,
                  decoration: const InputDecoration(labelText: 'Full name')),
              const SizedBox(height: 10),
              TextField(
                  controller: phoneCtrl,
                  decoration: const InputDecoration(labelText: 'Phone')),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () async {
                    try {
                      await context.read<AppState>().api.post('/profile', {
                        'name': nameCtrl.text.trim(),
                        'phone': phoneCtrl.text.trim()
                      });
                      if (ctx.mounted) Navigator.of(ctx).pop(true);
                    } on ApiException catch (e) {
                      if (ctx.mounted)
                        AppSnack.show(ctx, e.message, error: true);
                    }
                  },
                  child: const Text('Save'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
    if (saved == true) _load();
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();

    if (!app.isLoggedIn) {
      return Scaffold(
        appBar: AppBar(title: const Text('Profile')),
        body: EmptyState(
          icon: Icons.person_outline_rounded,
          title: 'You\'re not logged in',
          subtitle: 'Log in to manage your profile and orders.',
          action: ElevatedButton(
            onPressed: () => Navigator.of(context)
                .push(MaterialPageRoute(builder: (_) => const LoginScreen())),
            child: const Padding(
                padding: EdgeInsets.symmetric(horizontal: 24),
                child: Text('Log in')),
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: loading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.primary))
          : ListView(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
              children: [
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                        colors: [AppColors.primary, AppColors.primaryDark],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Row(
                    children: [
                      CircleAvatar(
                        radius: 28,
                        backgroundColor: Colors.white.withOpacity(.2),
                        child: Text(
                          (me?['name'] ?? '?').toString().isNotEmpty
                              ? me!['name'][0].toUpperCase()
                              : '?',
                          style: const TextStyle(
                              color: Colors.white,
                              fontSize: 22,
                              fontWeight: FontWeight.w800),
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('${me?['name'] ?? ''}',
                                style: const TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w800,
                                    fontSize: 16)),
                            const SizedBox(height: 2),
                            Text('${me?['email'] ?? ''}',
                                style: TextStyle(
                                    color: Colors.white.withOpacity(.8),
                                    fontSize: 12.5)),
                            if (me?['phone'] != null)
                              Text('${me?['phone']}',
                                  style: TextStyle(
                                      color: Colors.white.withOpacity(.8),
                                      fontSize: 12.5)),
                          ],
                        ),
                      ),
                      IconButton(
                          onPressed: _editProfile,
                          icon: const Icon(Icons.edit_outlined,
                              color: Colors.white)),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
                _tile(
                    context,
                    Icons.location_on_outlined,
                    'Delivery addresses',
                    () => Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => const AddressesScreen()))),
                _tile(
                    context,
                    Icons.notifications_none_rounded,
                    'Notifications',
                    () => Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => const NotificationsScreen()))),
                _tile(
                    context,
                    Icons.chat_bubble_outline_rounded,
                    'Messages',
                    () => Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => const MessagesScreen()))),
                _tile(
                    context,
                    Icons.report_outlined,
                    'My complaints',
                    () => Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => const ComplaintsScreen()))),
                const SizedBox(height: 12),
                _tile(context, Icons.logout_rounded, 'Log out', () async {
                  await context.read<AppState>().logout();
                  if (context.mounted) {
                    Navigator.of(context).pushAndRemoveUntil(
                        MaterialPageRoute(builder: (_) => const LoginScreen()),
                        (r) => false);
                  }
                }, danger: true),
              ],
            ),
    );
  }

  Widget _tile(
      BuildContext context, IconData icon, String label, VoidCallback onTap,
      {bool danger = false}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AppColors.line)),
          child: Row(
            children: [
              Icon(icon, color: danger ? AppColors.danger : AppColors.primary),
              const SizedBox(width: 12),
              Expanded(
                  child: Text(label,
                      style: TextStyle(
                          fontWeight: FontWeight.w700,
                          color: danger ? AppColors.danger : AppColors.ink))),
              Icon(Icons.chevron_right_rounded,
                  color: AppColors.muted.withOpacity(.6)),
            ],
          ),
        ),
      ),
    );
  }
}
