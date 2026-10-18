import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lokashop_rider/api.dart';
import 'package:lokashop_rider/models.dart';
import 'package:lokashop_rider/theme.dart';
import 'package:lokashop_rider/widgets/assignment_card.dart';

void main() {
  for (final width in [360.0, 800.0]) {
    testWidgets('Assignment content fits a $width logical pixel screen', (
      tester,
    ) async {
      tester.view.physicalSize = Size(width, 1000);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final assignment = Assignment.fromJson({
        'id': 1,
        'parcel_id': 2,
        'kind': 'delivery',
        'status': 'accepted',
        'tracking_code': 'LKS-LAYOUT-DEMO',
        'parcel_status': 'OUT_FOR_DELIVERY',
        'city': 'Santa Cruz',
        'province': 'Laguna',
        'recipient': {
          'name': 'Demonstration Buyer',
          'phone': '09170000000',
          'street': 'Example Street',
          'barangay': 'Poblacion',
        },
        'payment': {'method': 'cod', 'amount': '1234.50'},
        'actions': [
          {'type': 'status', 'status': 'DELIVERED', 'label': 'Mark delivered'},
          {
            'type': 'status',
            'status': 'DELIVERY_FAILED',
            'label': 'Delivery failed',
            'needs_reason': true,
          },
        ],
      });
      await tester.pumpWidget(
        MaterialApp(
          theme: courierTheme(),
          home: Scaffold(
            body: SingleChildScrollView(
              child: AssignmentCard(
                assignment: assignment,
                api: ApiClient(baseUrl: 'https://example.test'),
                onUpdated: (_) {},
              ),
            ),
          ),
        ),
      );
      expect(find.text('Mark delivered'), findsOneWidget);
      expect(find.textContaining('1234.50'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }
}
