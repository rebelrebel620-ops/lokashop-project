import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api.dart';
import 'models.dart';

/// Holds the logged-in rider, the saved server address and the API token.
class Session extends ChangeNotifier {
  /// Android emulator -> your PC's localhost. Override at build time with
  ///   flutter run --dart-define=API_BASE_URL=https://your-domain
  /// or just change it on the login screen ("Server" section).
  static const defaultServerUrl = String.fromEnvironment('API_BASE_URL', defaultValue: 'http://10.0.2.2:8001');

  static const _kUrl = 'server_url';
  static const _kToken = 'token';
  static const _kRider = 'rider';

  final ApiClient api = ApiClient(baseUrl: defaultServerUrl);

  /// Set by the app so any open dialogs/sheets are closed when the session ends.
  VoidCallback? onSignedOut;

  SharedPreferences? _prefs;
  bool ready = false;
  Rider? rider;

  bool get loggedIn => rider != null;
  String get serverUrl => api.baseUrl;

  Session() {
    api.onUnauthorized = _sessionExpired;
  }

  Future<void> init() async {
    final prefs = _prefs = await SharedPreferences.getInstance();
    api.baseUrl = prefs.getString(_kUrl) ?? defaultServerUrl;
    api.token = prefs.getString(_kToken);

    if (api.token != null) {
      try {
        rider = (await api.me()).rider;
        await _saveRider();
      } on ApiException catch (e) {
        if (e.statusCode == 401) {
          await _clear();
        } else {
          // Offline at startup: keep the rider signed in from the cached profile.
          rider = _cachedRider();
          if (rider == null) await _clear();
        }
      }
    }
    ready = true;
    notifyListeners();
  }

  Future<void> login(String serverUrl, String email, String password) async {
    api.baseUrl = ApiClient.normalizeUrl(serverUrl);
    final result = await api.login(email.trim(), password);
    api.token = result.token;
    rider = result.rider;
    await _prefs?.setString(_kUrl, api.baseUrl);
    await _prefs?.setString(_kToken, result.token);
    await _saveRider();
    notifyListeners();
  }

  Future<void> logout() async {
    await _clear();
    onSignedOut?.call();
    notifyListeners();
  }

  void updateRider(Rider updated) {
    rider = updated;
    _saveRider();
    notifyListeners();
  }

  void _sessionExpired() {
    if (rider == null && api.token == null) return;
    _clear().then((_) {
      onSignedOut?.call();
      notifyListeners();
    });
  }

  Future<void> _clear() async {
    api.token = null;
    rider = null;
    await _prefs?.remove(_kToken);
    await _prefs?.remove(_kRider);
  }

  Future<void> _saveRider() async {
    final r = rider;
    if (r != null) await _prefs?.setString(_kRider, jsonEncode(r.toJson()));
  }

  Rider? _cachedRider() {
    final raw = _prefs?.getString(_kRider);
    if (raw == null) return null;
    try {
      return Rider.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }
}
