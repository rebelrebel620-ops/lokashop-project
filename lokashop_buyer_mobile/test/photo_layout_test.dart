import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:lokashop_buyer/services/app_state.dart';
import 'package:lokashop_buyer/widgets/design.dart';

void main() {
  testWidgets('full-width proof photo uses a finite decode size', (
    tester,
  ) async {
    final app = AppState();
    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: app,
        child: const MaterialApp(
          home: Scaffold(
            body: AppPhoto(
              'https://lokashop.trade/api/orders/1/proof',
              width: double.infinity,
              height: 280,
              imageSize: 1200,
              private: true,
            ),
          ),
        ),
      ),
    );
    await tester.pump();
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
    app.dispose();
  });
}
