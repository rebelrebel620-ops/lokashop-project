import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/design.dart';
import '../widgets/product_card.dart';
import 'product_detail_screen.dart';
import 'notifications_screen.dart';
import 'buyer_features.dart';
import 'login_screen.dart';

class ShopTab extends StatefulWidget {
  const ShopTab({super.key});
  @override
  State<ShopTab> createState() => _ShopTabState();
}

class _ShopTabState extends State<ShopTab> {
  final search = TextEditingController();
  Timer? timer;
  List mains = [], subs = [], products = [];
  String? main;
  int? category;
  String sort = 'newest';
  int page = 1, last = 1, version = 0;
  bool loading = true, more = false;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load({bool append = false}) async {
    if (append && (more || loading || page >= last)) return;
    final request = append ? version : ++version;
    if (mounted) {
      setState(() {
        if (append) {
          more = true;
        } else {
          loading = true;
          error = null;
        }
      });
    }
    try {
      final api = context.read<AppState>().api;
      if (mains.isEmpty) {
        final tree = await api.get('/catalog/categories');
        mains = List.from(tree['main_categories']);
        subs = List.from(tree['subcategories']);
      }
      final result = await api.get(
        '/products',
        query: {
          'page': append ? page + 1 : 1,
          'sort': sort,
          if (search.text.trim().isNotEmpty) 'q': search.text.trim(),
          if (main != null) 'main_category': main,
          if (category != null) 'category': category,
        },
      );
      if (!mounted || request != version) return;
      setState(() {
        page = result['current_page'];
        last = result['last_page'];
        products = append
            ? [...products, ...result['data']]
            : List.from(result['data']);
      });
    } catch (e) {
      if (mounted && request == version) setState(() => error = '$e');
    } finally {
      if (mounted && request == version) {
        setState(() {
          loading = false;
          more = false;
        });
      }
    }
  }

