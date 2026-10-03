import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';

class ComplaintsScreen extends StatefulWidget {
  const ComplaintsScreen({super.key});
  @override
  State<ComplaintsScreen> createState() => _ComplaintsScreenState();
}

class _ComplaintsScreenState extends State<ComplaintsScreen> {
  bool loading = true;
  List complaints = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    try {
      final res = await context.read<AppState>().api.get('/complaints');
      setState(() => complaints = List.from(res));
    } catch (_) {
      if (mounted) AppSnack.show(context, 'Could not load complaints.', error: true);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Color _statusColor(String s) {
    switch (s) {
      case 'resolved':
        return AppColors.success;
      case 'open':
        return AppColors.accent;
      default:
        return AppColors.muted;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My complaints')),
      body: loading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : complaints.isEmpty
              ? const EmptyState(
                  icon: Icons.report_outlined,
                  title: 'No complaints filed',
                  subtitle: 'You can report an issue from any completed order.',
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  color: AppColors.primary,
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
                    itemCount: complaints.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, i) {
                      final c = complaints[i];
                      final color = _statusColor(c['status']);
                      return Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(14), border: Border.all(color: AppColors.line)),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text('Order #${c['order_id']}', style: const TextStyle(fontWeight: FontWeight.w800)),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                  decoration: BoxDecoration(color: color.withOpacity(.12), borderRadius: BorderRadius.circular(20)),
                                  child: Text('${c['status']}', style: TextStyle(color: color, fontWeight: FontWeight.w700, fontSize: 11.5)),
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Text('${c['description']}', style: const TextStyle(color: AppColors.muted, height: 1.4)),
                            if ((c['resolution'] ?? '').toString().isNotEmpty) ...[
                              const Divider(height: 20),
                              const Text('Resolution', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5)),
                              const SizedBox(height: 4),
                              Text('${c['resolution']}', style: const TextStyle(color: AppColors.muted, height: 1.4)),
                            ],
                          ],
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}
