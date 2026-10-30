import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lokashop_rider/models.dart';
import 'package:lokashop_rider/session.dart';
import 'package:lokashop_rider/screens/login_screen.dart';
import 'package:lokashop_rider/widgets/assignment_card.dart';
import 'package:lokashop_rider/api.dart';
import 'package:lokashop_rider/theme.dart';

void main() {
  test('sorting center and driver roles survive cached profiles', () {
    final center=Rider.fromJson({'id':1,'name':'Center','email':'c@example.test','role':'sorting_center'});
    expect(center.isCenter,isTrue);
    expect(Rider.fromJson(center.toJson()).isCenter,isTrue);
    final driver=Rider.fromJson({'id':2,'role':'rider','courier_type':'driver'});
    expect(driver.isDriver,isTrue);
    expect(driver.isCenter,isFalse);
  });
  testWidgets('login shows registration for all three roles and no server control', (tester) async {
    await tester.pumpWidget(MaterialApp(theme:courierTheme(),home:LoginScreen(session:Session())));
    expect(find.text('Sign in'),findsWidgets);
    expect(find.text('Register as rider'),findsOneWidget);
    expect(find.text('Register as driver'),findsOneWidget);
    expect(find.text('Register a sorting center'),findsOneWidget);
    expect(find.text('Server'),findsNothing);
    expect(find.text('Server address'),findsNothing);
  });
  testWidgets('delivery requires label, proof and COD without a delivery OTP field', (tester) async {
    final assignment=Assignment.fromJson({'id':1,'parcel_id':2,'tracking_code':'LKS-TEST','kind':'delivery','status':'accepted',
      'parcel_status':'OUT_FOR_DELIVERY','payment':{'method':'cod','amount':150}});
    await tester.pumpWidget(MaterialApp(theme:courierTheme(),home:DeliveryScreen(assignment:assignment,api:ApiClient(baseUrl:'https://example.test'))));
    expect(find.text('Buyer delivery OTP'),findsNothing);
    expect(find.text('Scanned label or printed tracking code'),findsOneWidget);
    expect(find.text('COD collected'),findsOneWidget);
    expect(find.text('Take photo'),findsOneWidget);
  });
}
