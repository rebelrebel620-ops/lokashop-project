import 'package:flutter/material.dart';

import 'screens/home_shell.dart';
import 'screens/login_screen.dart';
import 'session.dart';
import 'theme.dart';

final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  final session = Session();
  session.onSignedOut = () =>
      navigatorKey.currentState?.popUntil((route) => route.isFirst);
  session.init();
  runApp(LokaShopRiderApp(session: session));
}

class LokaShopRiderApp extends StatelessWidget {
  final Session session;
  const LokaShopRiderApp({super.key, required this.session});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'LokaShop Rider',
      navigatorKey: navigatorKey,
      debugShowCheckedModeBanner: false,
      theme: courierTheme(),
      home: ListenableBuilder(
        listenable: session,
        builder: (context, _) {
          if (!session.ready) {
            return const Scaffold(
              body: Center(child: CircularProgressIndicator()),
            );
          }
          return session.loggedIn
              ? HomeShell(session: session)
              : LoginScreen(session: session);
        },
      ),
    );
  }
}
