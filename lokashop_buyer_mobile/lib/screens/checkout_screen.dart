import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';
import '../services/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/address_form.dart';
import 'home_shell.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});
  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  bool loading = true;
  bool placing = false;
  List addresses = [];
  int? selectedAddress;
  final _voucherCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    try {
      final res = await context.read<AppState>().api.get('/addresses');
      setState(() {
        addresses = List.from(res);
        if (addresses.isNotEmpty) selectedAddress = addresses.first['id'];
      });
    } catch (_) {
      if (mounted) AppSnack.show(context, 'Could not load addresses.', error: true);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _addAddress() async {
    final result = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const AddressForm(),
    );
    if (result == true) _load();
  }

  Future<void> _placeOrder() async {
    if (selectedAddress == null) {
      AppSnack.show(context, 'Add a delivery address first.', error: true);
      return;
    }
    setState(() => placing = true);
    try {
      final app = context.read<AppState>();
      await app.api.post('/checkout', {
        'address_id': selectedAddress,
        'payment_method': 'cod',
        if (_voucherCtrl.text.trim().isNotEmpty) 'voucher': _voucherCtrl.text.trim(),
      });
      await app.refreshCart();
      if (!mounted) return;
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (_) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Row(children: [Icon(Icons.check_circle_rounded, color: AppColors.success), SizedBox(width: 10), Text('Order placed!')]),
          content: const Text('Your order was placed successfully. You can track it from My orders.'),
          actions: [
            ElevatedButton(
              onPressed: () => Navigator.of(context).pushAndRemoveUntil(MaterialPageRoute(builder: (_) => const HomeShell()), (r) => false),
              child: const Text('View orders'),
            ),
          ],
        ),
      );
    } on ApiException catch (e) {
      if (mounted) AppSnack.show(context, e.message, error: true);
    } catch (e) {
      if (mounted) AppSnack.show(context, 'Could not place order. Try again.', error: true);
    } finally {
      if (mounted) setState(() => placing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();

    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: loading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : ListView(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
              children: [
                const Text('Delivery address', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                const SizedBox(height: 10),
                ...addresses.map((a) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _AddressTile(
                        address: a,
                        selected: selectedAddress == a['id'],
                        onTap: () => setState(() => selectedAddress = a['id']),
                      ),
                    )),
                OutlinedButton.icon(
                  onPressed: _addAddress,
                  icon: const Icon(Icons.add_rounded, size: 18),
                  label: const Text('Add new address'),
                ),
                const SizedBox(height: 24),
                const Text('Payment method', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                const SizedBox(height: 10),
                const _PaymentTile(
                  title: 'Cash on delivery',
                  subtitle: 'Pay the rider when your order arrives',
                  icon: Icons.payments_outlined,
                ),
                const SizedBox(height: 24),
                const Text('Voucher (optional)', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                const SizedBox(height: 10),
                TextField(
                  controller: _voucherCtrl,
                  textCapitalization: TextCapitalization.characters,
                  decoration: const InputDecoration(hintText: 'Enter voucher code', prefixIcon: Icon(Icons.local_offer_outlined)),
                ),
                const SizedBox(height: 24),
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(16), border: Border.all(color: AppColors.line)),
                  child: Column(
                    children: [
                      _summaryRow('Subtotal', money(app.cartTotal)),
                      const SizedBox(height: 8),
                      _summaryRow('Delivery', 'Calculated by seller'),
                      const Divider(height: 24),
                      _summaryRow('Total', money(app.cartTotal), bold: true),
                    ],
                  ),
                ),
              ],
            ),
      bottomNavigationBar: loading
          ? null
          : SafeArea(
              child: Container(
                padding: const EdgeInsets.fromLTRB(20, 14, 20, 14),
                decoration: const BoxDecoration(color: AppColors.surface, border: Border(top: BorderSide(color: AppColors.line))),
                child: SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: placing ? null : _placeOrder,
                    child: placing
                        ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.4))
                        : Text('Place order • ${money(app.cartTotal)}'),
                  ),
                ),
              ),
            ),
    );
  }

  Widget _summaryRow(String label, String value, {bool bold = false}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: TextStyle(color: bold ? AppColors.ink : AppColors.muted, fontWeight: bold ? FontWeight.w800 : FontWeight.w500)),
        Text(value, style: TextStyle(fontWeight: bold ? FontWeight.w800 : FontWeight.w600, fontSize: bold ? 16 : 14)),
      ],
    );
  }
}

class _AddressTile extends StatelessWidget {
  final Map address;
  final bool selected;
  final VoidCallback onTap;
  const _AddressTile({required this.address, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: selected ? AppColors.primary : AppColors.line, width: selected ? 1.6 : 1),
        ),
        child: Row(
          children: [
            Icon(selected ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded, color: selected ? AppColors.primary : AppColors.muted),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('${address['recipient']} · ${address['phone']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 2),
                  Text('${address['street']}, ${address['barangay']}, ${address['city']}, ${address['province']}',
                      style: const TextStyle(color: AppColors.muted, fontSize: 13)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PaymentTile extends StatelessWidget {
  final String title;
  final String subtitle;
  final IconData icon;
  const _PaymentTile({required this.title, required this.subtitle, required this.icon});

  @override
  Widget build(BuildContext context) {
    return Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AppColors.primary, width: 1.6),
        ),
        child: Row(
          children: [
            Icon(icon, color: AppColors.primary),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
                  Text(subtitle, style: const TextStyle(color: AppColors.muted, fontSize: 12.5)),
                ],
              ),
            ),
            const Icon(Icons.radio_button_checked_rounded, color: AppColors.primary),
          ],
        ),
    );
  }
}