  @override
  void dispose() {
    timer?.cancel();
    search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: DecoratedBox(
      decoration: const BoxDecoration(
        image: DecorationImage(
          image: AssetImage('assets/images/buyer-sage-background.png'),
          fit: BoxFit.cover,
          alignment: Alignment.topCenter,
        ),
      ),
      child: SafeArea(
      child: PageBody(
        child: RefreshIndicator(
          onRefresh: () => load(),
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(22, 16, 22, 0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Image.asset(
                            'assets/lokashop_logo.png',
                            width: 38,
                            height: 38,
                          ),
                          const SizedBox(width: 10),
                          const Expanded(
                            child: Text(
                              'LokaShop',
                              style: TextStyle(
                                fontSize: 23,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          if (!context.watch<AppState>().isLoggedIn)
                            TextButton(onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LoginScreen())), child: const Text('Sign in'))
                          else IconButton(
                            tooltip: 'Saved finds',
                            onPressed: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => const WishlistScreen(),
                              ),
                            ),
                            icon: const Icon(Icons.favorite_border),
                          ),
                          if (context.watch<AppState>().isLoggedIn) IconButton(
                            tooltip: 'Notifications',
                            onPressed: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => const NotificationsScreen(),
                              ),
                            ),
                            icon: const Icon(Icons.notifications_none),
                          ),
                        ],
                      ),
                      const SizedBox(height: 22),
                      LayoutBuilder(builder: (context, constraints) {
                        final wide = constraints.maxWidth >= 600;
                        return ClipRRect(
                          borderRadius: BorderRadius.circular(25),
                          child: DecoratedBox(
                            decoration: const BoxDecoration(
                              color: AppColors.primaryDark,
                              image: DecorationImage(
                                image: AssetImage('assets/images/buyer-shopping-banner.png'),
                                fit: BoxFit.cover,
                                alignment: Alignment.centerRight,
                              ),
                            ),
                            child: Container(
                              width: double.infinity,
                              constraints: BoxConstraints(minHeight: wide ? 220 : 195),
                              padding: const EdgeInsets.all(20),
                              decoration: const BoxDecoration(
                                gradient: LinearGradient(
                                  colors: [Color(0x990C3023), Color(0x550C3023), Colors.transparent],
                                  stops: [0, .58, 1],
                                ),
                              ),
                              alignment: Alignment.centerLeft,
                              child: SizedBox(
                                width: (constraints.maxWidth - 40) * (wide ? .57 : .64),
                                child: Column(
                                  mainAxisSize: MainAxisSize.min,
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const Text('GOOD FINDS. CLOSE TO HOME.',
                                      style: TextStyle(color: AppColors.accent, fontSize: 9, fontWeight: FontWeight.w800, letterSpacing: 1.2)),
                                    const SizedBox(height: 10),
                                    Text('A little local.\nA lot to love.',
                                      style: TextStyle(color: Colors.white, fontSize: wide ? 32 : 24, height: 1.15, fontWeight: FontWeight.w900)),
                                    const SizedBox(height: 10),
                                    const Text('Discover everyday essentials from trusted local sellers.',
                                      style: TextStyle(color: Color(0xFFE5EEE7), fontSize: 11, height: 1.45)),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        );
                      }),
                      const SizedBox(height: 20),
                      TextField(
                        controller: search,
                        onChanged: (_) {
                          timer?.cancel();
                          timer = Timer(
                            const Duration(milliseconds: 400),
                            () => load(),
                          );
                        },
                        decoration: const InputDecoration(
                          hintText: 'Find your next local favourite',
                          prefixIcon: Icon(Icons.search),
                        ),
                      ),
                      SectionHeading('Shop by category', trailing: TextButton(onPressed: () { main = null; category = null; load(); }, child: const Text('View all'))),
                    ],
                  ),
                ),
              ),
              SliverToBoxAdapter(
                child: SizedBox(
                  height: 78 + MediaQuery.textScalerOf(context).scale(11) * 2.4,
                  child: ListView.separated(
                    key: const PageStorageKey<String>('buyer-category-carousel'),
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 4),
                    itemCount: mains.length,
                    separatorBuilder: (_, __) => const SizedBox(width: 10),
                    itemBuilder: (context, i) {
                      final name = '${mains[i]}', selected = main == name;
                      const icons = [Icons.pets_outlined, Icons.devices_outlined, Icons.checkroom_outlined,
                        Icons.checkroom, Icons.child_care_outlined, Icons.home_outlined,
                        Icons.sports_soccer, Icons.spa_outlined, Icons.menu_book_outlined,
                        Icons.restaurant_outlined, Icons.chair_outlined, Icons.watch_outlined];
                      return SizedBox(
                        width: 112,
                        child: Semantics(
                          button: true,
                          selected: selected,
                          label: name,
                          child: Tooltip(
                            message: name,
                            excludeFromSemantics: true,
                            child: Material(
                              color: selected ? AppColors.primary : Colors.white,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(18),
                                side: BorderSide(color: selected ? AppColors.primary : AppColors.line),
                              ),
                              clipBehavior: Clip.antiAlias,
                              child: InkWell(
                                onTap: () {
                                  main = selected ? null : name;
                                  category = null;
                                  load();
                                },
                                child: Padding(
                                  padding: const EdgeInsets.all(10),
                                  child: Column(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      Icon(icons[i % icons.length], color: selected ? AppColors.accent : AppColors.primary, size: 26),
                                      const SizedBox(height: 8),
                                      Text(name, textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis,
                                        style: TextStyle(fontSize: 11, height: 1.2, fontWeight: FontWeight.w700, color: selected ? Colors.white : AppColors.ink)),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ),
              if (main != null)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(22, 14, 22, 0),
                    child: DropdownButtonFormField<int>(
                      initialValue: category,
                      key: ValueKey('$main:$category'),
                      isExpanded: true,
                      decoration: const InputDecoration(
                        labelText: 'Subcategory',
                      ),
                      items: [
                        const DropdownMenuItem<int>(
                          value: null,
                          child: Text('All products in this category'),
                        ),
                        ...subs
                            .where((s) => s['main_category'] == main)
                            .map(
                              (s) => DropdownMenuItem<int>(
                                value: s['id'],
                                child: Text(
                                  '${s['name']}',
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ),
                      ],
                      onChanged: (v) {
                        category = v;
                        load();
                      },
                    ),
                  ),
                ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 22),
                  child: SectionHeading(
                    main ?? 'Fresh local finds',
                    trailing: PopupMenuButton<String>(
                      tooltip: 'Sort products',
                      onSelected: (s) {
                        sort = s;
                        load();
                      },
                      itemBuilder: (_) => const [
                        PopupMenuItem(
                          value: 'newest',
                          child: Text('Newest first'),
                        ),
                        PopupMenuItem(
                          value: 'price_asc',
                          child: Text('Price: low to high'),
                        ),
                        PopupMenuItem(
                          value: 'price_desc',
                          child: Text('Price: high to low'),
                        ),
                        PopupMenuItem(value: 'name', child: Text('Name A–Z')),
                      ],
                      child: const Icon(Icons.tune),
                    ),
                  ),
                ),
              ),
              if (error != null)
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.all(22),
                    child: Column(
                      children: [
                        Text(error!),
                        TextButton(
                          onPressed: () => load(),
                          child: const Text('Try again'),
                        ),
                      ],
                    ),
                  ),
                ),
              if (loading)
                const SliverToBoxAdapter(
                  child: Padding(
                    padding: EdgeInsets.all(60),
                    child: Center(child: CircularProgressIndicator()),
                  ),
                )
              else if (products.isEmpty)
                const SliverToBoxAdapter(
                  child: EmptyState(
                    icon: Icons.search_off,
                    title: 'No finds just yet',
                    subtitle: 'Try another category or search.',
                  ),
                )
              else
                SliverPadding(
                  padding: const EdgeInsets.symmetric(horizontal: 22),
                  sliver: SliverLayoutBuilder(
                    builder: (ctx, constraints) => SliverGrid(
                      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: constraints.crossAxisExtent > 700
                            ? 4
                            : constraints.crossAxisExtent > 480
                            ? 3
                            : 2,
                        mainAxisExtent: 290,
                        crossAxisSpacing: 14,
                        mainAxisSpacing: 14,
                      ),
                      delegate: SliverChildBuilderDelegate(
                        (ctx, i) => ProductCard(
                          product: products[i],
                          onTap: () => Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => ProductDetailScreen(
                                productId: products[i]['id'],
                              ),
                            ),
                          ),
                        ),
                        childCount: products.length,
                      ),
                    ),
                  ),
                ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.all(22),
                  child: page < last
                      ? OutlinedButton(
                          onPressed: more ? null : () => load(append: true),
                          child: Text(more ? 'Loading…' : 'Discover more'),
                        )
                      : const SizedBox(height: 12),
                ),
              ),
            ],
          ),
        ),
      ),
    ),
    ),
  );
}
