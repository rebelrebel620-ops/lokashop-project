import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import 'shop_tab.dart';
import 'orders_tab.dart';
import 'cart_tab.dart';
import 'profile_tab.dart';
import 'messages_screen.dart';
import 'buyer_features.dart';

class HomeShell extends StatefulWidget {
  final int initialIndex;
  const HomeShell({super.key, this.initialIndex = 0});
  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  late int index = widget.initialIndex;
  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    if (app.session.suspended) return const AppealScreen();
    final pages = [
      const ShopTab(),
      const OrdersTab(),
      CartTab(onBrowseProducts: () => setState(() => index = 0)),
      const MessagesScreen(),
      const ProfileTab(),
    ];
    return LayoutBuilder(
      builder: (ctx, size) {
        final wide = size.maxWidth >= 850;
        return Scaffold(
          body: Row(
            children: [
              if (wide)
                NavigationRail(
                  selectedIndex: index,
                  onDestinationSelected: (i) => setState(() => index = i),
                  labelType: NavigationRailLabelType.all,
                  destinations: const [
                    NavigationRailDestination(
                      icon: Icon(Icons.storefront_outlined),
                      label: Text('Discover'),
                    ),
                    NavigationRailDestination(
                      icon: Icon(Icons.receipt_long_outlined),
                      label: Text('Orders'),
                    ),
                    NavigationRailDestination(
                      icon: Icon(Icons.shopping_bag_outlined),
                      label: Text('Bag'),
                    ),
                    NavigationRailDestination(
                      icon: Icon(Icons.forum_outlined),
                      label: Text('Messages'),
                    ),
                    NavigationRailDestination(
                      icon: Icon(Icons.person_outline),
                      label: Text('Account'),
                    ),
                  ],
                ),
              Expanded(child: pages[index]),
            ],
          ),
          bottomNavigationBar: wide
              ? null
              : NavigationBar(
                  selectedIndex: index,
                  onDestinationSelected: (i) => setState(() => index = i),
                  destinations: [
                    const NavigationDestination(
                      icon: Icon(Icons.storefront_outlined),
                      label: 'Discover',
                    ),
                    const NavigationDestination(
                      icon: Icon(Icons.receipt_long_outlined),
                      label: 'Orders',
                    ),
                    NavigationDestination(
                      icon: Badge(
                        isLabelVisible: app.cartCount > 0,
                        label: Text('${app.cartCount}'),
                        child: const Icon(Icons.shopping_bag_outlined),
                      ),
                      label: 'Bag',
                    ),
                    const NavigationDestination(
                      icon: Icon(Icons.forum_outlined),
                      label: 'Messages',
                    ),
                    const NavigationDestination(
                      icon: Icon(Icons.person_outline),
                      label: 'Account',
                    ),
                  ],
                ),
        );
      },
    );
  }
}
