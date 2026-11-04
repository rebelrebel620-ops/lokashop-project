import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lokashop_rider/api.dart';
import 'package:lokashop_rider/models.dart';
import 'package:lokashop_rider/session.dart';
import 'package:lokashop_rider/screens/home_shell.dart';
import 'package:lokashop_rider/screens/assignments_screen.dart';
import 'package:lokashop_rider/screens/courier_overview_screen.dart';
import 'package:lokashop_rider/screens/trips_screen.dart';
import 'package:lokashop_rider/theme.dart';
import 'package:lokashop_rider/widgets/assignment_card.dart';

class FakeCourierApi extends ApiClient {
  FakeCourierApi() : super(baseUrl: 'https://example.test');
  String? lastPath;
  Map<String, String>? lastQuery;
  String? postedPath;
  Map<String, dynamic>? postedFields;
  List<Map<String, dynamic>> trips = [];
  @override
  Future<Map<String, dynamic>> get(
    String path, [
    Map<String, String>? query,
  ]) async {
    lastPath = path;
    lastQuery = query;
    if (path.endsWith('/overview')) {
      return {
        'offered': 2,
        'trip_offers': 2,
        'active_trips': 1,
        'active': 1,
        'completed': 12,
        'completed_trips': 3,
        'today_completed': 2,
        'today_completed_trips': 1,
        'today_earnings': 100,
        'unread': 1,
        'current_work': [],
        'center': {
          'name': 'Calamba center',
          'city': 'Calamba',
          'province': 'Laguna',
        },
      };
    }
    return {'data': path.endsWith('/trips') ? trips : [], 'last_page': 1};
  }

  @override
  Future<Map<String, dynamic>> post(
    String path,
    Map<String, dynamic> body,
  ) async {
    postedPath = path;
    postedFields = body;
    if (path.endsWith('/decline')) trips = [];
    return {'message': 'Saved'};
  }

  @override
  Future<Assignment> scan(String raw, {int? assignmentId}) async =>
      Assignment.fromJson({
        'id': 4,
        'parcel_id': 9,
        'tracking_code': raw,
        'kind': 'transfer',
        'status': 'accepted',
        'parcel_status': 'IN_TRANSIT_CENTER',
        'actions': [],
      });
}

Session sessionFor(FakeCourierApi api, {bool driver = false}) =>
    Session(apiClient: api)
      ..ready = true
      ..rider = Rider(
        id: 1,
        name: 'Demo Courier',
        email: 'demo@example.test',
        courierType: driver ? 'driver' : 'rider',
      );
Widget app(Widget child) => MaterialApp(theme: courierTheme(), home: child);

