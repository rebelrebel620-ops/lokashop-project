import 'package:flutter/foundation.dart';
import 'api_service.dart';
import 'session.dart';

class AppState extends ChangeNotifier {
  final Session session = Session();
  late final ApiService api = ApiService(session);

  bool booting = true;
  Map<String, dynamic>? user;
  final Set<int> selectedCartIds = {};
  bool _selectionReady = false;
  List<dynamic> get selectedCart => cart
      .where((c) => selectedCartIds.contains(int.parse("${c['id']}")))
      .toList();
  void selectCart(int id, bool enabled) {
    if (enabled) {
      selectedCartIds.add(id);
    } else {
      selectedCartIds.remove(id);
    }
    notifyListeners();
  }

  Future<void> refreshMe() async {
    if (session.suspended) return;
    user = Map<String, dynamic>.from(await api.get('/me'));
    notifyListeners();
  }

  void _syncSelection() {
    final ids = cart.map((c) => int.parse("${c['id']}")).toSet();
    if (!_selectionReady) {
      selectedCartIds.addAll(ids);
      _selectionReady = true;
    }
    selectedCartIds.removeWhere((id) => !ids.contains(id));
  }

  List<dynamic> cart = [];

  bool get isLoggedIn => session.isLoggedIn;
  int get cartCount => cart.fold<int>(
    0,
    (sum, c) => sum + (int.tryParse('${c['quantity']}') ?? 0),
  );

  double get cartTotal => cart.fold<double>(
    0,
    (sum, c) => sum + cartUnit(c) * (int.tryParse('${c['quantity']}') ?? 0),
  );

  Future<void> boot() async {
    await session.load();
    api.onUnauthorized = () async {
      await session.clear();
      cart = [];
      user = null;
      notifyListeners();
    };
    if (session.isLoggedIn && !session.suspended) {
      try {
        await refreshMe();
        await refreshCart();
      } catch (_) {
        // token might be stale; leave user logged out gracefully next action
      }
    }
    booting = false;
    notifyListeners();
  }

  Future<void> login(String email, String password) async {
    final res = await api.post('/login', {
      'email': email,
      'password': password,
    });
    await session.save(
      token: res['token'],
      name: res['user']['name'],
      email: res['user']['email'],
      suspended: res['appeal_required'] == true,
    );
    user = Map<String, dynamic>.from(res['user']);
    if (!session.suspended) {
      try {
        await refreshCart();
      } catch (_) {}
    }
    notifyListeners();
  }

  Future<Map<String, dynamic>> register(Map<String, dynamic> fields) async {
    final res = await api.post('/register', fields);
    return Map<String, dynamic>.from(res as Map);
  }

  Future<void> logout() async {
    try {
      await api.post('/logout');
    } catch (_) {}
    await session.clear();
    cart = [];
    selectedCartIds.clear();
    _selectionReady = false;
    user = null;
    notifyListeners();
  }

  Future<void> refreshCart() async {
    final res = await api.get('/cart');
    cart = List<dynamic>.from(res);
    _syncSelection();
    notifyListeners();
  }

  Future<void> addToCart(
    int productId,
    int quantity, {
    int? variationId,
  }) async {
    final res = await api.post('/cart', {
      'product_id': productId,
      'quantity': quantity,
      if (variationId != null) 'variation_id': variationId,
    });
    cart = List<dynamic>.from(res);
    _syncSelection();
    selectedCartIds.addAll(
      cart
          .where((c) => c['product_id'] == productId)
          .map((c) => int.parse("${c['id']}")),
    );
    notifyListeners();
  }

  Future<void> updateCartQty(int cartItemId, int quantity) async {
    final res = await api.post('/cart/$cartItemId', {'quantity': quantity});
    cart = List<dynamic>.from(res);
    _syncSelection();
    notifyListeners();
  }

  Future<void> removeFromCart(int cartItemId) async {
    final res = await api.delete('/cart/$cartItemId');
    cart = List<dynamic>.from(res);
    _syncSelection();
    notifyListeners();
  }
}
