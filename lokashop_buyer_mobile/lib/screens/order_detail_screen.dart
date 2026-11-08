import '../widgets/auto_refresh.dart';
import '../widgets/photo_viewer.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import '../services/contracts.dart';
import '../widgets/design.dart';
import '../widgets/common.dart';
import '../theme.dart';
import 'buyer_features.dart';
import 'complaint_form_screen.dart';

class OrderDetailScreen extends StatefulWidget {
  final int orderId;
  const OrderDetailScreen({super.key, required this.orderId});
  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen>
    with AutoRefresh<OrderDetailScreen> {
  @override
  bool get autoRefreshEnabled => !loading && !busy;
  @override
  Future<void> refreshAutomatically() => load();
  Map? data;
  bool loading = true, busy = false;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  bool fetching = false;
  Future<void> load() async {
    if (fetching) {
      return;
    }
    fetching = true;
    if (mounted) {
      setState(() => error = null);
    }
    try {
      final r = await context.read<AppState>().api.get(
        '/orders/${widget.orderId}',
      );
      if (mounted) {
        setState(() => data = Map.from(r));
      }
    } catch (e) {
      if (mounted) {
        setState(() => error = '$e');
      }
    } finally {
      fetching = false;
      if (mounted) {
        setState(() => loading = false);
      }
    }
  }

  Future<void> action(String path, Map<String, dynamic> fields) async {
    setState(() => busy = true);
    try {
      await context.read<AppState>().api.post(path, fields);
      await load();
      if (mounted) {
        AppSnack.show(context, 'Order updated.');
      }
    } catch (e) {
      if (mounted) {
        AppSnack.show(context, '$e', error: true);
      }
    } finally {
      if (mounted) {
        setState(() => busy = false);
      }
    }
  }

  Widget _totalRow(String label, dynamic value, {bool discount = false}) =>
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 5),
        child: Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: const TextStyle(color: AppColors.muted),
              ),
            ),
            Text(
              '${discount ? '−' : ''}${money(value)}',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ],
        ),
      );

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (data == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Order')),
        body: Center(child: Text(error ?? 'Order unavailable')),
      );
    }
    final o = data!['order'] as Map,
        items = List.from(data!['items']),
        status = '${o['status']}',
        address = data!['address'] as Map?;
    return Scaffold(
      appBar: AppBar(title: Text('Order #${widget.orderId}')),
      body: PageBody(
        child: RefreshIndicator(
          onRefresh: load,
          child: ListView(
            padding: const EdgeInsets.all(22),
            children: [
              SheetCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Eyebrow('YOUR DELIVERY'),
                    const SizedBox(height: 12),
                    StatusBadge(status: status),
                    const SizedBox(height: 12),
                    SelectableText(
                      '${o['tracking_code']}',
                      style: const TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 19,
                      ),
                    ),
                    TextButton.icon(
                      onPressed: () => Clipboard.setData(
                        ClipboardData(text: '${o['tracking_code']}'),
                      ),
                      icon: const Icon(Icons.copy, size: 15),
                      label: const Text('Copy tracking code'),
                    ),
                    Text(
                      '${o['seller_name'] ?? 'Local seller'}',
                      style: const TextStyle(color: AppColors.muted),
                    ),
                  ],
                ),
              ),
              const SectionHeading('Your items'),
              ...items.map(
                (i) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: SheetCard(
                    child: Column(
                      children: [
                        Row(
                          children: [
                            AppPhoto(
                              i['image_url'] ?? i['image'],
                              width: 62,
                              height: 70,
                            ),
                            const SizedBox(width: 14),
                            Expanded(
                              child: Text(
                                '${i['name']}',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                            Text(
                              '× ${i['quantity']}\n${money(i['unit_price'])}',
                              textAlign: TextAlign.right,
                            ),
                          ],
                        ),
                        if (status == 'COMPLETED')
                          TextButton.icon(
                            onPressed: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) =>
                                    ReviewEditor(item: i, review: i['review']),
                              ),
                            ).then((_) => load()),
                            icon: const Icon(Icons.star_outline),
                            label: Text(
                              i['review'] == null
                                  ? 'Review this product'
                                  : 'View / edit review',
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
              ),
              const SectionHeading('Delivery journey'),
              SheetCard(
                child: Column(
                  children:
                      (List.from(data!['timeline'] ?? data!['history'] ?? []))
                          .map(
                            (h) => Padding(
                              padding: const EdgeInsets.symmetric(vertical: 10),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Icon(
                                    Icons.check_circle_outline,
                                    color: AppColors.primary,
                                    size: 20,
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          '${h['title'] ?? h['to_status']}'
                                              .replaceAll('_', ' '),
                                          style: const TextStyle(
                                            fontSize: 12,
                                            fontWeight: FontWeight.w700,
                                          ),
                                        ),
                                        Text(
                                          '${h['at'] ?? h['created_at']}',
                                          style: const TextStyle(
                                            color: AppColors.muted,
                                            fontSize: 11,
                                          ),
                                        ),
                                        if (h['reason'] != null)
                                          Text(
                                            '${h['reason']}',
                                            style: const TextStyle(
                                              color: AppColors.danger,
                                              fontSize: 12,
                                            ),
                                          ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          )
                          .toList(),
                ),
              ),
              if (status == 'OUT_FOR_DELIVERY')
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  child: SheetCard(
                    child: Text(
                      data!['payment']?['method'] == 'wallet'
                          ? 'Your order is paid using Wallet. No cash collection is needed. Check the parcel before confirming receipt.'
                          : 'Your rider is on the way. Prepare the cash-on-delivery amount. After delivery, tap Order received to complete your order.',
                    ),
                  ),
                ),
              if (o['proof_url'] != null) ...[
                const SectionHeading('Proof of delivery'),
                SheetCard(
                  child: Column(
                    children: [
                      AppPhoto(
                        '${o['proof_url']}',
                        height: 280,
                        width: double.infinity,
                        fit: BoxFit.contain,
                        private: true,
                        imageSize: 1200,
                      ),
                      TextButton.icon(
                        onPressed: () {
                          final raw = '${o['proof_url']}';
                          final uri = Uri.parse(mediaUrl(raw));
                          final original = uri
                              .replace(
                                queryParameters: {
                                  ...uri.queryParameters,
                                  'original': '1',
                                },
                              )
                              .toString();
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => PhotoViewer(
                                url: original,
                                headers: context
                                    .read<AppState>()
                                    .api
                                    .imageHeaders(original),
                              ),
                            ),
                          );
                        },
                        icon: const Icon(Icons.zoom_in),
                        label: const Text('View original photo'),
                      ),
                    ],
                  ),
                ),
              ],
              if (status == 'DELIVERED')
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 12),
                  child: Text(
                    'Confirm Order received after checking your parcel. Eligible orders complete automatically seven days after delivery if there is no active return or dispute. Seller funds are released after completion.',
                  ),
                ),
              const SectionHeading('Delivery & payment'),
              SheetCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (address != null)
                      Text(
                        '${address['recipient']} · ${address['phone']}\n${address['street']}, ${address['barangay']}, ${address['city']}, ${address['province']}',
                      ),
                    const Divider(height: 28),
                    Text(
                      '${data!['payment']?['method'] == 'wallet' ? 'LokaShop Wallet' : 'Cash on delivery'} · ${data!['payment']?['status'] ?? ''}',
                      style: const TextStyle(color: AppColors.muted),
                    ),
                    const SizedBox(height: 16),
                    _totalRow('Items subtotal', o['merchandise_total']),
                    _totalRow('Shipping cost', o['shipping_fee']),
                    if ((double.tryParse('${o['cod_rounding_adjustment']}') ??
                            0) !=
                        0)
                      _totalRow('COD rounding', o['cod_rounding_adjustment']),
                    _totalRow(
                      'Store discount',
                      o['store_discount'],
                      discount: true,
                    ),
                    if ((double.tryParse('${o['platform_discount']}') ?? 0) > 0)
                      _totalRow(
                        'Platform discount',
                        o['platform_discount'],
                        discount: true,
                      ),
                    if ((double.tryParse('${o['shipping_discount']}') ?? 0) > 0)
                      _totalRow(
                        'Shipping discount',
                        o['shipping_discount'],
                        discount: true,
                      ),
                    const Divider(height: 24),
                    Text(
                      'Total ${money(o['total'])}',
                      style: const TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 23,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              if (['PLACED', 'CONFIRMED', 'PREPARING'].contains(status))
                OutlinedButton(
                  onPressed: busy
                      ? null
                      : () async {
                          final reason = await askText(
                            context,
                            'Why cancel this order?',
                            hint: 'At least 10 characters',
                            minLength: 10,
                          );
                          if (reason != null) {
                            await action('/orders/${widget.orderId}/cancel', {
                              'reason': reason,
                            });
                          }
                        },
                  child: const Text('Cancel order'),
                ),
              if (status == 'DELIVERED') ...[
                FilledButton(
                  onPressed: busy
                      ? null
                      : () async {
                          if (await confirmAction(
                            context,
                            'Everything arrived safely?',
                            'Confirming receipt completes your order. Request a return first if there is an issue.',
                            label: 'Order received',
                          )) {
                            await action(
                              '/orders/${widget.orderId}/confirm',
                              {},
                            );
                          }
                        },
                  child: const Text('Order received'),
                ),
                const SizedBox(height: 10),
                OutlinedButton(
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => data!['return_request'] == null
                          ? ReturnEditor(orderId: widget.orderId)
                          : const ReturnsScreen(),
                    ),
                  ).then((_) => load()),
                  child: Text(
                    data!['return_request'] == null
                        ? 'Request return'
                        : 'View returns',
                  ),
                ),
              ],
              const SizedBox(height: 10),
              TextButton(
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) =>
                        ComplaintFormScreen(orderId: widget.orderId),
                  ),
                ),
                child: const Text('Get help with this order'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
