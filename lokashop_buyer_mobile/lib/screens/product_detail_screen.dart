import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';
import '../services/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';
import 'cart_tab.dart';
import 'login_screen.dart';

class ProductDetailScreen extends StatefulWidget {
  final int productId;
  const ProductDetailScreen({super.key, required this.productId});

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  bool loading = true;
  Map? product;
  List variations = [];
  String? sellerName;
  double ratingAvg = 0;
  int ratingCount = 0;
  int? selectedVariation;
  int quantity = 1;
  bool adding = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    try {
      final res = await context.read<AppState>().api.get('/products/${widget.productId}');
      setState(() {
        product = Map.from(res['product']);
        variations = List.from(res['variations'] ?? []);
        sellerName = res['seller_name'];
        ratingAvg = double.tryParse('${res['rating_avg']}') ?? 0;
        ratingCount = int.tryParse('${res['rating_count']}') ?? 0;
        if (variations.isNotEmpty) selectedVariation = variations.first['id'];
      });
    } catch (e) {
      if (mounted) AppSnack.show(context, 'Could not load this product.', error: true);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  int get _stock {
    if (selectedVariation != null) {
      final v = variations.firstWhere((v) => v['id'] == selectedVariation, orElse: () => null);
      if (v != null) return int.tryParse('${v['stock']}') ?? 0;
    }
    return int.tryParse('${product?['stock']}') ?? 0;
  }

  double get _unitPrice {
    final price = double.tryParse('${product?['price']}') ?? 0;
    final discount = double.tryParse('${product?['discount_percent']}') ?? 0;
    double adj = 0;
    if (selectedVariation != null) {
      final v = variations.firstWhere((v) => v['id'] == selectedVariation, orElse: () => null);
      if (v != null) adj = double.tryParse('${v['price_adjustment']}') ?? 0;
    }
    return (price + adj) * (1 - discount / 100);
  }

  Future<void> _addToCart() async {
    final app = context.read<AppState>();
    if (!app.isLoggedIn) {
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LoginScreen()));
      return;
    }
    if (_stock <= 0) return;
    setState(() => adding = true);
    try {
      await app.addToCart(product!['id'], quantity, variationId: selectedVariation);
      if (!mounted) return;
      AppSnack.show(context, 'Added to cart.');
    } on ApiException catch (e) {
      if (mounted) AppSnack.show(context, e.message, error: true);
    } catch (e) {
      if (mounted) AppSnack.show(context, 'Could not add to cart.', error: true);
    } finally {
      if (mounted) setState(() => adding = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator(color: AppColors.primary)));
    }
    if (product == null) {
      return Scaffold(appBar: AppBar(), body: const EmptyState(icon: Icons.error_outline_rounded, title: 'Not found', subtitle: 'This product is unavailable.'));
    }

    final price = double.tryParse('${product!['price']}') ?? 0;
    final discount = double.tryParse('${product!['discount_percent']}') ?? 0;
    final outOfStock = _stock <= 0;

    return Scaffold(
      body: CustomScrollView(
        slivers: [
          SliverAppBar(
            pinned: true,
            backgroundColor: AppColors.bg,
            foregroundColor: AppColors.ink,
            expandedHeight: 260,
            flexibleSpace: FlexibleSpaceBar(
              background: ProductArt(seed: '${product!['id']}${product!['name']}', radius: BorderRadius.zero),
            ),
            actions: [
              IconButton(
                icon: const Icon(Icons.shopping_cart_outlined),
                onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const CartTab())),
              ),
            ],
          ),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 120),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('${product!['category'] ?? ''}', style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 12.5)),
                  const SizedBox(height: 6),
                  Text('${product!['name']}', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Text(money(_unitPrice), style: const TextStyle(color: AppColors.primary, fontSize: 22, fontWeight: FontWeight.w800)),
                      if (discount > 0) ...[
                        const SizedBox(width: 10),
                        Text(money(price), style: const TextStyle(color: AppColors.muted, decoration: TextDecoration.lineThrough)),
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(color: AppColors.danger.withOpacity(.1), borderRadius: BorderRadius.circular(20)),
                          child: Text('-${discount.toStringAsFixed(0)}%', style: const TextStyle(color: AppColors.danger, fontWeight: FontWeight.w700, fontSize: 12)),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      if (ratingCount > 0) ...[
                        const Icon(Icons.star_rounded, color: AppColors.accent, size: 20),
                        const SizedBox(width: 4),
                        Text('$ratingAvg', style: const TextStyle(fontWeight: FontWeight.w700)),
                        Text(' ($ratingCount reviews)', style: const TextStyle(color: AppColors.muted, fontSize: 13)),
                        const SizedBox(width: 14),
                      ],
                      const Icon(Icons.store_outlined, size: 18, color: AppColors.muted),
                      const SizedBox(width: 4),
                      Flexible(child: Text(sellerName ?? 'LokaShop seller', style: const TextStyle(color: AppColors.muted, fontSize: 13), overflow: TextOverflow.ellipsis)),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Icon(Icons.inventory_2_outlined, size: 18, color: outOfStock ? AppColors.danger : AppColors.muted),
                      const SizedBox(width: 4),
                      Text(outOfStock ? 'Out of stock' : '$_stock in stock', style: TextStyle(color: outOfStock ? AppColors.danger : AppColors.muted, fontSize: 13, fontWeight: FontWeight.w600)),
                    ],
                  ),
                  const Divider(height: 36),
                  if (variations.isNotEmpty) ...[
                    const Text('Options', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: variations.map<Widget>((v) {
                        final sel = selectedVariation == v['id'];
                        return ChoiceChip(
                          label: Text(v['name']),
                          selected: sel,
                          onSelected: (_) => setState(() => selectedVariation = v['id']),
                          showCheckmark: false,
                          selectedColor: AppColors.primary,
                          labelStyle: TextStyle(color: sel ? Colors.white : AppColors.ink, fontWeight: FontWeight.w700),
                        );
                      }).toList(),
                    ),
                    const Divider(height: 36),
                  ],
                  const Text('Description', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                  const SizedBox(height: 8),
                  Text(
                    (product!['description'] ?? '').toString().isEmpty ? 'No description provided.' : product!['description'],
                    style: const TextStyle(color: AppColors.muted, height: 1.5),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Container(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
          decoration: const BoxDecoration(color: AppColors.surface, border: Border(top: BorderSide(color: AppColors.line))),
          child: Row(
            children: [
              if (!outOfStock)
                Container(
                  decoration: BoxDecoration(borderRadius: BorderRadius.circular(12), border: Border.all(color: AppColors.line)),
                  child: Row(
                    children: [
                      IconButton(
                        onPressed: quantity > 1 ? () => setState(() => quantity--) : null,
                        icon: const Icon(Icons.remove_rounded),
                      ),
                      Text('$quantity', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                      IconButton(
                        onPressed: quantity < _stock ? () => setState(() => quantity++) : null,
                        icon: const Icon(Icons.add_rounded),
                      ),
                    ],
                  ),
                ),
              const SizedBox(width: 12),
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: (outOfStock || adding) ? null : _addToCart,
                  icon: adding
                      ? const SizedBox(height: 16, width: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.2))
                      : const Icon(Icons.shopping_cart_rounded, size: 20),
                  label: Text(outOfStock ? 'Out of stock' : 'Add to cart'),
                  style: ElevatedButton.styleFrom(backgroundColor: outOfStock ? AppColors.muted : AppColors.primary),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
