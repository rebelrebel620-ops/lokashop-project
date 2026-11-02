import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:image_picker/image_picker.dart';
import 'package:lokashop_rider/api.dart';
import 'package:lokashop_rider/models.dart';
import 'package:lokashop_rider/session.dart';
import 'package:lokashop_rider/screens/login_screen.dart';
import 'package:lokashop_rider/screens/registration_screen.dart';
import 'package:lokashop_rider/screens/profile_screen.dart';
import 'package:lokashop_rider/theme.dart';

class AccountApi extends ApiClient {
  AccountApi() : super(baseUrl: 'https://example.test');
  final calls = <String>[];
  Map<String, dynamic> user = {
    'id': 1,
    'name': 'Courier Name',
    'email': 'old@example.test',
    'phone': '09170000001',
    'role': 'rider',
  };
  @override
  Future<Map<String, dynamic>> post(
    String path,
    Map<String, dynamic> body,
  ) async {
    calls.add(path);
    if (path.endsWith('/verify')) {
      user = {...user, 'phone': '09170000002'};
      return {'rider': user};
    }
    return {'message': 'Code sent to your registered email.'};
  }

  @override
  Future<Map<String, dynamic>> upload(
    String path,
    Map<String, String> fields,
    Map<String, XFile> files,
  ) async {
    calls.add(path);
    user = {...user, 'name': fields['name']};
    return {'rider': user};
  }
}

Session session(AccountApi api) =>
    Session(apiClient: api)..rider = Rider.fromJson(api.user);
Future<void> show(WidgetTester t, Widget w) async {
  await t.runAsync(() => rootBundle.loadString('assets/places.json'));
  await t.pumpWidget(MaterialApp(theme: courierTheme(), home: w));
  await settle(t);
}

Future<void> settle(WidgetTester t) async {
  await t.runAsync(
    () => Future<void>.delayed(const Duration(milliseconds: 350)),
  );
  await t.pumpAndSettle();
}

void main() {
  testWidgets('sign up opens native sorting center or courier choices', (
    t,
  ) async {
    final s = Session();
    await show(t, LoginScreen(session: s));
    await t.ensureVisible(find.text('Sign up'));
    await t.tap(find.text('Sign up'));
    await settle(t);
    expect(find.byType(SignUpScreen), findsOneWidget);
    expect(find.text('Sorting center'), findsOneWidget);
    expect(find.text('Courier'), findsOneWidget);
    await t.tap(find.text('Courier'));
    await settle(t);
    expect(find.byType(RegistrationScreen), findsOneWidget);
    expect(find.text('Courier role'), findsOneWidget);
  });
  testWidgets('sorting center has native business and identity fields', (
    t,
  ) async {
    await show(
      t,
      RegistrationScreen(session: Session(), type: 'sorting_center'),
    );
    await t.scrollUntilVisible(
      find.text('Sorting center name'),
      250,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Sorting center name'), findsOneWidget);
    await t.scrollUntilVisible(
      find.text('SEC registration number'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('SEC registration number'), findsOneWidget);
    await t.scrollUntilVisible(
      find.text('TIN'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('TIN'), findsOneWidget);
  });
  testWidgets('driver choice changes courier vehicle options', (t) async {
    await show(t, RegistrationScreen(session: Session(), type: 'courier'));
    await t.tap(find.text('Rider • local pickup and delivery'));
    await settle(t);
    await t.tap(find.text('Driver • sorting center transfers').last);
    await settle(t);
    expect(find.text('Driver • sorting center transfers'), findsOneWidget);
  });
  for (final role in ['rider', 'driver', 'sorting_center']) {
    testWidgets('$role profile saves and exposes all security settings', (
      t,
    ) async {
      final api = AccountApi();
      api.user = {
        ...api.user,
        'role': role == 'sorting_center' ? 'sorting_center' : 'rider',
        'courier_type': role,
      };
      final s = session(api);
      await show(t, ProfileScreen(session: s));
      await t.enterText(find.byType(TextField).first, 'Updated Name');
      await t.tap(find.text('Save profile'));
      await settle(t);
      expect(api.calls, contains('/api/mobile/profile'));
      expect(s.rider!.name, 'Updated Name');
      await t.scrollUntilVisible(
        find.text('Password'),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('Phone number'), findsOneWidget);
      expect(find.text('Email address'), findsOneWidget);
      expect(find.text('Password'), findsOneWidget);
    });
  }
  testWidgets('phone change requests code then verifies and updates session', (
    t,
  ) async {
    final api = AccountApi();
    final s = session(api);
    await show(t, AccountSecurityScreen(session: s, kind: 'phone'));
    await t.enterText(find.byType(TextFormField).at(0), 'Test@1234');
    await t.enterText(find.byType(TextFormField).at(1), '09170000002');
    await t.tap(find.text('Send verification code'));
    await settle(t);
    expect(api.calls.last, '/api/mobile/contact');
    expect(find.text('Verification code'), findsOneWidget);
    await t.enterText(find.byType(TextFormField).first, '123456');
    await t.tap(find.text('Verify and save'));
    await settle(t);
    expect(api.calls.last, '/api/mobile/contact/verify');
    expect(s.rider!.phone, '09170000002');
  });
  testWidgets('password requires confirmation before calling server', (
    t,
  ) async {
    final api = AccountApi();
    await show(
      t,
      AccountSecurityScreen(session: session(api), kind: 'password'),
    );
    await t.enterText(find.byType(TextFormField).at(0), 'Test@1234');
    await t.enterText(find.byType(TextFormField).at(1), 'New@1234');
    await t.enterText(find.byType(TextFormField).at(2), 'Wrong');
    await t.tap(find.text('Update password'));
    await settle(t);
    expect(find.text('Passwords do not match'), findsOneWidget);
    expect(api.calls, isEmpty);
  });
}
