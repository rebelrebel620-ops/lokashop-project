import 'package:flutter_test/flutter_test.dart';
import 'package:lokashop_rider/api.dart';
import 'package:lokashop_rider/models.dart';

void main() {
  test('parses an actionable delivery (MySQL decimals arrive as strings)', () {
    final a = Assignment.fromJson({
      'id': 7,
      'parcel_id': '9002',
      'order_id': 9002,
      'kind': 'delivery',
      'status': 'accepted',
      'earnings': '50.00',
      'tracking_code': 'LKS-DEMO00000002',
      'parcel_status': 'OUT_FOR_DELIVERY',
      'city': 'Santa Cruz',
      'province': 'Laguna',
      'actions': [
        {'type': 'status', 'status': 'DELIVERED', 'label': 'Mark delivered', 'needs_reason': false},
        {'type': 'status', 'status': 'DELIVERY_FAILED', 'label': 'Delivery failed', 'needs_reason': true},
      ],
      'recipient': {'name': 'Demo Buyer', 'phone': '0900', 'street': '12 Main Street', 'barangay': 'Poblacion'},
      'payment': {'method': 'cod', 'status': 'pending', 'amount': '398.00'},
      'items': [
        {'name': 'Reusable Bag', 'quantity': 2}
      ],
    });

    expect(a.parcelId, 9002);
    expect(a.earnings, 50.0);
    expect(a.isPickup, isFalse);
    expect(a.actions.length, 2);
    expect(a.actions.last.needsReason, isTrue);
    expect(a.actions.last.isDestructive, isTrue);
    expect(a.recipient?.street, '12 Main Street');
    expect(a.payment?.amount, 398.0);
    expect(a.items.single.quantity, 2);
  });

  test('offered job has no private details and one accept action', () {
    final a = Assignment.fromJson({
      'id': 1,
      'parcel_id': 9003,
      'order_id': 9003,
      'kind': 'delivery',
      'status': 'offered',
      'earnings': 50,
      'tracking_code': 'LKS-DEMO00000003',
      'parcel_status': 'ASSIGNED_TO_RIDER',
      'city': 'Santa Cruz',
      'province': 'Laguna',
      'actions': [
        {'type': 'accept', 'status': null, 'label': 'Accept assignment', 'needs_reason': false}
      ],
      'recipient': null,
    });
    expect(a.recipient, isNull);
    expect(a.actions.single.type, 'accept');
  });

  test('formatting helpers', () {
    expect(peso(1234.5), '\u20B11234.50');
    expect(prettyStatus('OUT_FOR_DELIVERY'), 'Out For Delivery');
    expect(shortDate('2026-10-04 15:20:11'), '2026-10-04 15:20');
  });

  test('server address normalisation', () {
    expect(ApiClient.normalizeUrl('192.168.1.5:8001/'), 'http://192.168.1.5:8001');
    expect(ApiClient.normalizeUrl(' https://lokashop.trade// '), 'https://lokashop.trade');
  });
}
