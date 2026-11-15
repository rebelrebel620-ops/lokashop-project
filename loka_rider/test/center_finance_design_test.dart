import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lokashop_rider/screens/center/center_finance_widgets.dart';
import 'package:lokashop_rider/widgets/wallet_screen.dart';

void main() {
  test('money formatting preserves signs and handles grouped source amounts', () {
    expect(centerMoney('25.0000'), '₱25.00');
    expect(centerMoney('1,234.50'), '₱1,234.50');
    expect(centerMoney('-12.5000'), '-₱12.50');
    expect(centerMoney('unavailable'), 'unavailable');
  });

  testWidgets('record actions remain wired and monetary facts use two decimals', (tester) async {
    Map? pressed;
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: SingleChildScrollView(child: CenterRecordCard(
      row: {
        'title': 'Remittance #40', 'status': 'pending',
        'facts': [{'label': 'Amount (PHP)', 'value': '12.5000'}, {'label': 'Reference', 'value': '40'}],
        'actions': [{'label': 'Confirm remittance', 'key': 'cod-confirm', 'id': 40}],
      },
      onAction: (a) => pressed = a,
    )))));
    expect(find.text('₱12.50'), findsOneWidget);
    expect(find.text('40'), findsOneWidget);
    await tester.tap(find.text('Confirm remittance'));
    expect(pressed?['key'], 'cod-confirm');
    expect(pressed?['id'], 40);
    expect(tester.takeException(), isNull);
  });

  testWidgets('center wallet keeps transfer confirmation and API payload', (tester) async {
    Map<String, dynamic>? submitted;
    await tester.binding.setSurfaceSize(const Size(390, 844));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(MaterialApp(home: WalletScreen(
      centerDesign: true,
      fetch: (_) async => {
        'balance': '25.00', 'demo': true, 'center_owed': 40000,
        'transactions': {'data': [], 'last_page': 1},
        'courier_payments': null,
      },
      transfer: (fields) async { submitted = fields; return {}; },
    )));
    await tester.pumpAndSettle();
    expect(find.text('₱25.00'), findsOneWidget);
    expect(find.text('₱400.00'), findsOneWidget);
    await tester.scrollUntilVisible(find.widgetWithText(FilledButton, 'Send money'), 200);
    await tester.enterText(find.byType(TextFormField).at(0), 'recipient@example.com');
    await tester.enterText(find.byType(TextFormField).at(1), '1.25');
    await tester.enterText(find.byType(TextFormField).at(2), 'example-password');
    await tester.ensureVisible(find.widgetWithText(FilledButton, 'Send money'));
    await tester.tap(find.widgetWithText(FilledButton, 'Send money'));
    await tester.pumpAndSettle();
    expect(submitted, isNull);
    expect(find.text('Send wallet funds?'), findsOneWidget);
    await tester.tap(find.text('Cancel'));
    await tester.pumpAndSettle();
    expect(submitted, isNull);
    await tester.tap(find.widgetWithText(FilledButton, 'Send money'));
    await tester.pumpAndSettle();
    await tester.tap(find.descendant(of: find.byType(AlertDialog), matching: find.widgetWithText(FilledButton, 'Send money')));
    await tester.pumpAndSettle();
    expect(submitted?['recipient'], 'recipient@example.com');
    expect(submitted?['amount'], '1.25');
    expect(submitted?['password'], 'example-password');
    expect(submitted?['_request_key'], matches(RegExp(r'^[a-f0-9]{48}$')));
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });

  for (final scale in [1.0, 2.0]) {
    testWidgets('finance summary fits small screens at text scale $scale', (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 844));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await tester.pumpWidget(MaterialApp(home: MediaQuery(
        data: MediaQueryData(textScaler: TextScaler.linear(scale)),
        child: const Scaffold(body: SingleChildScrollView(child: Padding(
          padding: EdgeInsets.all(16),
          child: Column(children: [
            CenterFinanceSummary(metrics: [
              {'label': 'Wallet (PHP)', 'value': '25.00'},
              {'label': 'Legacy funding owed (PHP)', 'value': '400.00'},
              {'label': 'Shipping income', 'value': '1234567.8900'},
            ]),
            CenterEmptyState(title: 'No remittances yet', message: 'Pull down to refresh.'),
          ]),
        ))),
      )));
      expect(find.text('₱1,234,567.89'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }
}
