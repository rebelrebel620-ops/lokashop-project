import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/product_card.dart';
import 'product_detail_screen.dart';

class ShopTab extends StatefulWidget {
  const ShopTab({super.key});
  @override
  State<ShopTab> createState() => _ShopTabState();
}

class _ShopTabState extends State<ShopTab> {
  final _searchCtrl = TextEditingController();
  final _scrollCtrl = ScrollController();
  Timer? _debounce;

  List categories = [];
  int? selectedCategory;
  List products = [];
  int page = 1;
  bool hasMore = true;
  bool loading = true;
  bool loadingMore = false;
  String query = '';

  @override
  void initState() {
    super.initState();
    _load(reset: true);
    _scrollCtrl.addListener(() {
      if (_scrollCtrl.position.pixels > _scrollCtrl.position.maxScrollExtent - 300) {
        _loadMore();
      }
    });
  }

  Future<void> _load({bool reset = false}) async {
    final app = context.read<AppState>();
    if (reset) {
      setState(() {
        loading = true;
        page = 1;
        hasMore = true;
        products = [];
      });
    }
    try {
      if (categories.isEmpty) {
        final cats = await app.api.get('/categories');
        categories = List.from(cats);
      }
      final res = await app.api.get('/products', query: {
        'page': page,
        if (query.isNotEmpty) 'q': query,
        if (selectedCategory != null) 'category': selectedCategory,
      });
      final data = List.from(res['data'] ?? []);
      setState(() {
        products = data;
        hasMore = (res['current_page'] ?? 1) < (res['last_page'] ?? 1);
      });
    } catch (e) {
      if (mounted) AppSnack.show(context, 'Could not load products.', error: true);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _loadMore() async {
    if (loadingMore || !hasMore) return;
    setState(() => loadingMore = true);
    try {
      final app = context.read<AppState>();
      final next = page + 1;
      final res = await app.api.get('/products', query: {
        'page': next,
        if (query.isNotEmpty) 'q': query,
        if (selectedCategory != null) 'category': selectedCategory,
      });
      final data = List.from(res['data'] ?? []);
      setState(() {
        page = next;
        products.addAll(data);
        hasMore = (res['current_page'] ?? 1) < (res['last_page'] ?? 1);
      });
    } catch (_) {
    } finally {
      if (mounted) setState(() => loadingMore = false);
    }
  }

  void _onSearchChanged(String v) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () {
      query = v.trim();
      _load(reset: true);
    });
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: RefreshIndicator(
        onRefresh: () => _load(reset: true),
        color: AppColors.primary,
        child: CustomScrollView(
          controller: _scrollCtrl,
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 16, 20, 6),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('LokaShop', style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800)),
                    const Text('Discover goods from local sellers', style: TextStyle(color: AppColors.muted)),
                    const SizedBox(height: 16),
                    TextField(
                      controller: _searchCtrl,
                      onChanged: _onSearchChanged,
                      decoration: InputDecoration(
                        hintText: 'Search products…',
                        prefixIcon: const Icon(Icons.search_rounded),
                        suffixIcon: _searchCtrl.text.isEmpty
                            ? null
                            : IconButton(
                                icon: const Icon(Icons.close_rounded),
                                onPressed: () {
                                  _searchCtrl.clear();
                                  query = '';
                                  _load(reset: true);
                                },
                              ),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(30), borderSide: BorderSide.none),
                        filled: true,
                        fillColor: AppColors.surface,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            if (categories.isNotEmpty)
              SliverToBoxAdapter(
                child: SizedBox(
                  height: 44,
                  child: ListView(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    children: [
                      _CategoryChip(
                        label: 'All',
                        selected: selectedCategory == null,
                        onTap: () {
                          selectedCategory = null;
                          _load(reset: true);
                        },
                      ),
                      const SizedBox(width: 8),
                      ...categories.map((c) => Padding(
                            padding: const EdgeInsets.only(right: 8),
                            child: _CategoryChip(
                              label: c['name'],
                              selected: selectedCategory == c['id'],
                              onTap: () {
                                selectedCategory = c['id'];
                                _load(reset: true);
                              },
                            ),
                          )),
                    ],
                  ),
                ),
              ),
            const SliverToBoxAdapter(child: SizedBox(height: 12)),
            if (loading)
              SliverPadding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                sliver: SliverGrid(
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2, mainAxisSpacing: 14, crossAxisSpacing: 14, childAspectRatio: .68),
                  delegate: SliverChildBuilderDelegate((c, i) => const ShimmerBlock(height: 220), childCount: 6),
                ),
              )
            else if (products.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: EmptyState(
                  icon: Icons.search_off_rounded,
                  title: 'No products found',
                  subtitle: 'Try a different search or category.',
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
                sliver: SliverGrid(
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2, mainAxisSpacing: 14, crossAxisSpacing: 14, childAspectRatio: .68),
                  delegate: SliverChildBuilderDelegate(
                    (c, i) => ProductCard(
                      product: products[i],
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => ProductDetailScreen(productId: products[i]['id'])),
                      ),
                    ),
                    childCount: products.length,
                  ),
                ),
              ),
            if (loadingMore)
              const SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.all(20),
                  child: Center(child: CircularProgressIndicator(color: AppColors.primary, strokeWidth: 2.4)),
                ),
              ),
            const SliverToBoxAdapter(child: SizedBox(height: 20)),
          ],
        ),
      ),
    );
  }
}

class _CategoryChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;
  const _CategoryChip({required this.label, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => onTap(),
      showCheckmark: false,
      labelStyle: TextStyle(color: selected ? Colors.white : AppColors.ink, fontWeight: FontWeight.w700, fontSize: 13),
      selectedColor: AppColors.primary,
      backgroundColor: AppColors.surface,
      side: BorderSide(color: selected ? AppColors.primary : AppColors.line),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(30)),
    );
  }
}
