import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lokashop_rider/screens/center/center_overview_content.dart';

void main() {
  final data = <String, dynamic>{
    'title': 'Santa Cruz Sorting Center',
    'metrics': [
      {'label': 'Parcels in selected period', 'value': 125},
      {'label': 'Awaiting dispatch', 'value': 31},
      {'label': 'Out for delivery', 'value': 42},
      {'label': 'Delivered / completed', 'value': 52},
      {'label': 'Incoming transfers', 'value': 9},
      {'label': 'Approved riders', 'value': 12},
      {'label': 'Approved drivers', 'value': 3},
      {'label': 'Couriers online (last 30 minutes)', 'value': 7},
      {'label': 'Pending applications', 'value': 4},
      {'label': 'Delivery areas', 'value': 8},
      {'label': 'Unremitted COD', 'value': 'PHP 12,345.67'},
    ],
  };

  for (final width in [320.0, 390.0, 850.0]) {
    testWidgets('overview uses live summaries without overflow at width $width', (tester) async {
      await tester.binding.setSurfaceSize(Size(width, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await tester.pumpWidget(MaterialApp(home: Scaffold(
        body: SingleChildScrollView(child: Padding(
          padding: const EdgeInsets.all(16),
          child: CenterOverviewContent(data: data),
        )),
      )));
      expect(find.text('Santa Cruz Sorting Center'), findsOneWidget);
      expect(find.text('Parcel Operations'), findsOneWidget);
      expect(find.text('Team & Coverage'), findsOneWidget);
      expect(find.text('125'), findsOneWidget);
      expect(find.text('PHP 12,345.67'), findsOneWidget);
      expect(find.byType(InkWell), findsNothing);
      expect(find.byType(FilledButton), findsNothing);
      expect(find.byType(TextButton), findsNothing);
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets('large text and missing metrics remain readable', (tester) async {
    await tester.binding.setSurfaceSize(const Size(320, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(MaterialApp(home: MediaQuery(
      data: const MediaQueryData(textScaler: TextScaler.linear(2)),
      child: const Scaffold(body: SingleChildScrollView(child: Padding(
        padding: EdgeInsets.all(16),
        child: CenterOverviewContent(data: {'title': 'A long sorting center name that must wrap', 'metrics': []}),
      ))),
    )));
    expect(find.text('—'), findsWidgets);
    expect(find.text('PHP 0.00'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}
