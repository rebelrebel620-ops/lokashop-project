import 'dart:io';
import 'package:flutter/foundation.dart';
import 'api_service.dart';
import 'session.dart';

class AppState extends ChangeNotifier {
  final Session session = Session();
  late final ApiService api = ApiService(session);

  bool booting = true;
  List<dynamic> cart = [];

  bool get isLoggedIn => session.isLoggedIn;
  int get cartCount => cart.fold<int>(0, (sum, c) => sum + ((c['quantity'] ?? 0) as int));

  double get cartTotal {
    double sum = 0;
    for (final c in cart) {
      final price = double.tryParse('${c['price']}') ?? 0;
      final adj = double.tryParse('${c['price_adjustment'] ?? 0}') ?? 0;
      final discount = double.tryParse('${c['discount_percent'] ?? 0}') ?? 0;
      final unit = (price + adj) * (1 - discount / 100);
      sum += unit * ((c['quantity'] ?? 0) as int);
    }
    return sum;
  }

  Future<void> boot() async {
    await session.load();
    if (session.isLoggedIn) {
      try {
        await refreshCart();
      } catch (_) {
        // token might be stale; leave user logged out gracefully next action
      }
    }
    booting = false;
    notifyListeners();
  }

  Future<void> login(String email, String password) async {
    final res = await api.post('/login', {'email': email, 'password': password});
    await session.save(token: res['token'], name: res['user']['name'], email: res['user']['email']);
    await refreshCart();
    notifyListeners();
  }

  Future<String> register({
    required String name,
    required String phone,
    required String email,
    required String password,
    required String passwordConfirmation,
    required File idDocument,
  }) async {
    final res = await api.postMultipart('/register', {
      'name': name,
      'phone': phone,
      'email': email,
      'password': password,
      'password_confirmation': passwordConfirmation,
    }, file: idDocument);
    return res['message'] ?? 'Registration submitted.';
  }

  Future<void> logout() async {
    try {
      await api.post('/logout');
    } catch (_) {}
    await session.clear();
    cart = [];
    notifyListeners();
  }

  Future<void> refreshCart() async {
    final res = await api.get('/cart');
    cart = List<dynamic>.from(res);
    notifyListeners();
  }

  Future<void> addToCart(int productId, int quantity, {int? variationId}) async {
    final res = await api.post('/cart', {
      'product_id': productId,
      'quantity': quantity,
      if (variationId != null) 'variation_id': variationId,
    });
    cart = List<dynamic>.from(res);
    notifyListeners();
  }

  Future<void> updateCartQty(int cartItemId, int quantity) async {
    final res = await api.post('/cart/$cartItemId', {'quantity': quantity});
    cart = List<dynamic>.from(res);
    notifyListeners();
  }

  Future<void> removeFromCart(int cartItemId) async {
    final res = await api.delete('/cart/$cartItemId');
    cart = List<dynamic>.from(res);
    notifyListeners();
  }
}
