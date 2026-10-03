import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';
import '../services/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';
import 'complaint_form_screen.dart';

class OrderDetailScreen extends StatefulWidget {
  final int orderId;
  const OrderDetailScreen({super.key, required this.orderId});
  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  bool loading = true;
  Map? order;
  List items = [];
  Map? address;
  Map? payment;
  Map? rating;
  bool acting = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    try {
      final res = await context.read<AppState>().api.get('/orders/${widget.orderId}');
      setState(() {
        order = Map.from(res['order']);
        items = List.from(res['items'] ?? []);
        address = res['address'] != null ? Map.from(res['address']) : null;
        payment = res['payment'] != null ? Map.from(res['payment']) : null;
        rating = res['rating'] != null ? Map.from(res['rating']) : null;
      });
    } catch (_) {
      if (mounted) AppSnack.show(context, 'Could not load order.', error: true);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _confirmReceived() async {
    setState(() => acting = true);
    try {
      await context.read<AppState>().api.post('/orders/${widget.orderId}/confirm');
      await _load();
      if (mounted) AppSnack.show(context, 'Order marked as completed. Thanks for shopping local!');
    } on ApiException catch (e) {
      if (mounted) AppSnack.show(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => acting = false);
    }
  }

  Future<void> _rate() async {
    int stars = 5;
    final commentCtrl = TextEditingController();
    final result = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheetState) => Padding(
          padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom),
          child: Container(
            decoration: const BoxDecoration(color: AppColors.bg, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Rate this order', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: List.generate(5, (i) {
                    final filled = i < stars;
                    return IconButton(
                      onPressed: () => setSheetState(() => stars = i + 1),
                      icon: Icon(filled ? Icons.star_rounded : Icons.star_border_rounded, color: AppColors.accent, size: 34),
                    );
                  }),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: commentCtrl,
                  maxLines: 3,
                  decoration: const InputDecoration(hintText: 'Share your experience (optional)'),
                ),
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: () async {
                      try {
                        await context.read<AppState>().api.post('/orders/${widget.orderId}/rating', {
                          'stars': stars,
                          if (commentCtrl.text.trim().isNotEmpty) 'comment': commentCtrl.text.trim(),
                        });
                        if (ctx.mounted) Navigator.of(ctx).pop(true);
                      } on ApiException catch (e) {
                        if (ctx.mounted) AppSnack.show(ctx, e.message, error: true);
                      }
                    },
                    child: const Text('Submit review'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
    if (result == true) _load();
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const Scaffold(body: Center(child: CircularProgressIndicator(color: AppColors.primary)));
    if (order == null) {
      return Scaffold(appBar: AppBar(), body: const EmptyState(icon: Icons.error_outline_rounded, title: 'Not found', subtitle: 'This order is unavailable.'));
    }

    final status = order!['status'] as String;
    final info = statusInfo(status);
    final step = stepOf(status);
    final failed = status == 'DELIVERY_FAILED' || status == 'RETURNED';

    return Scaffold(
      appBar: AppBar(title: Text('Order #${order!['id']}')),
      body: RefreshIndicator(
        onRefresh: _load,
        color: AppColors.primary,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 30),
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(color: info.color.withOpacity(.08), borderRadius: BorderRadius.circular(16)),
              child: Row(
                children: [
                  Icon(failed ? Icons.error_outline_rounded : Icons.local_shipping_outlined, color: info.color),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(info.label, style: TextStyle(color: info.color, fontWeight: FontWeight.w800, fontSize: 15)),
                        Text('Tracking code: ${order!['tracking_code']}', style: const TextStyle(color: AppColors.muted, fontSize: 12.5)),
                        if (failed && order!['failure_reason'] != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 4),
                            child: Text('${order!['failure_reason']}', style: const TextStyle(color: AppColors.danger, fontSize: 12.5)),
                          ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            if (!failed) ...[
              const SizedBox(height: 20),
              _TrackingTimeline(currentStep: step),
            ],
            const SizedBox(height: 24),
            const Text('Items', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
            const SizedBox(height: 10),
            ...items.map((it) => Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(14), border: Border.all(color: AppColors.line)),
                    child: Row(
                      children: [
                        SizedBox(width: 48, height: 48, child: ProductArt(seed: '${it['product_id']}${it['name']}')),
                        const SizedBox(width: 12),
                        Expanded(child: Text('${it['name']}', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700))),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text('x${it['quantity']}', style: const TextStyle(color: AppColors.muted, fontSize: 12.5)),
                            Text(money(it['unit_price']), style: const TextStyle(fontWeight: FontWeight.w700)),
                          ],
                        ),
                      ],
                    ),
                  ),
                )),
            const SizedBox(height: 14),
            _infoCard('Deliver to', address == null
                ? '—'
                : '${address!['recipient']} · ${address!['phone']}\n${address!['street']}, ${address!['barangay']}, ${address!['city']}, ${address!['province']}'),
            const SizedBox(height: 10),
            _infoCard('Payment', payment == null ? '—' : '${_paymentLabel(payment!['method'])} — ${_paymentStatusLabel(payment!['status'])}'),
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(14), border: Border.all(color: AppColors.line)),
              child: Column(
                children: [
                  _row('Discount', money(order!['discount'])),
                  const SizedBox(height: 8),
                  _row('Total', money(order!['total']), bold: true),
                ],
              ),
            ),
            const SizedBox(height: 24),
            if (status == 'DELIVERED')
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: acting ? null : _confirmReceived,
                  icon: const Icon(Icons.check_circle_outline_rounded),
                  label: acting ? const Text('Please wait…') : const Text('Confirm order received'),
                ),
              ),
            if (status == 'COMPLETED' && rating == null)
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(onPressed: _rate, icon: const Icon(Icons.star_border_rounded), label: const Text('Rate this order')),
              ),
            if (status == 'COMPLETED' && rating != null)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(color: AppColors.accent.withOpacity(.1), borderRadius: BorderRadius.circular(14)),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(children: [
                      ...List.generate(5, (i) => Icon(i < (rating!['stars'] ?? 0) ? Icons.star_rounded : Icons.star_border_rounded, color: AppColors.accent, size: 18)),
                      const SizedBox(width: 6),
                      const Text('Your review', style: TextStyle(fontWeight: FontWeight.w700)),
                    ]),
                    if ((rating!['comment'] ?? '').toString().isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text('${rating!['comment']}', style: const TextStyle(color: AppColors.muted)),
                    ],
                  ],
                ),
              ),
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              child: TextButton.icon(
                onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => ComplaintFormScreen(orderId: order!['id']))),
                icon: const Icon(Icons.report_outlined),
                label: const Text('Report a problem with this order'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _paymentLabel(String m) => m == 'cod' ? 'Cash on delivery' : 'Manual payment';
  String _paymentStatusLabel(String s) => s.replaceAll('_', ' ');

  Widget _infoCard(String title, String body) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(14), border: Border.all(color: AppColors.line)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(color: AppColors.muted, fontSize: 12, fontWeight: FontWeight.w700)),
          const SizedBox(height: 4),
          Text(body, style: const TextStyle(fontWeight: FontWeight.w600, height: 1.4)),
        ],
      ),
    );
  }

  Widget _row(String label, String value, {bool bold = false}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: TextStyle(color: bold ? AppColors.ink : AppColors.muted, fontWeight: bold ? FontWeight.w800 : FontWeight.w500)),
        Text(value, style: TextStyle(fontWeight: bold ? FontWeight.w800 : FontWeight.w600, fontSize: bold ? 16 : 14)),
      ],
    );
  }
}

