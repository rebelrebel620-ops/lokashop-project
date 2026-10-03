import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'services/app_state.dart';
import 'theme.dart';
import 'screens/splash_screen.dart';

void main() {
  runApp(const LokaShopBuyerApp());
}

class LokaShopBuyerApp extends StatelessWidget {
  const LokaShopBuyerApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => AppState(),
      child: MaterialApp(
        title: 'LokaShop',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        home: const SplashScreen(),
      ),
    );
  }
}
