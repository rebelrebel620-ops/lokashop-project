import 'package:flutter/material.dart';

import 'screens/home_shell.dart';
import 'screens/login_screen.dart';
import 'screens/center/center_shell.dart';
import 'session.dart';
import 'theme.dart';
import 'widgets/logistics_background.dart';
import 'screens/startup_screen.dart';

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
      title: 'LokaShop Logistics',
      navigatorKey: navigatorKey,
      debugShowCheckedModeBanner: false,
      theme: courierTheme(),
      builder: (context, child) => ListenableBuilder(
        listenable: session,
        builder: (context, _) {
          final theme = Theme.of(context);
          final user = session.rider;
          final asset = LogisticsBackground.forUser(user);
          return LogisticsBackground(
            asset: asset,
            child: Theme(
              data: theme.copyWith(
                scaffoldBackgroundColor: user == null
                    ? theme.scaffoldBackgroundColor : Colors.transparent,
              ),
              child: child ?? const SizedBox.shrink(),
            ),
          );
        },
      ),
      home: ListenableBuilder(
        listenable: session,
        builder: (context, _) {
          if (!session.ready) {
            return const StartupScreen();
          }
          return session.loggedIn
              ? (session.rider?.isCenter == true
                    ? CenterShell(
                        key: ValueKey(session.rider!.id),
                        session: session,
                      )
                    : HomeShell(session: session))
              : LoginScreen(session: session);
        },
      ),
    );
  }
}
