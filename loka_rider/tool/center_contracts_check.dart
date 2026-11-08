// ignore_for_file: avoid_print
import 'package:lokashop_rider/screens/center/center_contracts.dart';

void main() {
  var checks = 0;
  void check(bool value) {
    if (!value) throw StateError('Native center contract failed');
    checks++;
  }

  for (final section in [
    'overview',
    'pickups',
    'deliveries',
    'transfers',
    'areas',
    'couriers',
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
  ]) {
    check(centerPath(section) == '/api/center/$section');
  }
  try {
    centerPath('../admin/users');
    throw StateError('Unknown section accepted');
  } on ArgumentError {
    checks++;
  }
  final fields = centerMultipart({
    'parcel_ids': [12, 13],
    'confirm_receipt': true,
    'active': false,
    'reason': 'Physical receipt verified',
    'missing': null,
  });
  check(fields['parcel_ids[0]'] == '12' && fields['parcel_ids[1]'] == '13');
  check(fields['confirm_receipt'] == '1' && fields['active'] == '0');
  check(
    fields['reason'] == 'Physical receipt verified' &&
        !fields.containsKey('missing'),
  );
  print('PASS: $checks native center contract checks.');
}
