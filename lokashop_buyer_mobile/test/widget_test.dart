import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:lokashop_buyer/theme.dart';
import 'package:lokashop_buyer/services/app_state.dart';
import 'package:lokashop_buyer/services/api_service.dart';
import 'package:lokashop_buyer/services/session.dart';
import 'package:lokashop_buyer/screens/shop_tab.dart';
import 'package:lokashop_buyer/screens/checkout_screen.dart';
import 'package:lokashop_buyer/widgets/product_card.dart';

class FakeApi extends ApiService {
  FakeApi() : super(Session());
  Map<String, dynamic>? lastProducts, quoteFields;
  @override
  Future<dynamic> get(String path, {Map<String, dynamic>? query}) async {
    if (path == '/catalog/categories') {
      return {
        'main_categories': ['Pet Supplies', 'Electronics and Gadgets'],
        'subcategories': [
          {
            'id': 1,
            'name': 'Dog Food & Treats',
            'main_category': 'Pet Supplies',
          },
        ],
      };
    }
    if (path == '/products') {
      lastProducts = query;
      return {
        'current_page': 1,
        'last_page': 1,
        'data': [
          {
            'id': 1,
            'name': 'Local pet food',
            'price': 100,
            'stock': 5,
            'category': 'Dog food',
            'images': '[]',
          },
        ],
      };
    }
    if (path == '/addresses') {
      return [
        {
          'id': 1,
          'recipient': 'Demo Buyer',
          'phone': '09170000001',
          'street': 'Test Street',
          'barangay': 'Test Barangay',
          'city': 'Calamba',
          'province': 'Laguna',
        },
      ];
    }
    return [];
  }

  @override
  Future<dynamic> post(String path, [Map<String, dynamic>? data]) async {
    if (path == '/checkout/quote') {
      quoteFields = data;
      return {
        'merchandise_total': 100,
        'shipping_total': 65,
        'discount_total': 0,
        'total': 165,
        'orders': [],
      };
    }
    return {};
  }
}

class FakeApp extends AppState {
  final FakeApi fake = FakeApi();
  @override
  ApiService get api => fake;
}

Widget wrap(FakeApp app, Widget child) =>
    ChangeNotifierProvider<AppState>.value(
      value: app,
      child: MaterialApp(theme: AppTheme.light(), home: child),
    );
void main() {
  testWidgets('main category choice drives server filtering', (tester) async {
    final app = FakeApp();
    await tester.pumpWidget(wrap(app, const ShopTab()));
    await tester.pumpAndSettle();
    expect(find.text('Pet Supplies'), findsOneWidget);
    await tester.tap(find.text('Pet Supplies'));
    await tester.pumpAndSettle();
    expect(app.fake.lastProducts?['main_category'], 'Pet Supplies');
    expect(find.byType(DropdownButtonFormField<int>), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  testWidgets('checkout displays shipping-inclusive quote for selected rows', (
    tester,
  ) async {
    final app = FakeApp();
    app.session.token = 'test-token';
    await tester.pumpWidget(wrap(app, const CheckoutScreen(cartIds: [7])));
    await tester.pumpAndSettle();
    expect(app.fake.quoteFields?['cart_ids'], [7]);
    expect(find.textContaining('Place order'), findsOneWidget);
    expect(find.textContaining('165.00'), findsWidgets);
    expect(tester.takeException(), isNull);
  });
  for (final width in [360.0, 800.0]) {
    testWidgets('product card fits at width $width and larger text', (
      tester,
    ) async {
      tester.view.physicalSize = Size(width, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final app = FakeApp();
      await tester.pumpWidget(
        wrap(
          app,
          Scaffold(
            body: MediaQuery(
              data: MediaQueryData(
                size: Size(width, 800),
                textScaler: TextScaler.linear(1.2),
              ),
              child: Center(
                child: SizedBox(
                  width: 210,
                  height: 300,
                  child: ProductCard(
                    product: const {
                      'id': 1,
                      'name': 'A lovely everyday local find',
                      'price': '199',
                      'discount_percent': 10,
                      'stock': 4,
                      'images': '[]',
                    },
                    onTap: () {},
                  ),
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('A lovely everyday local find'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }
}
