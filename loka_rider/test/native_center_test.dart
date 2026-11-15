import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:lokashop_rider/api.dart';
import 'package:lokashop_rider/session.dart';
import 'package:lokashop_rider/screens/center/center_shell.dart';
import 'package:lokashop_rider/screens/center/center_form.dart';

class CenterApi extends ApiClient {
  CenterApi() : super(baseUrl: 'https://example.test');
  final calls = <String>[];
  Map<String, dynamic>? submitted;
  @override
  Future<Map<String, dynamic>> get(
    String path, [
    Map<String, String>? query,
  ]) async {
    calls.add(path);
    return {
      'title': path.endsWith('overview')
          ? 'My native center'
          : 'Native section',
      'records': path.endsWith('overview') ? [
        {'title': 'Recent parcel', 'facts': [], 'actions': [
          {'label': 'Open parcel operations', 'module': 'pickups'},
        ]},
      ] : [],
      'actions': path.endsWith('overview') ? [
        {'label': 'Go to pickups', 'module': 'pickups'},
      ] : [],
      'current_page': 1,
      'last_page': 1,
      'metrics': [
        {'label': 'Delivered', 'value': 7},
      ],
    };
  }

  @override
  Future<Map<String, dynamic>> post(
    String path,
    Map<String, dynamic> fields,
  ) async {
    submitted = fields;
    calls.add(path);
    return {'message': 'Saved'};
  }
}

void main() {
  testWidgets('center shows only requested grouped navigation and a read-only overview', (
    tester,
  ) async {
    final api = CenterApi();
    final s = Session(apiClient: api);
    await tester.pumpWidget(MaterialApp(home: CenterShell(session: s)));
    await tester.pumpAndSettle();
    expect(find.text('My native center'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('Recent parcel'), 400);
    expect(find.text('Recent parcel'), findsOneWidget);
    expect(find.text('Go to pickups'), findsNothing);
    expect(find.text('Open parcel operations'), findsNothing);
    expect(find.byType(NavigationBar), findsNothing);
    expect(find.byType(WebViewWidget), findsNothing);

    Future<void> openMenu() async {
      tester.state<ScaffoldState>(find.byType(Scaffold).first).openDrawer();
      await tester.pumpAndSettle();
    }

    await openMenu();
    expect(find.text('Pickups'), findsNothing);
    expect(find.text('Transfers'), findsNothing);
    expect(find.text('Deliveries'), findsNothing);
    expect(find.text('More'), findsNothing);
    await tester.tap(find.text('Finance & Reports'));
    await tester.pumpAndSettle();
    for (final label in ['Wallet', 'Financial report',
      'COD Collections & Remittances', 'Filtered report']) {
      expect(find.text(label), findsOneWidget);
    }
    await tester.tap(find.text('Financial report'));
    await tester.pumpAndSettle();
    expect(api.calls.last, '/api/center/finance');
    await openMenu();
    await tester.tap(find.text('Filtered report'));
    await tester.pumpAndSettle();
    expect(api.calls.last, '/api/center/reports');
    await openMenu();
    await tester.tap(find.text('Overview'));
    await tester.pumpAndSettle();
    expect(api.calls.last, '/api/center/overview');
    expect(find.text('Go to pickups'), findsNothing);
    expect(find.text('Open parcel operations'), findsNothing);
    await tester.pumpWidget(const SizedBox());
    s.dispose();
  });
  testWidgets(
    'bulk receipt requires parcel selection and explicit confirmation',
    (tester) async {
      final api = CenterApi();
      final session = Session(apiClient: api);
      await tester.pumpWidget(const MaterialApp(home: Scaffold()));
      Navigator.of(tester.element(find.byType(Scaffold))).push(
        MaterialPageRoute(
          builder: (_) => CenterForm(
            session: session,
            action: {
              'key': 'stop-receive',
              'label': 'Confirm received parcels',
              'id': 8,
              'fixed': {'confirm_receipt': true},
              'fields': [
                {
                  'name': 'parcel_ids',
                  'label': 'Parcels physically received',
                  'type': 'multi',
                  'required': true,
                  'options': [
                    {'value': '12', 'label': 'LKS-TWELVE'},
                    {'value': '13', 'label': 'LKS-THIRTEEN'},
                  ],
                },
              ],
            },
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Continue'));
      await tester.pumpAndSettle();
      expect(api.submitted, isNull);
      expect(find.text('Select at least one parcel'), findsOneWidget);
      await tester.tap(find.text('LKS-TWELVE'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Continue'));
      await tester.pumpAndSettle();
      expect(api.submitted, isNull);
      expect(find.text('Confirm received parcels?'), findsOneWidget);
      await tester.tap(find.widgetWithText(FilledButton, 'Confirm'));
      await tester.pumpAndSettle();
      expect(api.calls.last, '/api/center/action/stop-receive/8');
      expect(api.submitted?['parcel_ids'], [12]);
      expect(api.submitted?['confirm_receipt'], isTrue);
      expect(
        api.submitted?['_request_key'],
        matches(RegExp(r'^[a-f0-9]{48}$')),
      );
      await tester.pumpWidget(const SizedBox());
      session.dispose();
    },
  );
  testWidgets('changing invited courier role resets incompatible vehicle', (
    tester,
  ) async {
    final session = Session(apiClient: CenterApi());
    await tester.pumpWidget(
      MaterialApp(
        home: CenterForm(
          session: session,
          action: {
            'key': 'courier-invite',
            'label': 'Invite courier',
            'fields': [
              {
                'name': 'courier_type',
                'label': 'Courier role',
                'type': 'select',
                'initial': 'rider',
                'options': [
                  {'value': 'rider', 'label': 'Rider'},
                  {'value': 'driver', 'label': 'Driver'},
                ],
              },
              {
                'name': 'vehicle',
                'label': 'Vehicle',
                'type': 'select',
                'initial': 'motorcycle',
                'options': [
                  {'value': 'motorcycle', 'label': 'Motorcycle'},
                  {'value': '4-wheel truck', 'label': 'Truck'},
                ],
              },
            ],
          },
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Rider'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Driver').last);
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    await tester.tap(find.text('Vehicle'));
    await tester.pumpAndSettle();
    expect(find.text('Motorcycle'), findsNothing);
    expect(find.text('Truck'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    session.dispose();
  });
}
