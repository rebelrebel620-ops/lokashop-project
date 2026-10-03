import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';
import 'login_screen.dart';
import 'order_detail_screen.dart';

class OrdersTab extends StatefulWidget {
  const OrdersTab({super.key});
  @override
  State<OrdersTab> createState() => _OrdersTabState();
}

class _OrdersTabState extends State<OrdersTab> {
  bool loading = true;
  List orders = [];

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
    setState(() => loading = true);
    try {
      final res = await app.api.get('/orders');
      setState(() => orders = List.from(res));
    } catch (_) {
      if (mounted) AppSnack.show(context, 'Could not load orders.', error: true);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();

    if (!app.isLoggedIn) {
      return Scaffold(
        appBar: AppBar(title: const Text('My orders')),
        body: EmptyState(
          icon: Icons.receipt_long_outlined,
          title: 'Log in to see your orders',
          subtitle: 'Track deliveries and order history here.',
          action: ElevatedButton(
            onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LoginScreen())),
            child: const Padding(padding: EdgeInsets.symmetric(horizontal: 24), child: Text('Log in')),
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('My orders')),
      body: loading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : orders.isEmpty
              ? const EmptyState(icon: Icons.receipt_long_outlined, title: 'No orders yet', subtitle: 'Your orders will show up here once you check out.')
              : RefreshIndicator(
                  onRefresh: _load,
                  color: AppColors.primary,
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
                    itemCount: orders.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (context, i) {
                      final o = orders[i];
                      final status = statusInfo(o['status']);
                      return InkWell(
                        borderRadius: BorderRadius.circular(16),
                        onTap: () async {
                          await Navigator.of(context).push(MaterialPageRoute(builder: (_) => OrderDetailScreen(orderId: o['id'])));
                          _load();
                        },
                        child: Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(16), border: Border.all(color: AppColors.line)),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text('Order #${o['id']}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                    decoration: BoxDecoration(color: status.color.withOpacity(.12), borderRadius: BorderRadius.circular(20)),
                                    child: Text(status.label, style: TextStyle(color: status.color, fontWeight: FontWeight.w700, fontSize: 11.5)),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 6),
                              Text('${o['seller_name'] ?? 'Seller'}', style: const TextStyle(color: AppColors.muted, fontSize: 13)),
                              const SizedBox(height: 4),
                              Text('Tracking: ${o['tracking_code']}', style: const TextStyle(color: AppColors.muted, fontSize: 12)),
                              const Divider(height: 20),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(money(o['total']), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: AppColors.primary)),
                                  const Icon(Icons.chevron_right_rounded, color: AppColors.muted),
                                ],
                              ),
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
