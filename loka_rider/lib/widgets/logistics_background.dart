import 'package:flutter/material.dart';
import '../models.dart';

/// Decorative local assets only; never captures gestures or adds navigation.
class LogisticsBackground extends StatelessWidget {
  static const sortingCenter = 'assets/backgrounds/sorting_center.png';
  static const driver = 'assets/backgrounds/driver.png';
  static const rider = 'assets/backgrounds/rider.png';
  static const login = 'assets/backgrounds/login.png';
  static const register = 'assets/backgrounds/register.png';
  static const loginBanner = 'assets/backgrounds/login_banner.png';

  static String? forUser(Rider? user) {
    if (user == null) return null;
    if (user.isCenter) return sortingCenter;
    if (user.isDriver) return driver;
    return rider;
  }

  final String? asset;
  final Widget child;
  final double veilOpacity;
  const LogisticsBackground({super.key, required this.child, this.asset, this.veilOpacity = 0.84});

  @override
  Widget build(BuildContext context) => Stack(
    fit: StackFit.expand,
    children: [
      Positioned.fill(child: IgnorePointer(child: ExcludeSemantics(child: RepaintBoundary(
        child: ColoredBox(
          color: const Color(0xFFF6F7F2),
          child: asset == null ? const SizedBox.expand() : Image.asset(
            asset!, fit: BoxFit.cover, alignment: Alignment.center,
            cacheWidth: 1000,
            errorBuilder: (_, error, stackTrace) => const SizedBox.expand(),
          ),
        ),
      )))),
      Positioned.fill(child: IgnorePointer(child: ColoredBox(
        color: const Color(0xFFF6F7F2).withValues(alpha: veilOpacity),
      ))),
      child,
    ],
  );
}

/// Shared background for signup selection, application steps and email verification.
class RegistrationScaffold extends StatelessWidget {
  final PreferredSizeWidget? appBar;
  final Widget body;
  const RegistrationScaffold({super.key, this.appBar, required this.body});

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Colors.transparent,
    appBar: appBar,
    body: LogisticsBackground(
      asset: LogisticsBackground.register,
      veilOpacity: 0.65,
      child: SafeArea(
        top: false,
        child: Container(
          margin: const EdgeInsets.all(14),
          clipBehavior: Clip.antiAlias,
          decoration: BoxDecoration(
            color: const Color(0xF5FFFFFF),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFDCE6DB)),
          ),
          child: body,
        ),
      ),
    ),
  );
}
