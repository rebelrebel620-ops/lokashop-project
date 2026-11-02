import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import '../services/contracts.dart';
import '../widgets/design.dart';
import '../widgets/common.dart';
import '../theme.dart';
import '../widgets/customer_reviews.dart';
import 'buyer_features.dart';
import 'login_screen.dart';
import 'checkout_screen.dart';
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
  int quantity = 1, photoIndex = 0;
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
    final variantPhoto = mediaUrl('${variant?['image_url'] ?? variant?['image'] ?? ''}');
    final photos = variantPhoto.isNotEmpty ? [variantPhoto] : List.from(p['image_urls'] as List? ?? []);
    final price =
        variant?['effective_price'] ?? p['effective_price'] ?? p['price'];
    final productStock = int.tryParse('${p['stock']}') ?? 0;
    final variantStock = int.tryParse('${variant?['stock'] ?? p['stock']}') ?? 0;
    final stock = productStock < variantStock ? productStock : variantStock;
    final vacation = data!['store_on_vacation'] == true;
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
                      key: ValueKey(variant?['id'] ?? 0),
                      onPageChanged: (index) => setState(() => photoIndex = index),
                      children: photos
                          .map((url) => AppPhoto('$url', fit: BoxFit.contain))
                          .toList(),
                    ),
            ),
            if (photos.isNotEmpty) Center(child: Text('${photoIndex + 1} / ${photos.length} · Swipe to see photos')),
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
            const SizedBox(height: 12),
            Row(children: [const Icon(Icons.star_rounded, size: 19, color: Color(0xFFC58C20)), const SizedBox(width: 5),
              Text('${data!['rating_avg'] ?? 0} · ${data!['rating_count'] ?? 0} ratings', style: const TextStyle(color: AppColors.muted, fontSize: 12))]),
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
                Text('${data!['review_summary']?['sold'] ?? 0} sold', style: const TextStyle(fontSize: 12)),
              ],
            ),
            const SizedBox(height: 20),
            if (variations.isNotEmpty)
              DropdownButtonFormField<int>(
                initialValue: variant?['id'] ?? 0,
                isExpanded: true,
                decoration: const InputDecoration(
                  labelText: 'Choose a variation',
                ),
                items: [
                  const DropdownMenuItem<int>(value: 0, child: Text('Original product')),
                  ...variations.map(
                      (v) => DropdownMenuItem<int>(
                        value: v['id'],
                        enabled: productStock > 0 && (int.tryParse('${v['stock']}') ?? 0) > 0,
                        child: Text('${v['name']} · ${money(v['effective_price'] ?? p['effective_price'] ?? p['price'])} · ${v['stock']} left', maxLines: 2, overflow: TextOverflow.ellipsis),
                      ),
                    ),
                ],
                onChanged: busy ? null : (id) => setState(() {
                  photoIndex = 0;
                  variant = id == null || id == 0 ? null : Map.from(variations.firstWhere((v) => v['id'] == id));
                  final available = int.tryParse('${variant?['stock'] ?? p['stock']}') ?? 0;
                  final max = available < productStock ? available : productStock;
                  if (quantity > max) quantity = max > 0 ? max : 1;
                }),
              ),
            if (variant?['option_values'] is Map) Padding(
              padding: const EdgeInsets.only(top: 10),
              child: Wrap(spacing: 8, runSpacing: 6, children: (variant!['option_values'] as Map).entries.map<Widget>((entry) => Chip(label: Text('${entry.key}: ${entry.value}'))).toList()),
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
                    title: Text('${data!['store_name'] ?? data!['seller_name'] ?? 'Local store'}'),
                    subtitle: Text('${data!['seller_person_name'] ?? 'Seller'} · View store'),
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
            CustomerReviews(productId: widget.productId, initial: data!),
            const SizedBox(height: 30),
          ],
        ),
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            OutlinedButton.icon(
              onPressed: busy || vacation || stock <= 0 || quantity > stock ? null : () async {
                if (!await signed()) return;
                if (!context.mounted) return;
                setState(() => busy = true);
                try {
                  final app = context.read<AppState>();
                  final result = await app.api.post('/cart/buy-now', {'product_id': widget.productId, 'quantity': quantity,
                    if (variant != null) 'variation_id': variant!['id'], '_request_key': requestKey()});
                  await app.refreshCart();
                  if (!context.mounted) return;
                  await Navigator.push(context, MaterialPageRoute(builder: (_) => CheckoutScreen(cartIds: [int.parse('${result['cart_id']}')])));
                } catch (e) { if (context.mounted) AppSnack.show(context, '$e', error: true); }
                finally { if (mounted) setState(() => busy = false); }
              },
              icon: const Icon(Icons.bolt_outlined), label: const Text('Buy now'),
            ),
            const SizedBox(height: 8),
            FilledButton.icon(
            onPressed:
                busy || vacation || stock <= 0 || quantity > stock
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
                  : vacation
                  ? 'Store is on vacation'
                  : stock <= 0
                  ? 'Out of stock'
                  : 'Add to bag · ${money((double.tryParse('$price') ?? 0) * quantity)}',
            ),
          ),
          ]),
        ),
      ),
    );
  }
}
