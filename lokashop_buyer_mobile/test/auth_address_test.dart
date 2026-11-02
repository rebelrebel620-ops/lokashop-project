import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lokashop_buyer/screens/login_screen.dart';
import 'package:lokashop_buyer/screens/register_screen.dart';
import 'package:lokashop_buyer/widgets/place_fields.dart';
import 'package:lokashop_buyer/theme.dart';

void main() {
  testWidgets('registration address asset is bundled and contains Laguna places', (tester) async {
    final raw = await tester.runAsync(() => rootBundle.loadString('assets/places.json'));
    final data = jsonDecode(raw!) as Map;
    final laguna = (data['provinces'] as List).firstWhere((p) => p['name'] == 'Laguna');
    expect((data['cities-municipalities'] as List).where((c) => c['provinceCode'] == laguna['code']), isNotEmpty);
  });

  testWidgets('sign in validates empty fields without an API call', (tester) async {
    await tester.pumpWidget(MaterialApp(theme: AppTheme.light(), home: const LoginScreen()));
    await tester.ensureVisible(find.widgetWithText(FilledButton, 'Sign in'));
    await tester.tap(find.widgetWithText(FilledButton, 'Sign in'));
    await tester.pumpAndSettle();
    expect(find.text('Enter a valid email'), findsOneWidget);
    expect(find.text('Enter your password'), findsOneWidget);
  });

  testWidgets('registration renders address section and geographic fields', (tester) async {
    await tester.pumpWidget(MaterialApp(theme: AppTheme.light(), home: const RegisterScreen()));
    await tester.runAsync(() async { await rootBundle.loadString('assets/places.json'); });
    await tester.pumpAndSettle();
    expect(find.text('Delivery address'), findsOneWidget);
    expect(find.byType(PlaceFields), findsOneWidget);
    expect(find.byType(DropdownButtonFormField<String>), findsNWidgets(4));
    expect(tester.takeException(), isNull);
  });

  testWidgets('editing an address infers region even when API omitted it', (tester) async {
    Map<String, String>? selected;
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: Form(child: PlaceFields(
      initial: const {'province': 'Laguna', 'city': 'City of Calamba', 'barangay': 'Barandal'},
      onChanged: (value) => selected = value,
    )))));
    await tester.runAsync(() async { await rootBundle.loadString('assets/places.json'); });
    await tester.pumpAndSettle();
    expect(selected?['province'], 'Laguna');
    expect(selected?['region'], isNotEmpty);
    expect(selected?['city'], isNotEmpty);
    expect(selected?['barangay'], 'Barandal');
    expect(tester.takeException(), isNull);
  });
}
