import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import '../widgets/design.dart';
import '../widgets/common.dart';
import '../theme.dart';
import 'buyer_features.dart';
import 'login_screen.dart';
import 'message_thread_screen.dart';

class ProductDetailScreen extends StatefulWidget {
  final int productId;
  const ProductDetailScreen({super.key, required this.productId});
  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  Map? data;
  bool loading = true, busy = false, saved = false;
  String? error;
  int quantity = 1;
  Map? variant;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final app = context.read<AppState>();
      final result = await app.api.get('/products/${widget.productId}');
      if (!context.mounted) return;
      setState(() => data = Map.from(result));
      if (app.isLoggedIn) {
        final wish = await app.api.get('/wishlist');
        if (context.mounted) {
          setState(
            () =>
                saved = (wish as List).any((p) => p['id'] == widget.productId),
          );
        }
      }
    } catch (e) {
      if (context.mounted) setState(() => error = '$e');
    } finally {
      if (context.mounted) setState(() => loading = false);
    }
  }

  Future<bool> signed() async {
    if (context.read<AppState>().isLoggedIn) return true;
    await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
    return mounted && context.read<AppState>().isLoggedIn;
  }

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (data == null) {
      return Scaffold(
        appBar: AppBar(),
        body: Center(child: Text(error ?? 'Product unavailable')),
      );
    }
    final p = data!['product'] as Map,
        variations = List.from(data!['variations'] ?? []);
    final photos = (p['image_urls'] as List? ?? []);
    final price =
        variant?['effective_price'] ?? p['effective_price'] ?? p['price'];
    final stock = int.tryParse('${variant?['stock'] ?? p['stock']}') ?? 0;
    return Scaffold(
      appBar: AppBar(
        title: const Text('A closer look'),
        actions: [
          IconButton(
            tooltip: 'Save product',
            icon: Icon(saved ? Icons.favorite : Icons.favorite_border),
            onPressed: busy
                ? null
                : () async {
                    if (!await signed()) return;
                    if (!context.mounted) return;
                    setState(() => busy = true);
                    try {
                      await context.read<AppState>().api.post('/wishlist', {
                        'product_id': widget.productId,
                        'enabled': !saved,
                      });
                      if (context.mounted) setState(() => saved = !saved);
                    } catch (e) {
                      if (context.mounted) {
                        AppSnack.show(context, '$e', error: true);
                      }
                    } finally {
                      if (context.mounted) setState(() => busy = false);
                    }
                  },
          ),
        ],
      ),
      body: PageBody(
        child: ListView(
          padding: const EdgeInsets.all(22),
          children: [
            SizedBox(
              height: 320,
              child: photos.isEmpty
                  ? AppPhoto(productPhoto(p))
                  : PageView(
                      children: photos
                          .map((url) => AppPhoto('$url', fit: BoxFit.contain))
                          .toList(),
                    ),
            ),
            const SizedBox(height: 24),
            Eyebrow('${p['category'] ?? 'LOCAL FIND'}'),
            const SizedBox(height: 10),
            Text(
              '${p['name']}',
              style: const TextStyle(
                fontSize: 28,
                height: 1.2,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: Text(
                    money(price),
                    style: const TextStyle(
                      fontSize: 28,
                      fontWeight: FontWeight.w900,
                      color: AppColors.primary,
                    ),
                  ),
                ),
                const Icon(Icons.star, color: Color(0xFFAD802B), size: 19),
                Text(
                  ' ${data!['rating_avg']} (${data!['rating_count']})',
                  style: const TextStyle(fontSize: 12),
                ),
              ],
            ),
            const SizedBox(height: 20),
            if (variations.isNotEmpty)
              DropdownButtonFormField<int>(
                initialValue: variant?['id'],
                isExpanded: true,
                decoration: const InputDecoration(
                  labelText: 'Choose a variation',
                ),
                items: variations
                    .map(
                      (v) => DropdownMenuItem<int>(
                        value: v['id'],
                        child: Text('${v['name']} · ${v['stock']} available'),
                      ),
                    )
                    .toList(),
                onChanged: (id) => setState(
                  () => variant = Map.from(
                    variations.firstWhere((v) => v['id'] == id),
                  ),
                ),
              ),
            const SizedBox(height: 16),
            Row(
              children: [
                const Expanded(
                  child: Text(
                    'Quantity',
                    style: TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),
                IconButton(
                  onPressed: quantity > 1
                      ? () => setState(() => quantity--)
                      : null,
                  icon: const Icon(Icons.remove_circle_outline),
                ),
                Text('$quantity'),
                IconButton(
                  onPressed: quantity < stock
                      ? () => setState(() => quantity++)
                      : null,
                  icon: const Icon(Icons.add_circle_outline),
                ),
              ],
            ),
            Text(
              '$stock available',
              style: const TextStyle(color: AppColors.muted, fontSize: 12),
            ),
            const SectionHeading('Thoughtfully sourced, locally sold'),
            Text(
              '${p['description'] ?? 'No description provided.'}',
              style: const TextStyle(height: 1.8, color: AppColors.muted),
            ),
            const SectionHeading('Meet the seller'),
            SheetCard(
              child: Column(
                children: [
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.storefront_outlined),
                    title: Text('${data!['seller_name'] ?? 'Local seller'}'),
                    subtitle: const Text(
                      'Browse the store and follow for more finds',
                    ),
                    onTap: () async {
                      if (await signed() && context.mounted) {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) =>
                                StoreScreen(sellerId: p['seller_id']),
                          ),
                        );
                      }
                    },
                  ),
                  OutlinedButton.icon(
                    onPressed: () async {
                      if (await signed() && context.mounted) {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => MessageThreadScreen(
                              userId: p['seller_id'],
                              userName: '${data!['seller_name'] ?? 'Seller'}',
                            ),
                          ),
                        );
                      }
                    },
                    icon: const Icon(Icons.forum_outlined),
                    label: const Text('Message seller'),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 30),
          ],
        ),
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: FilledButton.icon(
            onPressed:
                busy || stock <= 0 || variations.isNotEmpty && variant == null
                ? null
                : () async {
                    if (!await signed()) return;
                    if (!context.mounted) return;
                    setState(() => busy = true);
                    try {
                      await context.read<AppState>().addToCart(
                        widget.productId,
                        quantity,
                        variationId: variant?['id'],
                      );
                      if (context.mounted) {
                        AppSnack.show(context, 'Added to your bag.');
                      }
                    } catch (e) {
                      if (context.mounted) {
                        AppSnack.show(context, '$e', error: true);
                      }
                    } finally {
                      if (context.mounted) setState(() => busy = false);
                    }
                  },
            icon: const Icon(Icons.shopping_bag_outlined),
            label: Text(
              busy
                  ? 'Adding…'
                  : stock <= 0
                  ? 'Out of stock'
                  : 'Add to bag · ${money((double.tryParse('$price') ?? 0) * quantity)}',
            ),
          ),
        ),
      ),
    );
  }
}
