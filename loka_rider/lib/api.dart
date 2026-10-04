import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import 'models.dart';

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  const ApiException(this.message, [this.statusCode]);

  @override
  String toString() => message;
}

/// Thin client for the Laravel rider API (routes/api.php in lokashop-logistics).
class ApiClient {
  ApiClient({required this.baseUrl, this.token, this.onUnauthorized});

  String baseUrl;
  String? token;

  /// Called when an authenticated request comes back 401 (token expired / account disabled).
  void Function()? onUnauthorized;

  static const _timeout = Duration(seconds: 20);

  /// Adds http:// when missing and strips trailing slashes.
  static String normalizeUrl(String input) {
    var u = input.trim();
    if (u.isEmpty) return u;
    if (!RegExp(r'^https?://', caseSensitive: false).hasMatch(u)) u = 'http://$u';
    while (u.endsWith('/')) {
      u = u.substring(0, u.length - 1);
    }
    return u;
  }

  Uri _uri(String path, [Map<String, String>? query]) {
    final uri = Uri.parse('${normalizeUrl(baseUrl)}$path');
    return query == null ? uri : uri.replace(queryParameters: query);
  }

  Future<Map<String, dynamic>> _send(
    String method,
    String path, {
    Map<String, dynamic>? body,
    Map<String, String>? query,
    bool auth = true,
  }) async {
    final uri = _uri(path, query);
    final headers = <String, String>{
      'Accept': 'application/json',
      if (method == 'POST') 'Content-Type': 'application/json',
      if (auth && token != null) 'Authorization': 'Bearer $token',
    };

    http.Response res;
    try {
      if (method == 'POST') {
        res = await http.post(uri, headers: headers, body: jsonEncode(body ?? {})).timeout(_timeout);
      } else {
        res = await http.get(uri, headers: headers).timeout(_timeout);
      }
    } on TimeoutException {
      throw const ApiException('The server took too long to respond. Check your connection and the server address.');
    } catch (_) {
      throw const ApiException('Cannot reach the server. Check your internet connection and the server address.');
    }

    Map<String, dynamic> data = {};
    try {
      final decoded = jsonDecode(utf8.decode(res.bodyBytes));
      if (decoded is Map<String, dynamic>) data = decoded;
    } catch (_) {
      // Non-JSON body (e.g. an HTML error page) - fall through to the generic message.
    }

    if (res.statusCode >= 200 && res.statusCode < 300) return data;

    if (res.statusCode == 401 && auth) onUnauthorized?.call();
    throw ApiException(_messageOf(data, res.statusCode), res.statusCode);
  }

  String _messageOf(Map<String, dynamic> data, int status) {
    final errors = data['errors'];
    if (errors is Map && errors.isNotEmpty) {
      final first = errors.values.first;
      if (first is List && first.isNotEmpty) return '${first.first}';
      if (first is String) return first;
    }
    final msg = data['message'];
    if (msg is String && msg.isNotEmpty) return msg;
    if (status == 404) return 'Not found. Is the server address correct and the API installed?';
    if (status >= 500) return 'Server error ($status). Please try again.';
    return 'Request failed ($status).';
  }

  // ------------------------------------------------------------------ calls

  Future<void> ping() async {
    final data = await _send('GET', '/api/ping', auth: false);
    if (data['ok'] != true) throw const ApiException('That address does not look like a LokaShop Logistics server.');
  }

  Future<({String token, Rider rider})> login(String email, String password) async {
    final data = await _send('POST', '/api/rider/login', body: {'email': email, 'password': password}, auth: false);
    return (token: '${data['token']}', rider: Rider.fromJson(data['rider'] as Map<String, dynamic>));
  }

  Future<({Rider rider, RiderStats stats})> me() async {
    final data = await _send('GET', '/api/rider/me');
    return (
      rider: Rider.fromJson(data['rider'] as Map<String, dynamic>),
      stats: RiderStats.fromJson((data['stats'] as Map?)?.cast<String, dynamic>() ?? {}),
    );
  }

  Future<Rider> updateProfile(String name, String phone) async {
    final data = await _send('POST', '/api/rider/profile', body: {'name': name, 'phone': phone});
    return Rider.fromJson(data['rider'] as Map<String, dynamic>);
  }

  /// [filter]: 'all' | 'active' | 'completed'
  Future<List<Assignment>> assignments({String filter = 'all'}) async {
    final data = await _send('GET', '/api/rider/assignments', query: {'filter': filter});
    final list = data['data'];
    return list is List ? list.whereType<Map<String, dynamic>>().map(Assignment.fromJson).toList() : [];
  }

  Future<ActionResult> accept(int assignmentId) async {
    final data = await _send('POST', '/api/rider/assignments/$assignmentId/accept');
    return _result(data);
  }

  Future<ActionResult> setStatus(int parcelId, String status, {String? reason}) async {
    final data = await _send('POST', '/api/rider/parcels/$parcelId/status', body: {
      'status': status,
      if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
    });
    return _result(data);
  }

  /// Sends the raw QR content; the server extracts the tracking code.
  Future<Assignment> scan(String raw) async {
    final data = await _send('POST', '/api/rider/scan', body: {'code': raw});
    return Assignment.fromJson(data['assignment'] as Map<String, dynamic>);
  }

  Future<EarningsSummary> earnings() async => EarningsSummary.fromJson(await _send('GET', '/api/rider/earnings'));

  ActionResult _result(Map<String, dynamic> data) {
    final a = data['assignment'];
    return ActionResult('${data['message'] ?? 'Done.'}', a is Map<String, dynamic> ? Assignment.fromJson(a) : null);
  }
}
