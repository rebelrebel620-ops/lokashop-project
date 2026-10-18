import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Holds the bearer token + cached user info issued by /api/login.
class Session {
  static const _secure = FlutterSecureStorage();
  bool suspended = false;
  static const _tokenKey = 'lokashop_token';
  static const _nameKey = 'lokashop_name';
  static const _emailKey = 'lokashop_email';

  String? token;
  String? name;
  String? email;

  bool get isLoggedIn => token != null && token!.isNotEmpty;

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    try {
      token = await _secure.read(key: _tokenKey);
    } catch (_) {
      token = null;
    }
    final legacy = prefs.getString(_tokenKey);
    await prefs.remove(_tokenKey);
    if (token == null && legacy != null) {
      try {
        await _secure.write(key: _tokenKey, value: legacy);
        token = legacy;
      } catch (_) {
        token = null;
      }
    }
    suspended = prefs.getBool('lokashop_suspended') ?? false;
    name = prefs.getString(_nameKey);
    email = prefs.getString(_emailKey);
  }

  Future<void> save({
    required String token,
    required String name,
    required String email,
    bool suspended = false,
  }) async {
    await _secure.write(key: _tokenKey, value: token);
    this.suspended = suspended;
    this.token = token;
    this.name = name;
    this.email = email;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('lokashop_suspended', suspended);
    await prefs.setString(_nameKey, name);
    await prefs.setString(_emailKey, email);
  }

  Future<void> clear() async {
    suspended = false;
    token = null;
    try {
      await _secure.delete(key: _tokenKey);
    } catch (_) {
      /* Server logout still revokes the bearer token. */
    }
    name = null;
    email = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    await prefs.remove('lokashop_suspended');
    await prefs.remove(_nameKey);
    await prefs.remove(_emailKey);
  }
}
