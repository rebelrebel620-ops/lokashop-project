import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lokashop_rider/screens/startup_screen.dart';

void main() {
  testWidgets('startup shows branding and loading without navigation controls', (tester) async {
    await tester.pumpWidget(const MaterialApp(home: StartupScreen()));
    await tester.pump(const Duration(milliseconds: 100));
    expect(find.text('LokaShop'), findsOneWidget);
    expect(find.text('LOGISTICS'), findsOneWidget);
    expect(find.text('Preparing your workspace…'), findsOneWidget);
    expect(find.byType(CircularProgressIndicator), findsOneWidget);
    expect(find.byType(FilledButton), findsNothing);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });

  testWidgets('startup fits a narrow screen with large text', (tester) async {
    await tester.binding.setSurfaceSize(const Size(320, 640));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(const MaterialApp(home: MediaQuery(
      data: MediaQueryData(textScaler: TextScaler.linear(2)),
      child: StartupScreen(),
    )));
    await tester.pump(const Duration(milliseconds: 100));
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });
}
