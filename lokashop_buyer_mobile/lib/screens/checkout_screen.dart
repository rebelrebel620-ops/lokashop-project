import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import '../services/api_service.dart';
import '../widgets/design.dart';
import '../widgets/common.dart';
import '../widgets/address_form.dart';
import '../theme.dart';
import 'home_shell.dart';

class CheckoutScreen extends StatefulWidget {
  final List<int>? cartIds;
  const CheckoutScreen({super.key, this.cartIds});
  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  List addresses = [];
  int? address;
  Map? quote;
  bool loading = true, busy = false, quoting = false;
  String? error;
  final store = TextEditingController(),
      platform = TextEditingController(),
      shipping = TextEditingController(),
      note = TextEditingController();
  final key = requestKey();
  int quoteVersion = 0;
  void invalidateQuote() {
    setState(() {
      quoteVersion++;
      quote = null;
      quoting = false;
    });
  }

  @override
  void initState() {
    super.initState();
    load();
  }

  Map<String, dynamic> data() => {
    'address_id': address,
    'payment_method': 'cod',
    'selection_required': 1,
    'cart_ids':
        widget.cartIds ?? context.read<AppState>().selectedCartIds.toList(),
    if (store.text.trim().isNotEmpty) 'voucher': store.text.trim(),
    if (platform.text.trim().isNotEmpty)
      'platform_voucher': platform.text.trim(),
    if (shipping.text.trim().isNotEmpty)
      'shipping_voucher': shipping.text.trim(),
    'note': note.text.trim(),
  };
  Future<void> load() async {
    try {
      final result = await context.read<AppState>().api.get('/addresses');
      if (!mounted) return;
      setState(() {
        addresses = List.from(result);
        if (addresses.isNotEmpty) address ??= addresses.first['id'];
        loading = false;
      });
      if (address != null) await calculate();
    } catch (e) {
      if (mounted) {
        setState(() {
          error = '$e';
          loading = false;
        });
      }
    }
  }

  Future<void> calculate() async {
    final version = ++quoteVersion;
    final submitted = data();
    setState(() {
      quoting = true;
      quote = null;
      error = null;
    });
    try {
      final result = await context.read<AppState>().api.post(
        '/checkout/quote',
        submitted,
      );
      if (mounted && version == quoteVersion) {
        setState(() => quote = Map.from(result));
      }
    } catch (e) {
      if (mounted && version == quoteVersion) setState(() => error = '$e');
    } finally {
      if (mounted && version == quoteVersion) setState(() => quoting = false);
    }
  }

  Future<void> place() async {
    if (quote == null || address == null) return;
    if (!await confirmAction(
      context,
      'Place your order?',
      'Pay ${money(quote!['total'])} by cash on delivery.',
      label: 'Place order',
    )) {
      return;
    }
    if (!mounted) return;
    setState(() => busy = true);
    try {
      final app = context.read<AppState>();
      await app.api.post('/checkout', {...data(), '_request_key': key});
      await app.refreshCart();
      if (!mounted) return;
      AppSnack.show(context, 'Your local finds are on their way.');
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (_) => const HomeShell(initialIndex: 1)),
        (_) => false,
      );
    } catch (e) {
      if (mounted) AppSnack.show(context, '$e', error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  void dispose() {
    store.dispose();
    platform.dispose();
    shipping.dispose();
    note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('A few final details')),
    body: PageBody(
      child: loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(22),
              children: [
                const Eyebrow('SAFE CHECKOUT · CASH ON DELIVERY'),
                const SectionHeading('Where should we deliver?'),
                ...addresses.map(
                  (a) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: SheetCard(
                      child: ListTile(
                        contentPadding: EdgeInsets.zero,
                        selected: address == a['id'],
                        leading: Icon(
                          address == a['id']
                              ? Icons.check_circle
                              : Icons.radio_button_unchecked,
                        ),
                        onTap: busy || quoting
                            ? null
                            : () {
                                setState(() => address = a['id']);
                                calculate();
                              },
                        title: Text(
                          '${a['recipient']}',
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                        subtitle: Text(
                          '${a['street']}, ${a['barangay']}, ${a['city']}\n${a['phone']}',
                        ),
                      ),
                    ),
                  ),
                ),
                OutlinedButton.icon(
                  onPressed: busy
                      ? null
                      : () async {
                          final saved = await showModalBottomSheet<bool>(
                            context: context,
                            isScrollControlled: true,
                            builder: (_) => const AddressForm(),
                          );
                          if (saved == true) await load();
                        },
                  icon: const Icon(Icons.add_location_alt_outlined),
                  label: const Text('Add delivery address'),
                ),
                const SectionHeading('Make the most of your bag'),
                SheetCard(
                  child: Column(
                    children: [
                      TextField(
                        controller: store,
                        onChanged: (_) => invalidateQuote(),
                        decoration: const InputDecoration(
                          labelText: 'Store voucher',
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: platform,
                        onChanged: (_) => invalidateQuote(),
                        decoration: const InputDecoration(
                          labelText: 'Platform voucher',
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: shipping,
                        onChanged: (_) => invalidateQuote(),
                        decoration: const InputDecoration(
                          labelText: 'Shipping voucher',
                        ),
                      ),
                      const SizedBox(height: 12),
                      OutlinedButton(
                        onPressed: address == null || quoting || busy
                            ? null
                            : calculate,
                        child: const Text('Apply and update total'),
                      ),
                    ],
                  ),
                ),
                const SectionHeading('Order summary'),
                if (error != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: Text(
                      error!,
                      style: const TextStyle(color: AppColors.danger),
                    ),
                  ),
                if (quoting)
                  const Center(child: CircularProgressIndicator())
                else if (quote != null)
                  SheetCard(
                    child: Column(
                      children: [
                        row('Products', money(quote!['merchandise_total'])),
                        row('Shipping', money(quote!['shipping_total'])),
                        row('Discounts', '−${money(quote!['discount_total'])}'),
                        const Divider(height: 28),
                        row(
                          'Cash on delivery',
                          money(quote!['total']),
                          bold: true,
                        ),
                        const SizedBox(height: 12),
                        const Text(
                          'Totals are calculated by LokaShop using your selected products, delivery address and vouchers.',
                          style: TextStyle(
                            fontSize: 11,
                            color: AppColors.muted,
                          ),
                        ),
                      ],
                    ),
                  ),
                const SizedBox(height: 18),
                TextField(
                  controller: note,
                  maxLength: 200,
                  maxLines: 2,
                  decoration: const InputDecoration(
                    labelText: 'Delivery note (optional)',
                  ),
                ),
                const SizedBox(height: 20),
              ],
            ),
    ),
    bottomNavigationBar: SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: FilledButton(
          onPressed: busy || quoting || quote == null ? null : place,
          child: Text(
            busy
                ? 'Placing order…'
                : quote == null
                ? 'Update total to continue'
                : 'Place order · ${money(quote!['total'])}',
          ),
        ),
      ),
    ),
  );
  Widget row(String label, String value, {bool bold = false}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: Row(
      children: [
        Expanded(child: Text(label)),
        Text(
          value,
          style: TextStyle(
            fontWeight: bold ? FontWeight.w900 : FontWeight.w700,
            fontSize: bold ? 19 : 14,
          ),
        ),
      ],
    ),
  );
}
