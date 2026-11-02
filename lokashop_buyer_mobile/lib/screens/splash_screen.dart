import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import 'package:flutter/services.dart';
import 'home_shell.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});
  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _boot());
  }

  Future<void> _boot() async {
    final app = context.read<AppState>();
    await app.boot();
    if (!mounted) return;
    Navigator.of(
      context,
    ).pushReplacement(MaterialPageRoute(builder: (_) => const HomeShell()));
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light.copyWith(
        statusBarColor: Colors.transparent,
        systemNavigationBarColor: const Color(0xFF103E2D),
      ),
      child: Scaffold(
        body: Container(
          width: double.infinity,
          decoration: const BoxDecoration(gradient: LinearGradient(
            begin: Alignment.topLeft, end: Alignment.bottomRight,
            colors: [Color(0xFF1D6446), Color(0xFF103E2D), Color(0xFF08291E)],
          )),
          child: SafeArea(child: Stack(children: [
            Positioned(top: -70, right: -90, child: _glow(260)),
            Positioned(bottom: -90, left: -110, child: _glow(300)),
            Center(child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Container(
                  decoration: BoxDecoration(borderRadius: BorderRadius.circular(32), boxShadow: const [BoxShadow(color: Color(0x55000000), blurRadius: 45, offset: Offset(0, 18))]),
                  child: ClipRRect(borderRadius: BorderRadius.circular(30), child: SizedBox(
                    width: 112, height: 112,
                    child: Transform.scale(scale: 1.0, child: Image.asset('assets/lokashop_logo.png', fit: BoxFit.cover)),
                  )),
                ),
                const SizedBox(height: 30),
                const Text('LokaShop', style: TextStyle(color: Colors.white, fontSize: 38, fontWeight: FontWeight.w900, letterSpacing: -1)),
                const SizedBox(height: 12),
                const Text('Local shops, delivered.', textAlign: TextAlign.center, style: TextStyle(color: Color(0xFFC9E4D2), fontSize: 16, height: 1.5)),
                const SizedBox(height: 38),
                const SizedBox(width: 150, child: LinearProgressIndicator(minHeight: 3, color: Color(0xFF9BE56C), backgroundColor: Color(0xFF37634E))),
                const SizedBox(height: 14),
                const Text('Getting your shop ready', style: TextStyle(color: Color(0xFFAAC9B6), fontSize: 12)),
              ]),
            )),
            const Positioned(bottom: 24, left: 0, right: 0, child: Text('DISCOVER LOCAL. SHOP WITH EASE.', textAlign: TextAlign.center, style: TextStyle(color: Color(0xFF91B49F), fontSize: 10, letterSpacing: 1.6))),
          ])),
        ),
      ),
    );
  }

  Widget _glow(double size) => Container(width: size, height: size,
    decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: const Color(0x184DBE7C), width: 30)));
}
