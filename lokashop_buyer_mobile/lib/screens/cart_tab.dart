import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';
import '../services/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';
import 'checkout_screen.dart';
import 'login_screen.dart';
import 'shop_tab.dart';

class CartTab extends StatefulWidget {
  final VoidCallback? onBrowseProducts;
  const CartTab({super.key, this.onBrowseProducts});
  @override
  State<CartTab> createState() => _CartTabState();
}

class _CartTabState extends State<CartTab> {
  bool loading = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _refresh());
  }

  Future<void> _refresh() async {
    final app = context.read<AppState>();
    if (!app.isLoggedIn) {
      setState(() => loading = false);
      return;
    }
    setState(() => loading = true);
    try {
      await app.refreshCart();
    } catch (_) {
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();

    if (!app.isLoggedIn) {
      return Scaffold(
        appBar: AppBar(title: const Text('Cart')),
        body: EmptyState(
          icon: Icons.shopping_cart_outlined,
          title: 'Log in to see your cart',
          subtitle: 'Your cart is saved to your LokaShop account.',
          action: ElevatedButton(
            onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LoginScreen())),
            child: const Padding(padding: EdgeInsets.symmetric(horizontal: 24), child: Text('Log in')),
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('My cart')),
      body: loading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : app.cart.isEmpty
              ? EmptyState(
                  icon: Icons.shopping_cart_outlined,
                  title: 'Your cart is empty',
                  subtitle: 'Browse the shop and add something you like.',
                  action: OutlinedButton(
                    onPressed: widget.onBrowseProducts ?? () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const Scaffold(body: ShopTab()))),
                    child: const Padding(padding: EdgeInsets.symmetric(horizontal: 24), child: Text('Browse products')),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _refresh,
                  color: AppColors.primary,
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
                    itemCount: app.cart.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (context, i) => _CartRow(item: app.cart[i]),
                  ),
                ),
      bottomNavigationBar: (!loading && app.cart.isNotEmpty)
          ? SafeArea(
              child: Container(
                padding: const EdgeInsets.fromLTRB(20, 14, 20, 14),
                decoration: const BoxDecoration(color: AppColors.surface, border: Border(top: BorderSide(color: AppColors.line))),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Total', style: TextStyle(color: AppColors.muted, fontSize: 12)),
                          Text(money(app.cartTotal), style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                        ],
                      ),
                    ),
                    ElevatedButton(
                      onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const CheckoutScreen())),
                      child: const Padding(padding: EdgeInsets.symmetric(horizontal: 28), child: Text('Checkout')),
                    ),
                  ],
                ),
              ),
            )
          : null,
    );
  }
}

class _CartRow extends StatelessWidget {
  final Map item;
  const _CartRow({required this.item});

  @override
  Widget build(BuildContext context) {
    final price = double.tryParse('${item['price']}') ?? 0;
    final adj = double.tryParse('${item['price_adjustment'] ?? 0}') ?? 0;
    final discount = double.tryParse('${item['discount_percent'] ?? 0}') ?? 0;
    final unit = (price + adj) * (1 - discount / 100);
    final qty = item['quantity'] as int;
    final maxStock = item['variation_id'] != null
        ? (int.tryParse('${item['variation_stock']}') ?? 0)
        : (int.tryParse('${item['product_stock']}') ?? 0);

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(16), border: Border.all(color: AppColors.line)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 64, height: 64, child: ProductArt(seed: '${item['product_id']}${item['name']}')),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${item['name']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
                if (item['variation'] != null) Text('${item['variation']}', style: const TextStyle(color: AppColors.muted, fontSize: 12.5)),
                const SizedBox(height: 6),
                Text(money(unit), style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w800)),
                const SizedBox(height: 8),
                Row(
                  children: [
                    _stepperButton(context, Icons.remove_rounded, () {
                      if (qty > 1) context.read<AppState>().updateCartQty(item['id'], qty - 1);
                      else _remove(context);
                    }),
                    Container(
                      width: 34,
                      alignment: Alignment.center,
                      child: Text('$qty', style: const TextStyle(fontWeight: FontWeight.w800)),
                    ),
                    _stepperButton(context, Icons.add_rounded, qty < maxStock
                        ? () => context.read<AppState>().updateCartQty(item['id'], qty + 1)
                        : null),
                    const Spacer(),
                    IconButton(
                      icon: const Icon(Icons.delete_outline_rounded, color: AppColors.danger),
                      onPressed: () => _remove(context),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _remove(BuildContext context) async {
    try {
      await context.read<AppState>().removeFromCart(item['id']);
    } on ApiException catch (e) {
      if (context.mounted) AppSnack.show(context, e.message, error: true);
    }
  }

  Widget _stepperButton(BuildContext context, IconData icon, VoidCallback? onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        width: 28,
        height: 28,
        decoration: BoxDecoration(borderRadius: BorderRadius.circular(8), border: Border.all(color: AppColors.line)),
        child: Icon(icon, size: 16, color: onTap == null ? AppColors.muted.withOpacity(.4) : AppColors.ink),
      ),
    );
  }
}
