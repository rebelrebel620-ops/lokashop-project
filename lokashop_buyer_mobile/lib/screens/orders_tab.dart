import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import '../widgets/common.dart';
import '../widgets/design.dart';
import '../theme.dart';
import 'order_detail_screen.dart';
import 'login_screen.dart';

class OrdersTab extends StatefulWidget {
  const OrdersTab({super.key});
  @override
  State<OrdersTab> createState() => _OrdersTabState();
}

class _OrdersTabState extends State<OrdersTab> {
  List orders = [];
  bool loading = true;
  String filter = 'All';
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    if (!context.read<AppState>().isLoggedIn) {
      setState(() => loading = false);
      return;
    }
    try {
      final data = await context.read<AppState>().api.get('/orders');
      if (mounted) setState(() => orders = List.from(data));
    } catch (e) {
      if (mounted) setState(() => error = '$e');
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final rows = orders
        .where(
          (o) =>
              filter == 'All' ||
              (filter == 'On the way' &&
                  ![
                    'COMPLETED',
                    'CANCELLED',
                    'DELIVERED',
                    'RETURN_RECEIVED',
                  ].contains(o['status'])) ||
              (filter == 'Delivered' && o['status'] == 'DELIVERED') ||
              (filter == 'Completed' && o['status'] == 'COMPLETED'),
        )
        .toList();
    return Scaffold(
      appBar: AppBar(title: const Text('Your local deliveries')),
      body: PageBody(
        child: !app.isLoggedIn
            ? EmptyState(
                icon: Icons.receipt_long_outlined,
                title: 'Your orders live here',
                subtitle: 'Sign in to track your deliveries.',
                action: FilledButton(
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const LoginScreen()),
                  ),
                  child: const Text('Sign in'),
                ),
              )
            : loading
            ? const Center(child: CircularProgressIndicator())
            : RefreshIndicator(
                onRefresh: load,
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(22),
                  children: [
                    const Eyebrow('FROM LOCAL SELLERS TO YOUR DOOR'),
                    const SizedBox(height: 16),
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: Row(
                        children:
                            ['All', 'On the way', 'Delivered', 'Completed']
                                .map(
                                  (f) => Padding(
                                    padding: const EdgeInsets.only(right: 8),
                                    child: ChoiceChip(
                                      label: Text(f),
                                      selected: filter == f,
                                      onSelected: (_) =>
                                          setState(() => filter = f),
                                    ),
                                  ),
                                )
                                .toList(),
                      ),
                    ),
                    const SizedBox(height: 20),
                    if (error != null) Text(error!),
                    if (rows.isEmpty)
                      const EmptyState(
                        icon: Icons.local_shipping_outlined,
                        title: 'No orders in this view',
                        subtitle: 'Your shopping journey will appear here.',
                      ),
                    ...rows.map(
                      (o) => Padding(
                        padding: const EdgeInsets.only(bottom: 14),
                        child: InkWell(
                          onTap: () async {
                            await Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) =>
                                    OrderDetailScreen(orderId: o['id']),
                              ),
                            );
                            load();
                          },
                          borderRadius: BorderRadius.circular(22),
                          child: SheetCard(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    const Icon(
                                      Icons.inventory_2_outlined,
                                      color: AppColors.primary,
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Text(
                                        '${o['seller_name'] ?? 'Local seller'}',
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w800,
                                        ),
                                      ),
                                    ),
                                    Text(
                                      money(o['total']),
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w900,
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 18),
                                StatusBadge(status: '${o['status']}'),
                                const SizedBox(height: 12),
                                Text(
                                  'Order #${o['id']} · ${o['tracking_code']}',
                                  style: const TextStyle(
                                    fontSize: 11,
                                    color: AppColors.muted,
                                  ),
                                ),
                                const SizedBox(height: 12),
                                const Row(
                                  children: [
                                    Expanded(
                                      child: Text(
                                        'Track delivery & view details',
                                        style: TextStyle(
                                          fontWeight: FontWeight.w600,
                                          fontSize: 12,
                                        ),
                                      ),
                                    ),
                                    Icon(Icons.arrow_forward, size: 17),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
      ),
    );
  }
}
