const centerModules = {
  'overview',
  'pickups',
  'deliveries',
  'transfers',
  'areas',
  'couriers',
  'courier',
  'applications',
  'appeals',
  'rates',
  'cod',
  'returns',
  'tracking',
  'reports',
  'finance',
  'notifications',
  'address',
};
String centerPath(String module) {
  if (!centerModules.contains(module)) {
    throw ArgumentError('Unknown sorting-center section');
  }
  return '/api/center/$module';
}

Map<String, String> centerMultipart(Map<String, dynamic> fields) {
  final out = <String, String>{};
  for (final entry in fields.entries) {
    if (entry.value is List) {
      final rows = entry.value as List;
      for (var i = 0; i < rows.length; i++) {
        out['${entry.key}[$i]'] = '${rows[i]}';
      }
    } else if (entry.value is bool) {
      out[entry.key] = entry.value == true ? '1' : '0';
    } else if (entry.value != null) {
      out[entry.key] = '${entry.value}';
    }
  }
  return out;
}
