import 'package:flutter/material.dart';
import '../theme.dart';

/// One responsive layout for buyer login, registration and verification.
class AuthLayout extends StatelessWidget {
  final String title, subtitle, badge;
  final Widget child;
  final bool back;
  final bool showBanner;
  const AuthLayout({
    super.key,
    required this.title,
    required this.subtitle,
    required this.child,
    this.badge = 'LOKASHOP BUYER',
    this.back = true,
    this.showBanner = false,
  });

  @override
  Widget build(BuildContext context) => Scaffold(
    body: DecoratedBox(
      decoration: BoxDecoration(
        image: showBanner
            ? const DecorationImage(
                image: AssetImage(
                  'assets/images/buyer-auth-mint-background.png',
                ),
                fit: BoxFit.cover,
                alignment: Alignment.center,
              )
            : null,
        gradient: showBanner
            ? null
            : const LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  Color(0xFFE6F1EA),
                  Color(0xFFF8FAF7),
                  Color(0xFFF1F4E4),
                ],
              ),
      ),
      child: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 560),
            child: SingleChildScrollView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              padding: const EdgeInsets.all(22),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      if (back && Navigator.canPop(context))
                        BackButton(
                          onPressed: () => Navigator.maybePop(context),
                        ),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(14),
                        child: Image.asset(
                          'assets/lokashop_logo.png',
                          width: 48,
                          height: 48,
                        ),
                      ),
                      const SizedBox(width: 12),
                      const Text(
                        'LokaShop',
                        style: TextStyle(
                          fontSize: 25,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const Spacer(),
                      const Icon(
                        Icons.shopping_bag_outlined,
                        color: AppColors.primary,
                      ),
                    ],
                  ),
                  const SizedBox(height: 26),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(26),
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        color: AppColors.primaryDark,
                        image: showBanner
                            ? const DecorationImage(
                                image: AssetImage(
                                  'assets/images/buyer-shopping-banner.png',
                                ),
                                fit: BoxFit.cover,
                                alignment: Alignment.centerRight,
                              )
                            : null,
                      ),
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            colors: showBanner
                                ? const [
                                    Color(0xED103B2B),
                                    Color(0x80103B2B),
                                    Color(0x10103B2B),
                                  ]
                                : const [
                                    AppColors.primaryDark,
                                    AppColors.primary,
                                    AppColors.primary,
                                  ],
                            stops: const [0, 0.65, 1],
                          ),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.all(25),
                          child: FractionallySizedBox(
                            widthFactor: showBanner ? 0.80 : 1,
                            alignment: Alignment.centerLeft,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  badge,
                                  style: const TextStyle(
                                    color: AppColors.accent,
                                    fontSize: 10,
                                    letterSpacing: 1.8,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                const SizedBox(height: 14),
                                Text(
                                  title,
                                  style: const TextStyle(
                                    fontSize: 29,
                                    height: 1.15,
                                    fontWeight: FontWeight.w900,
                                    color: Colors.white,
                                  ),
                                ),
                                const SizedBox(height: 12),
                                Text(
                                  subtitle,
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 12,
                                    height: 1.5,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  Container(
                    padding: const EdgeInsets.all(22),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(26),
                      border: Border.all(color: AppColors.line),
                    ),
                    child: child,
                  ),
                  const SizedBox(height: 22),
                  const Text(
                    'Local shops. Everyday finds. Delivered to you.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: AppColors.muted, fontSize: 12),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    ),
  );
}

class AuthSection extends StatelessWidget {
  final IconData icon;
  final String title, subtitle;
  const AuthSection({
    super.key,
    required this.icon,
    required this.title,
    required this.subtitle,
  });
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 8, bottom: 18),
    child: Row(
      children: [
        Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: const Color(0xFFECF4EF),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(icon, color: AppColors.primary, size: 21),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                subtitle,
                style: const TextStyle(fontSize: 12, color: AppColors.muted),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}
