import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import '../services/api_service.dart';
import '../widgets/design.dart';
import '../widgets/common.dart';
import 'addresses_screen.dart';
import '../theme.dart';
import 'home_shell.dart';

class CheckoutScreen extends StatefulWidget {
  final List<int>? cartIds;
  const CheckoutScreen({super.key, this.cartIds});
  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  List addresses = [], vouchers = [];
  Set<String> sellerIds = {};
  String? voucherError;
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
    quoteVersion++;
    try {
      final api = context.read<AppState>().api;
      final results = await Future.wait<dynamic>([
        api.get('/addresses'),
        api.get('/vouchers').catchError((Object _) => null),
        api.get('/cart'),
      ]);
      final result = results[0];
      if (!mounted) return;
      setState(() {
        addresses = List.from(result);
        vouchers = List.from(results[1] ?? []);
        voucherError = results[1] == null ? 'Vouchers could not load. Tap Refresh vouchers to try again.' : null;
        final selected = widget.cartIds ?? context.read<AppState>().selectedCartIds.toList();
        sellerIds = (results[2] as List).where((row) => selected.contains(int.tryParse('${row['id']}'))).map<String>((row) => '${row['seller_id']}').toSet();
        for (final field in [store, platform, shipping]) {
          if (field.text.isNotEmpty && !vouchers.any((v) => v['code'] == field.text && _available(v) && (v['seller_id'] == null || sellerIds.contains('${v['seller_id']}')))) field.clear();
        }
        if (!addresses.any((a) => a['id'] == address)) {
          address = addresses.isEmpty ? null : addresses.first['id'];
        }
        quote = null;
        error = null;
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

  bool _available(dynamic v) =>
      (v['claimed'] == true || v['claimed'] == 1) &&
      v['display_state'] == 'Available';

  Widget voucherPicker(String label, TextEditingController field, String kind) {
    final rows = vouchers.where((v) {
      if (!_available(v)) return false;
      final storeVoucher = v['seller_id'] != null;
      if (kind == 'store') return storeVoucher && sellerIds.contains('${v['seller_id']}');
      return !storeVoucher && (kind == 'shipping' ? v['type'] == 'shipping' : v['type'] != 'shipping');
    }).toList();
    final codes = <String>{};
    final choices = rows.where((v) => codes.add('${v['code']}')).toList();
    final current = codes.contains(field.text) ? field.text : '';
    return DropdownButtonFormField<String>(
      key: ValueKey('$kind:$current:${codes.join(',')}'),
      initialValue: current,
      isExpanded: true,
      decoration: InputDecoration(labelText: label, helperText: choices.isEmpty ? 'No available claimed vouchers' : null),
      items: [
        const DropdownMenuItem<String>(value: '', child: Text('No voucher')),
        ...choices.map((v) => DropdownMenuItem<String>(
          value: '${v['code']}',
          child: Text('${v['code']} · ${v['type'] == 'percent' ? '${v['value']}% off' : '${money(v['value'])} off'} · Min ${money(v['minimum_spend'])}', maxLines: 2, overflow: TextOverflow.ellipsis),
        )),
      ],
      onChanged: busy || quoting ? null : (value) {
        field.text = value ?? '';
        invalidateQuote();
        if (address != null) calculate();
      },
    );
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
                if (addresses.isEmpty)
                  const SheetCard(child: Text('Add a delivery address in Addresses, then return here to choose it.'))
                else ...[
                  DropdownButtonFormField<int>(
                    key: ValueKey(address), initialValue: address, isExpanded: true,
                    decoration: const InputDecoration(labelText: 'Saved delivery address', prefixIcon: Icon(Icons.location_on_outlined)),
                    items: addresses.map((a) => DropdownMenuItem<int>(value: a['id'], child: Text('${a['recipient']} · ${a['city']}', overflow: TextOverflow.ellipsis))).toList(),
                    onChanged: busy || quoting ? null : (value) { setState(() => address = value); calculate(); },
                  ),
                  const SizedBox(height: 12),
                  ...addresses.where((a) => a['id'] == address).map((a) => SheetCard(child: Text('${a['street']}, ${a['barangay']}, ${a['city']}, ${a['province']} ${a['postal_code'] ?? ''}\n${a['phone']}'))),
                ],
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: busy || quoting ? null : () async {
                    await Navigator.push(context, MaterialPageRoute(builder: (_) => const AddressesScreen()));
                    if (mounted) await load();
                  },
                  icon: const Icon(Icons.edit_location_alt_outlined), label: const Text('Manage saved addresses'),
                ),
                const SectionHeading('Make the most of your bag'),
                SheetCard(
                  child: Column(
                    children: [
                      voucherPicker('Store voucher', store, 'store'),
                      const SizedBox(height: 12),
                      voucherPicker('Platform voucher', platform, 'platform'),
                      const SizedBox(height: 12),
                      voucherPicker('Shipping voucher', shipping, 'shipping'),
                      const SizedBox(height: 8),
                      const Text('Only your claimed, available vouchers are listed. Minimum spend and final eligibility are checked when the total updates.', style: TextStyle(fontSize: 11, color: AppColors.muted)),
                      if (voucherError != null) Text(voucherError!, style: const TextStyle(color: AppColors.danger)),
                      TextButton(onPressed: busy || quoting ? null : load, child: const Text('Refresh vouchers')),
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
