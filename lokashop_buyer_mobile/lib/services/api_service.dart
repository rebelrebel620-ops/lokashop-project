import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'contracts.dart';
export 'contracts.dart';
import 'package:http/http.dart' as http;
import '../config.dart';
import 'session.dart';

class ApiException implements Exception {
  final String message;
  final Map<String, dynamic>? errors, data;
  final int? status;
  ApiException(this.message, {this.errors, this.data, this.status});
  factory ApiException.fromBody(int status, dynamic body) {
    final map = body is Map
        ? Map<String, dynamic>.from(body)
        : <String, dynamic>{};
    final errors = map['errors'] is Map
        ? Map<String, dynamic>.from(map['errors'])
        : null;
    String message = 'Request failed ($status).';
    if (errors != null && errors.isNotEmpty) {
      final first = errors.values.first;
      message = first is List && first.isNotEmpty ? '${first.first}' : '$first';
    } else if (map['message'] != null) {
      message = '${map['message']}';
    }
    return ApiException(message, errors: errors, data: map, status: status);
  }
  @override
  String toString() => message;
}

class ApiService {
  final Session session;
  final http.Client client;
  void Function()? onUnauthorized;
  ApiService(this.session, {http.Client? client})
    : client = client ?? http.Client();
  Uri _u(String path, [Map<String, dynamic>? query]) => Uri.parse(
    '${apiBaseUrl.replaceFirst(RegExp(r'/$'), '')}$path',
  ).replace(queryParameters: query?.map((k, v) => MapEntry(k, '$v')));
  Map<String, String> get headers => {
    'Accept': 'application/json',
    if (session.token != null) 'Authorization': 'Bearer ${session.token}',
  };
  Map<String, String> imageHeaders(String raw) {
    final u = Uri.parse(mediaUrl(raw)), base = Uri.parse(apiBaseUrl);
    return u.origin == base.origin ? headers : {};
  }

  dynamic _decode(http.Response response) {
    dynamic body = {};
    try {
      body = response.body.isEmpty ? {} : jsonDecode(response.body);
    } catch (_) {
      throw ApiException(
        'The server returned an unreadable response. Check the API address.',
        status: response.statusCode,
      );
    }
    if (response.statusCode >= 200 && response.statusCode < 300) return body;
    if (response.statusCode == 401) onUnauthorized?.call();
    throw ApiException.fromBody(response.statusCode, body);
  }

  Future<dynamic> _send(Future<http.Response> Function() send) async {
    try {
      return _decode(await send().timeout(const Duration(seconds: 30)));
    } on TimeoutException {
      throw ApiException(
        'Connection timed out. Check your connection and try again.',
      );
    } on SocketException {
      throw ApiException(
        'Cannot reach LokaShop. Check your internet or server address.',
      );
    } on http.ClientException {
      throw ApiException('Connection interrupted. Try again.');
    }
  }

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) =>
      _send(() => client.get(_u(path, query), headers: headers));
  Future<dynamic> post(String path, [Map<String, dynamic>? data]) => _send(
    () => client.post(
      _u(path),
      headers: {...headers, 'Content-Type': 'application/json'},
      body: jsonEncode(data ?? {}),
    ),
  );
  Future<dynamic> delete(String path) =>
      _send(() => client.delete(_u(path), headers: headers));
  Future<dynamic> postMultipart(
    String path,
    Map<String, String> fields, {
    File? file,
    String fileField = 'id_document',
    List<File> files = const [],
    String filesField = 'photos[]',
  }) async {
    final request = http.MultipartRequest('POST', _u(path));
    request.headers.addAll(headers);
    request.fields.addAll(fields);
    if (file != null) {
      request.files.add(
        await http.MultipartFile.fromPath(fileField, file.path),
      );
    }
    for (final image in files) {
      request.files.add(
        await http.MultipartFile.fromPath(filesField, image.path),
      );
    }
    return _send(
      () async => http.Response.fromStream(await client.send(request)),
    );
  }
}
