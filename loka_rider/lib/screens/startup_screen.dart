import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Shown only while the existing session initialization is in progress.
/// No timer, fixed delay, authentication or navigation is added here.
class StartupScreen extends StatelessWidget {
  const StartupScreen({super.key});

  @override
  Widget build(BuildContext context) => AnnotatedRegion<SystemUiOverlayStyle>(
    value: const SystemUiOverlayStyle(
      statusBarColor: Color(0xFF123E32),
      statusBarIconBrightness: Brightness.light,
      statusBarBrightness: Brightness.dark,
      systemNavigationBarColor: Color(0xFF123E32),
      systemNavigationBarIconBrightness: Brightness.light,
    ),
    child: Scaffold(
      backgroundColor: const Color(0xFF123E32),
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft, end: Alignment.bottomRight,
            colors: [Color(0xFF123E32), Color(0xFF1D5743), Color(0xFF0C3027)],
          ),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 36),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 440),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: const Color(0x18FFFFFF),
                        border: Border.all(color: const Color(0x38FFFFFF)),
                        borderRadius: BorderRadius.circular(30),
                      ),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(22),
                        child: Image.asset(
                          'assets/icons/rider_logo.png', width: 100, height: 100,
                          fit: BoxFit.contain,
                          errorBuilder: (_, error, stackTrace) => const SizedBox(
                            width: 100, height: 100,
                            child: Icon(Icons.local_shipping_outlined, color: Colors.white, size: 64),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 26),
                    const Text('LokaShop', textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.white, fontSize: 36, fontWeight: FontWeight.w800, letterSpacing: -1)),
                    const SizedBox(height: 6),
                    const Text('LOGISTICS', textAlign: TextAlign.center,
                      style: TextStyle(color: Color(0xFFDDECBB), fontSize: 13, letterSpacing: 3.5, fontWeight: FontWeight.w700)),
                    const SizedBox(height: 22),
                    const Text('Move parcels. Connect communities.', textAlign: TextAlign.center,
                      style: TextStyle(color: Color(0xFFD2E5D9), fontSize: 14, height: 1.5)),
                    const SizedBox(height: 46),
                    const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(
                      strokeWidth: 2.5, color: Color(0xFFC4EF9A),
                      semanticsLabel: 'Loading your logistics workspace',
                    )),
                    const SizedBox(height: 16),
                    const Text('Preparing your workspace…', textAlign: TextAlign.center,
                      style: TextStyle(color: Color(0xFFE5EFE8), fontSize: 12)),
                    const SizedBox(height: 44),
                    const Text('Sorting Center • Driver • Rider', textAlign: TextAlign.center,
                      style: TextStyle(color: Color(0xFFB6CFC1), fontSize: 11)),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    ),
  );
}
