import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import '../config.dart';
import 'session.dart';

class ApiException implements Exception {
  final String message;
  final Map<String, dynamic>? errors;
  ApiException(this.message, {this.errors});

  /// Flattens Laravel's `{"errors": {"field": ["msg"]}}` validation shape
  /// into one readable string.
  factory ApiException.fromBody(int status, dynamic body) {
    if (body is Map && body['errors'] is Map) {
      final errors = Map<String, dynamic>.from(body['errors']);
      final first = errors.values.first;
      final msg = first is List ? first.first.toString() : first.toString();
      return ApiException(msg, errors: errors);
    }
    if (body is Map && body['message'] != null) {
      return ApiException(body['message'].toString());
    }
    return ApiException('Something went wrong (HTTP $status).');
  }
}

class ApiService {
  final Session session;
  ApiService(this.session);

  Uri _u(String path, [Map<String, dynamic>? query]) {
    final uri = Uri.parse('$apiBaseUrl$path');
    if (query == null || query.isEmpty) return uri;
    return uri.replace(queryParameters: {
      ...uri.queryParameters,
      ...query.map((k, v) => MapEntry(k, v.toString())),
    });
  }

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (session.token != null) 'Authorization': 'Bearer ${session.token}',
      };

  dynamic _decode(http.Response res) {
    final body = res.body.isEmpty ? {} : jsonDecode(res.body);
    if (res.statusCode >= 200 && res.statusCode < 300) return body;
    throw ApiException.fromBody(res.statusCode, body);
  }

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) async {
    final res = await http.get(_u(path, query), headers: _headers).timeout(const Duration(seconds: 20));
    return _decode(res);
  }

  Future<dynamic> post(String path, [Map<String, dynamic>? data]) async {
    final res = await http
        .post(_u(path), headers: _headers, body: jsonEncode(data ?? {}))
        .timeout(const Duration(seconds: 20));
    return _decode(res);
  }

  Future<dynamic> delete(String path) async {
    final res = await http.delete(_u(path), headers: _headers).timeout(const Duration(seconds: 20));
    return _decode(res);
  }

  /// multipart POST, used for registration (ID document upload).
  Future<dynamic> postMultipart(String path, Map<String, String> fields, {File? file, String fileField = 'id_document'}) async {
    final req = http.MultipartRequest('POST', _u(path));
    req.headers['Accept'] = 'application/json';
    if (session.token != null) req.headers['Authorization'] = 'Bearer ${session.token}';
    req.fields.addAll(fields);
    if (file != null) {
      req.files.add(await http.MultipartFile.fromPath(fileField, file.path));
    }
    final streamed = await req.send().timeout(const Duration(seconds: 30));
    final res = await http.Response.fromStream(streamed);
    return _decode(res);
  }
}
