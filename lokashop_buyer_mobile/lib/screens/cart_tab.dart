import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import '../services/contracts.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/design.dart';
import 'checkout_screen.dart';
import 'login_screen.dart';

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
    WidgetsBinding.instance.addPostFrameCallback((_) => reload());
  }

  Future<void> reload() async {
    try {
      if (context.read<AppState>().isLoggedIn) {
        await context.read<AppState>().refreshCart();
      }
    } catch (e) {
      if (mounted) AppSnack.show(context, '$e', error: true);
    }
    if (mounted) setState(() => loading = false);
  }

  Future<void> change(Future<void> Function() fn) async {
    try {
      await fn();
    } catch (e) {
      if (mounted) AppSnack.show(context, '$e', error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final selected = app.selectedCart;
    final total = selected.fold<double>(
      0,
      (sum, c) => sum + cartUnit(c) * (int.tryParse('${c['quantity']}') ?? 0),
    );
    return Scaffold(
      appBar: AppBar(title: Text('Your bag (${app.cartCount})')),
      body: PageBody(
        child: !app.isLoggedIn
            ? EmptyState(
                icon: Icons.shopping_bag_outlined,
                title: 'Keep your finds together',
                subtitle: 'Sign in to save items in your bag.',
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
            : app.cart.isEmpty
            ? EmptyState(
                icon: Icons.shopping_bag_outlined,
                title: 'Your next find is waiting',
                subtitle: 'Browse local products and add your favourites.',
                action: TextButton(
                  onPressed: widget.onBrowseProducts,
                  child: const Text('Explore products'),
                ),
              )
            : RefreshIndicator(
                onRefresh: reload,
                child: ListView.separated(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(20),
                  itemCount: app.cart.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 12),
                  itemBuilder: (ctx, i) {
                    final c = app.cart[i],
                        id = int.parse('${c['id']}'),
                        qty = int.parse('${c['quantity']}');
                    return SheetCard(
                      child: Column(
                        children: [
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Checkbox(
                                value: app.selectedCartIds.contains(id),
                                onChanged: (v) =>
                                    app.selectCart(id, v ?? false),
                              ),
                              AppPhoto(productPhoto(c), width: 68, height: 80),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      '${c['name']}',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                    if (c['variation'] != null)
                                      Text(
                                        '${c['variation']}',
                                        style: const TextStyle(
                                          color: AppColors.muted,
                                          fontSize: 12,
                                        ),
                                      ),
                                    const SizedBox(height: 7),
                                    Text(
                                      money(cartUnit(c)),
                                      style: const TextStyle(
                                        color: AppColors.primary,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                          const Divider(height: 24),
                          Row(
                            children: [
                              IconButton(
                                onPressed: qty > 1
                                    ? () => change(
                                        () => app.updateCartQty(id, qty - 1),
                                      )
                                    : null,
                                icon: const Icon(Icons.remove_circle_outline),
                              ),
                              Text(
                                '$qty',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              IconButton(
                                onPressed: () => change(
                                  () => app.updateCartQty(id, qty + 1),
                                ),
                                icon: const Icon(Icons.add_circle_outline),
                              ),
                              const Spacer(),
                              TextButton.icon(
                                onPressed: () =>
                                    change(() => app.removeFromCart(id)),
                                icon: const Icon(
                                  Icons.delete_outline,
                                  size: 18,
                                ),
                                label: const Text('Remove'),
                              ),
                            ],
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ),
      ),
      bottomNavigationBar: !app.isLoggedIn || app.cart.isEmpty
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${selected.length} selected · items subtotal',
                            style: const TextStyle(
                              color: AppColors.muted,
                              fontSize: 11,
                            ),
                          ),
                          Text(
                            money(total),
                            style: const TextStyle(
                              fontSize: 22,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ],
                      ),
                    ),
                    FilledButton(
                      onPressed: selected.isEmpty
                          ? null
                          : () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => CheckoutScreen(
                                  cartIds: selected
                                      .map((c) => int.parse('${c['id']}'))
                                      .toList(),
                                ),
                              ),
                            ),
                      child: const Text('Checkout'),
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}
