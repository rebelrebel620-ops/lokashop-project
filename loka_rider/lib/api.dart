import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import 'models.dart';
import 'package:image_picker/image_picker.dart';

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
    if (!RegExp(r'^https?://', caseSensitive: false).hasMatch(u)) {
      u = 'http://$u';
    }
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
        res = await http
            .post(uri, headers: headers, body: jsonEncode(body ?? {}))
            .timeout(_timeout);
      } else {
        res = await http.get(uri, headers: headers).timeout(_timeout);
      }
    } on TimeoutException {
      throw const ApiException(
        'The server took too long to respond. Check your connection and the server address.',
      );
    } catch (_) {
      throw const ApiException(
        'Cannot reach the server. Check your internet connection and the server address.',
      );
    }

    Map<String, dynamic> data = {};
    bool validJson = false;
    try {
      final decoded = jsonDecode(utf8.decode(res.bodyBytes));
      if (decoded is Map<String, dynamic>) {
        data = decoded;
        validJson = true;
      }
    } catch (_) {
      // Non-JSON body (e.g. an HTML error page) - fall through to the generic message.
    }

    if (res.statusCode >= 200 && res.statusCode < 300) {
      if (!validJson) {
        throw const ApiException(
          'The server returned a web page instead of JSON. Check the logistics server address and install its API update.',
        );
      }
      return data;
    }

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
    if (status == 404) {
      return 'Not found. Is the server address correct and the API installed?';
    }
    if (status >= 500) return 'Server error ($status). Please try again.';
    return 'Request failed ($status).';
  }

  Future<Map<String, dynamic>> get(String path, [Map<String, String>? query]) =>
      _send('GET', path, query: query);
  Future<Map<String, dynamic>> post(String path, Map<String, dynamic> body) =>
      _send('POST', path, body: body);

  String mediaUrl(String raw) {
    final root = Uri.parse(normalizeUrl(baseUrl));
    final u = Uri.tryParse(raw);
    if (u == null ||
        (u.hasScheme && u.scheme != 'https' && u.scheme != 'http')) {
      return '';
    }
    if (!u.hasScheme) return root.resolveUri(u).toString();
    if (['localhost', '127.0.0.1', '10.0.2.2'].contains(u.host)) {
      return root
          .replace(
            path: u.path,
            query: u.hasQuery ? u.query : null,
            fragment: '',
          )
          .toString();
    }
    return u.toString();
  }

  Map<String, String> imageHeaders(String raw) {
    final image = Uri.tryParse(mediaUrl(raw));
    final root = Uri.parse(normalizeUrl(baseUrl));
    return token != null &&
            image != null &&
            image.hasScheme &&
            image.hasAuthority &&
            image.origin == root.origin
        ? {'Authorization': 'Bearer $token'}
        : {};
  }

  Future<Map<String, dynamic>> upload(
    String path,
    Map<String, String> fields,
    Map<String, XFile> files,
  ) async {
    final req = http.MultipartRequest('POST', _uri(path));
    req.headers.addAll({
      'Accept': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    });
    req.fields.addAll(fields);
    for (final entry in files.entries) {
      if (await entry.value.length() > 5 * 1024 * 1024) {
        throw const ApiException('Choose an image smaller than 5 MB.');
      }
      req.files.add(
        await http.MultipartFile.fromPath(entry.key, entry.value.path),
      );
    }
    http.Response res;
    try {
      res = await http.Response.fromStream(
        await req.send().timeout(const Duration(seconds: 45)),
      );
    } on TimeoutException {
      throw const ApiException(
        'Upload timed out. Check the connection and refresh before retrying.',
      );
    } catch (_) {
      throw const ApiException('Upload failed. Check your connection.');
    }
    Map<String, dynamic> data = {};
    try {
      final decoded = jsonDecode(res.body);
      if (decoded is Map) data = Map<String, dynamic>.from(decoded);
    } catch (_) {}
    if (res.statusCode >= 200 && res.statusCode < 300) return data;
    if (res.statusCode == 401) onUnauthorized?.call();
    throw ApiException(_messageOf(data, res.statusCode), res.statusCode);
  }

  // ------------------------------------------------------------------ calls

  Future<void> ping() async {
    final data = await _send('GET', '/api/ping', auth: false);
    if (data['ok'] != true) {
      throw const ApiException(
        'That address does not look like a LokaShop Logistics server.',
      );
    }
  }

  Future<({String token, Rider rider})> login(
    String email,
    String password,
  ) async {
    final data = await _send(
      'POST',
      '/api/rider/login',
      body: {'email': email, 'password': password},
      auth: false,
    );
    return (
      token: '${data['token']}',
      rider: Rider.fromJson(data['rider'] as Map<String, dynamic>),
    );
  }

  Future<({Rider rider, RiderStats stats})> me() async {
    final data = await _send('GET', '/api/rider/me');
    return (
      rider: Rider.fromJson(data['rider'] as Map<String, dynamic>),
      stats: RiderStats.fromJson(
        (data['stats'] as Map?)?.cast<String, dynamic>() ?? {},
      ),
    );
  }

  Future<Rider> updateProfile(String name, String phone) async {
    final data = await _send(
      'POST',
      '/api/rider/profile',
      body: {'name': name, 'phone': phone},
    );
    return Rider.fromJson(data['rider'] as Map<String, dynamic>);
  }

  /// [filter]: 'all' | 'active' | 'completed'
  Future<List<Assignment>> assignments({String filter = 'all'}) async {
    final data = await _send(
      'GET',
      '/api/rider/assignments',
      query: {'filter': filter},
    );
    final list = data['data'];
    return list is List
        ? list
              .whereType<Map<String, dynamic>>()
              .map(Assignment.fromJson)
              .toList()
        : [];
  }

  Future<ActionResult> accept(int assignmentId) async {
    final data = await _send(
      'POST',
      '/api/rider/assignments/$assignmentId/accept',
    );
    return _result(data);
  }

  Future<ActionResult> setStatus(
    int parcelId,
    String status, {
    String? reason,
  }) async {
    final data = await _send(
      'POST',
      '/api/rider/parcels/$parcelId/status',
      body: {
        'status': status,
        if (reason != null && reason.trim().isNotEmpty) 'reason': reason.trim(),
      },
    );
    return _result(data);
  }

  /// Sends the raw QR content; the server extracts the tracking code.
  Future<Assignment> scan(String raw) async {
    final data = await _send('POST', '/api/rider/scan', body: {'code': raw});
    return Assignment.fromJson(data['assignment'] as Map<String, dynamic>);
  }

  Future<EarningsSummary> earnings() async =>
      EarningsSummary.fromJson(await _send('GET', '/api/rider/earnings'));

  ActionResult _result(Map<String, dynamic> data) {
    final a = data['assignment'];
    return ActionResult(
      '${data['message'] ?? 'Done.'}',
      a is Map<String, dynamic> ? Assignment.fromJson(a) : null,
    );
  }
}
