import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lokashop_rider/widgets/wallet_screen.dart';

Map<String, dynamic> ledger() => {
  'balance': '25.50',
  'demo': true,
  'center_owed': 0,
  'transactions': {
    'data': [
      {
        'description': 'Order refund',
        'amount_cents': 125,
        'balance_after_cents': 2550,
        'created_at': '2026-11-08',
      },
    ],
    'last_page': 1,
  },
  'courier_payments': null,
};
void main() {
  testWidgets('wallet shows server pesos and converts ledger centavos', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: WalletScreen(
          fetch: (_) async => ledger(),
          transfer: (_) async => {},
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('₱25.50'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Order refund'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('+₱1.25'), findsOneWidget);
    expect(find.textContaining('Balance after: ₱25.50'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets('invalid transfer cannot reach the API', (tester) async {
    var sent = 0;
    await tester.pumpWidget(
      MaterialApp(
        home: WalletScreen(
          fetch: (_) async => ledger(),
          transfer: (_) async {
            sent++;
            return {};
          },
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.widgetWithText(FilledButton, 'Send money'),
      160,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Send money'));
    await tester.pumpAndSettle();
    expect(sent, 0);
    expect(find.text('Enter your password'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
  });
  testWidgets('transfer requires confirmation and supplies a retry key', (
    tester,
  ) async {
    Map<String, dynamic>? submitted;
    await tester.pumpWidget(
      MaterialApp(
        home: WalletScreen(
          fetch: (_) async => ledger(),
          transfer: (v) async {
            submitted = v;
            return {};
          },
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byType(TextFormField).at(0),
      'buyer@example.com',
    );
    await tester.enterText(find.byType(TextFormField).at(1), '1.25');
    await tester.enterText(
      find.byType(TextFormField).at(2),
      'example-password',
    );
    await tester.scrollUntilVisible(
      find.widgetWithText(FilledButton, 'Send money'),
      160,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Send money'));
    await tester.pumpAndSettle();
    expect(submitted, isNull);
    expect(find.text('Send wallet funds?'), findsOneWidget);
    await tester.tap(
      find.descendant(
        of: find.byType(AlertDialog),
        matching: find.widgetWithText(FilledButton, 'Send money'),
      ),
    );
    await tester.pumpAndSettle();
    expect(submitted?['recipient'], 'buyer@example.com');
    expect(submitted?['amount'], '1.25');
    expect(submitted?['_request_key'], matches(RegExp(r'^[a-f0-9]{48}$')));
    expect(find.text('Transfer completed.'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
  });
}