void main() {
  testWidgets('driver starts on a native overview and can open trips', (
    tester,
  ) async {
    final api = FakeCourierApi();
    await tester.pumpWidget(
      app(HomeShell(session: sessionFor(api, driver: true))),
    );
    await tester.pumpAndSettle();
    expect(find.text('Driver overview'), findsOneWidget);
    await tester.tap(find.text('Trips'));
    await tester.pumpAndSettle();
    expect(find.text('Transport trips'), findsOneWidget);
    expect(api.lastPath, '/api/rider/trips');
    await tester.pumpWidget(const SizedBox());
  });

  testWidgets('rider stage switches request the matching server filter', (
    tester,
  ) async {
    final api = FakeCourierApi();
    await tester.pumpWidget(
      app(AssignmentsScreen(session: sessionFor(api), onScan: () {})),
    );
    await tester.pumpAndSettle();
    expect(api.lastQuery?['filter'], 'new');
    await tester.tap(find.text('Ongoing'));
    await tester.pumpAndSettle();
    expect(api.lastQuery?['filter'], 'progress');
    expect(api.lastQuery?['page'], '1');
    await tester.tap(find.text('Completed'));
    await tester.pumpAndSettle();
    expect(api.lastQuery?['filter'], 'completed');
    await tester.pumpWidget(const SizedBox());
  });

  testWidgets('declining a driver offer returns to requests', (tester) async {
    final api = FakeCourierApi()
      ..trips = [
        {
          'id': 7,
          'status': 'offered',
          'expires_at': '2026-11-02 15:00:00',
          'stops': [],
        },
      ];
    await tester.pumpWidget(
      app(TripsScreen(session: sessionFor(api, driver: true))),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Decline'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Decline trip'));
    await tester.pumpAndSettle();
    expect(api.postedPath, '/api/rider/trips/7/decline');
    final tabs = tester.widget<SegmentedButton<String>>(
      find.byType(SegmentedButton<String>),
    );
    expect(tabs.selected, {'new'});
    expect(api.lastQuery?['tab'], 'new');
    await tester.pumpWidget(const SizedBox());
  });

  testWidgets('individual transfer handover sends explicit acknowledgement', (
    tester,
  ) async {
    final api = FakeCourierApi();
    final assignment = Assignment.fromJson({
      'id': 4,
      'parcel_id': 9,
      'tracking_code': 'LKS-HANDOVER',
      'kind': 'transfer',
      'status': 'accepted',
      'parcel_status': 'IN_TRANSIT_CENTER',
      'actions': [
        {
          'type': 'transfer_handover',
          'label': 'Confirm handover at destination',
        },
      ],
    });
    await tester.pumpWidget(
      app(
        Scaffold(
          body: SingleChildScrollView(
            child: AssignmentCard(
              assignment: assignment,
              api: api,
              onUpdated: (_) {},
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Confirm handover at destination'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Confirm'));
    await tester.pumpAndSettle();
    expect(api.postedPath, '/api/rider/transfers/9/handover');
    expect(api.postedFields, {'confirm_handover': true});
  });

  testWidgets('partial destination receipt keeps the driver trip ongoing', (
    tester,
  ) async {
    final api = FakeCourierApi()
      ..trips = [
        {
          'id': 8,
          'status': 'in_transit',
          'stops': [
            {
              'id': 3,
              'position': 1,
              'center_name': 'Santa Cruz center',
              'city': 'Santa Cruz',
              'province': 'Laguna',
              'handed_over_at': '2026-11-02 12:00:00',
              'received_at': null,
              'parcels': [
                {
                  'tracking_code': 'LKS-ONE',
                  'received_at': '2026-11-02 12:05:00',
                },
                {'tracking_code': 'LKS-TWO', 'received_at': null},
              ],
            },
          ],
        },
      ];
    await tester.pumpWidget(
      app(
        TripsScreen(
          session: sessionFor(api, driver: true),
          initialTab: 'progress',
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('1 / 2 parcels received'), findsOneWidget);
    expect(
      find.text(
        'Handover recorded. Waiting for the destination center to confirm all remaining parcels.',
      ),
      findsOneWidget,
    );
    expect(
      tester
          .widget<SegmentedButton<String>>(find.byType(SegmentedButton<String>))
          .selected,
      {'progress'},
    );
    expect(api.postedPath, isNull);
    await tester.pumpWidget(const SizedBox());
  });

  for (final code in <String?>[null, 'LKS-WRONG']) {
    testWidgets(
      'pickup confirmation is locked without a matching scan: $code',
      (tester) async {
        var opened = false;
        final assignment = Assignment.fromJson({
          'id': 15,
          'parcel_id': 19,
          'kind': 'pickup',
          'status': 'accepted',
          'parcel_status': 'READY_FOR_PICKUP',
          'tracking_code': 'LKS-PICKUP',
          'actions': [
            {
              'type': 'status',
              'status': 'PICKED_UP',
              'label': 'Confirm pickup from seller',
            },
          ],
        });
        await tester.pumpWidget(
          app(
            Scaffold(
              body: SingleChildScrollView(
                child: AssignmentCard(
                  assignment: assignment,
                  api: FakeCourierApi(),
                  scannedCode: code,
                  onScan: () => opened = true,
                  onUpdated: (_) {},
                ),
              ),
            ),
          ),
        );
        await tester.pumpAndSettle();
        expect(find.text('Confirm pickup from seller'), findsNothing);
        expect(find.text('Tracking history'), findsNothing);
        await tester.tap(find.text('Scan parcel'));
        expect(opened, isTrue);
      },
    );
  }
  testWidgets('matching verified scan unlocks pickup confirmation', (
    tester,
  ) async {
    final api = FakeCourierApi();
    final assignment = Assignment.fromJson({
      'id': 15,
      'parcel_id': 19,
      'kind': 'pickup',
      'status': 'accepted',
      'parcel_status': 'READY_FOR_PICKUP',
      'tracking_code': 'LKS-PICKUP',
      'actions': [
        {
          'type': 'status',
          'status': 'PICKED_UP',
          'label': 'Confirm pickup from seller',
        },
      ],
    });
    await tester.pumpWidget(
      app(
        Scaffold(
          body: SingleChildScrollView(
            child: AssignmentCard(
              assignment: assignment,
              api: api,
              scannedCode: 'LKS-PICKUP',
              onUpdated: (_) {},
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Scan parcel'), findsNothing);
    await tester.tap(find.text('Confirm pickup from seller'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Confirm'));
    await tester.pumpAndSettle();
    expect(api.postedPath, '/api/rider/parcels/19/status');
    expect(api.postedFields?['status'], 'PICKED_UP');
    expect(api.postedFields?['scanned_code'], 'LKS-PICKUP');
  });

  for (final driver in [false, true]) {
    for (final width in [320.0, 800.0]) {
      testWidgets('${driver ? 'driver' : 'rider'} overview fits width $width', (
        tester,
      ) async {
        tester.view.physicalSize = Size(width, 1000);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        await tester.pumpWidget(
          app(
            OverviewScreen(
              session: sessionFor(FakeCourierApi(), driver: driver),
              onJobs: (_) {},
              onScan: () {},
            ),
          ),
        );
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        expect(
          find.text(driver ? 'Driver overview' : 'Rider overview'),
          findsOneWidget,
        );
      });
    }
  }
}
