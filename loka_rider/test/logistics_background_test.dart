import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lokashop_rider/models.dart';
import 'package:lokashop_rider/widgets/logistics_background.dart';

void main() {
  test('background follows the authenticated role, including cached profiles', () {
    final center = Rider.fromJson({'id': 1, 'role': 'sorting_center'});
    final driver = Rider.fromJson({'id': 2, 'role': 'rider', 'courier_type': 'driver'});
    final rider = Rider.fromJson({'id': 3, 'role': 'rider', 'courier_type': 'rider'});
    expect(LogisticsBackground.forUser(center), LogisticsBackground.sortingCenter);
    expect(LogisticsBackground.forUser(Rider.fromJson(driver.toJson())), LogisticsBackground.driver);
    expect(LogisticsBackground.forUser(rider), LogisticsBackground.rider);
    expect(LogisticsBackground.forUser(null), isNull);
    expect(LogisticsBackground.login, isNot(LogisticsBackground.register));
  });

  testWidgets('decorative layers do not intercept form button taps', (tester) async {
    var pressed = false;
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: LogisticsBackground(
      asset: LogisticsBackground.sortingCenter,
      child: Center(child: FilledButton(onPressed: () => pressed = true, child: const Text('Continue'))),
    ))));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continue'));
    expect(pressed, isTrue);
    expect(tester.takeException(), isNull);
  });

  testWidgets('registration gets its own backdrop and readable panel', (tester) async {
    await tester.pumpWidget(const MaterialApp(home: RegistrationScaffold(
      body: Center(child: Text('Registration form')),
    )));
    await tester.pumpAndSettle();
    final background = tester.widget<LogisticsBackground>(find.byType(LogisticsBackground));
    expect(background.asset, LogisticsBackground.register);
    expect(find.text('Registration form'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
