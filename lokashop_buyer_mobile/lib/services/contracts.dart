import 'dart:math';
import '../config.dart';

String requestKey() {
  final random = Random.secure();
  return List.generate(
    24,
    (_) => random.nextInt(256).toRadixString(16).padLeft(2, '0'),
  ).join();
}

String mediaUrl(String? raw) {
  if (raw == null || raw.isEmpty) return '';
  final base = Uri.parse(apiBaseUrl);
  final root = base.replace(
    path: base.path.replaceFirst(RegExp(r'/api/?$'), ''),
    query: null,
  );
  final uri = Uri.tryParse(raw);
  if (uri == null) return '';
  if (!uri.hasScheme) {
    return root
        .resolve(raw.startsWith('/') ? raw : '${root.path}/media/$raw')
        .toString();
  }
  if (!['http', 'https'].contains(uri.scheme)) return '';
  if (['localhost', '127.0.0.1', '10.0.2.2'].contains(uri.host)) {
    return Uri(
      scheme: base.scheme,
      host: base.host,
      port: base.hasPort ? base.port : null,
      path: uri.path,
      query: uri.hasQuery ? uri.query : null,
    ).toString();
  }
  return uri.toString();
}

double cartUnit(Map row) {
  final effective = double.tryParse('${row['effective_price']}');
  if (effective != null) return effective;
  final price = double.tryParse('${row['price']}') ?? 0,
      adjustment = double.tryParse('${row['price_adjustment']}') ?? 0,
      discount = double.tryParse('${row['discount_percent']}') ?? 0;
  return (price + adjustment) * (1 - discount / 100);
}
