import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';
import 'message_thread_screen.dart';

class MessagesScreen extends StatefulWidget {
  const MessagesScreen({super.key});
  @override
  State<MessagesScreen> createState() => _MessagesScreenState();
}

class _MessagesScreenState extends State<MessagesScreen> {
  bool loading = true;
  List contacts = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    try {
      final res = await context.read<AppState>().api.get('/messages/contacts');
      setState(() => contacts = List.from(res));
    } catch (_) {
      if (mounted) AppSnack.show(context, 'Could not load contacts.', error: true);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Messages')),
      body: loading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : contacts.isEmpty
              ? const EmptyState(icon: Icons.chat_bubble_outline_rounded, title: 'No contacts yet', subtitle: 'Sellers and support will show up here once you order.')
              : RefreshIndicator(
                  onRefresh: _load,
                  color: AppColors.primary,
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
                    itemCount: contacts.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, i) {
                      final c = contacts[i];
                      return InkWell(
                        borderRadius: BorderRadius.circular(14),
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(builder: (_) => MessageThreadScreen(userId: c['id'], userName: c['name'])),
                        ),
                        child: Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(14), border: Border.all(color: AppColors.line)),
                          child: Row(
                            children: [
                              CircleAvatar(
                                radius: 20,
                                backgroundColor: AppColors.primary.withOpacity(.12),
                                child: Text(
                                  ('${c['name']}').isNotEmpty ? c['name'][0].toUpperCase() : '?',
                                  style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w800),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text('${c['name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                                    Text('${c['role'] == 'admin' ? 'LokaShop support' : 'Seller'}', style: const TextStyle(color: AppColors.muted, fontSize: 12)),
                                  ],
                                ),
                              ),
                              const Icon(Icons.chevron_right_rounded, color: AppColors.muted),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}
