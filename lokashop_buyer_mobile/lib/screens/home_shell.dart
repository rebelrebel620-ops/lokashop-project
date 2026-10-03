import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import '../theme.dart';
import 'shop_tab.dart';
import 'cart_tab.dart';
import 'orders_tab.dart';
import 'profile_tab.dart';

class HomeShell extends StatefulWidget {
  const HomeShell({super.key});
  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  late final _tabs = [ShopTab(), OrdersTab(), CartTab(onBrowseProducts: () => setState(() => _index = 0)), ProfileTab()];

  @override
  Widget build(BuildContext context) {
    final cartCount = context.watch<AppState>().cartCount;

    return Scaffold(
      body: IndexedStack(index: _index, children: _tabs),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _index,
        onTap: (i) => setState(() => _index = i),
        items: [
          const BottomNavigationBarItem(icon: Icon(Icons.storefront_outlined), activeIcon: Icon(Icons.storefront_rounded), label: 'Shop'),
          const BottomNavigationBarItem(icon: Icon(Icons.receipt_long_outlined), activeIcon: Icon(Icons.receipt_long_rounded), label: 'Orders'),
          BottomNavigationBarItem(
            icon: Badge(
              label: Text('$cartCount'),
              isLabelVisible: cartCount > 0,
              backgroundColor: AppColors.accent,
              child: const Icon(Icons.shopping_cart_outlined),
            ),
            activeIcon: Badge(
              label: Text('$cartCount'),
              isLabelVisible: cartCount > 0,
              backgroundColor: AppColors.accent,
              child: const Icon(Icons.shopping_cart_rounded),
            ),
            label: 'Cart',
          ),
          const BottomNavigationBarItem(icon: Icon(Icons.person_outline_rounded), activeIcon: Icon(Icons.person_rounded), label: 'Profile'),
        ],
      ),
    );
  }
}