class _TrackingTimeline extends StatelessWidget {
  final int currentStep;
  const _TrackingTimeline({required this.currentStep});

  static const _labels = ['Placed', 'Preparing', 'Picked up', 'Sorting', 'Delivering', 'Delivered'];
  static const _icons = [
    Icons.receipt_long_rounded,
    Icons.inventory_2_outlined,
    Icons.local_shipping_outlined,
    Icons.warehouse_outlined,
    Icons.two_wheeler_rounded,
    Icons.home_rounded,
  ];

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(16), border: Border.all(color: AppColors.line)),
      child: Row(
        children: List.generate(_labels.length, (i) {
          final n = i + 1;
          final done = n <= currentStep;
          return Expanded(
            child: Column(
              children: [
                Row(
                  children: [
                    if (i > 0) Expanded(child: Container(height: 2, color: done ? AppColors.primary : AppColors.line)),
                    Container(
                      width: 30,
                      height: 30,
                      decoration: BoxDecoration(color: done ? AppColors.primary : AppColors.line, shape: BoxShape.circle),
                      child: Icon(_icons[i], size: 15, color: done ? Colors.white : AppColors.muted),
                    ),
                    if (i < _labels.length - 1) Expanded(child: Container(height: 2, color: n < currentStep ? AppColors.primary : AppColors.line)),
                  ],
                ),
                const SizedBox(height: 6),
                Text(_labels[i], textAlign: TextAlign.center, style: TextStyle(fontSize: 9.5, fontWeight: FontWeight.w700, color: done ? AppColors.ink : AppColors.muted)),
              ],
            ),
          );
        }),
      ),
    );
  }
}
