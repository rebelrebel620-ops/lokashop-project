import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:shimmer/shimmer.dart';
import '../theme.dart';

final _peso = NumberFormat.currency(locale: 'en_PH', symbol: '₱');
String money(dynamic v) => _peso.format(double.tryParse('$v') ?? 0);

/// Human label + color for an order/parcel status string.
class StatusInfo {
  final String label;
  final Color color;
  const StatusInfo(this.label, this.color);
}

StatusInfo statusInfo(String status) {
  switch (status) {
    case 'PLACED':
      return const StatusInfo('Order placed', AppColors.accent);
    case 'CONFIRMED':
      return const StatusInfo('Confirmed by seller', AppColors.accent);
    case 'PREPARING':
      return const StatusInfo('Preparing your order', AppColors.accent);
    case 'READY_FOR_PICKUP':
      return const StatusInfo('Ready for pickup', AppColors.accent);
    case 'PICKED_UP':
      return const StatusInfo('Picked up by rider', AppColors.primary);
    case 'AT_SORTING_CENTER':
      return const StatusInfo('At sorting center', AppColors.primary);
    case 'SORTED':
      return const StatusInfo('Sorted', AppColors.primary);
    case 'ASSIGNED_TO_RIDER':
      return const StatusInfo('Assigned to rider', AppColors.primary);
    case 'OUT_FOR_DELIVERY':
      return const StatusInfo('Out for delivery', AppColors.primary);
    case 'DELIVERED':
      return const StatusInfo('Delivered', AppColors.success);
    case 'COMPLETED':
      return const StatusInfo('Completed', AppColors.success);
    case 'DELIVERY_FAILED':
      return const StatusInfo('Delivery failed', AppColors.danger);
    case 'RETURNED':
      return const StatusInfo('Returned', AppColors.danger);
    default:
      return StatusInfo(status, AppColors.muted);
  }
}

int stepOf(String status) {
  switch (status) {
    case 'PLACED':
      return 1;
    case 'CONFIRMED':
    case 'PREPARING':
      return 2;
    case 'READY_FOR_PICKUP':
    case 'PICKED_UP':
      return 3;
    case 'AT_SORTING_CENTER':
    case 'SORTED':
    case 'ASSIGNED_TO_RIDER':
      return 4;
    case 'OUT_FOR_DELIVERY':
      return 5;
    case 'DELIVERED':
    case 'COMPLETED':
      return 6;
    default:
      return 1;
  }
}

class SectionHeader extends StatelessWidget {
  final String title;
  final String? action;
  final VoidCallback? onAction;
  const SectionHeader({super.key, required this.title, this.action, this.onAction});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 4, 4, 10),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
          if (action != null)
            GestureDetector(
              onTap: onAction,
              child: Text(action!, style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700)),
            ),
        ],
      ),
    );
  }
}

class EmptyState extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final Widget? action;
  const EmptyState({super.key, required this.icon, required this.title, required this.subtitle, this.action});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(color: AppColors.primary.withOpacity(.08), shape: BoxShape.circle),
              child: Icon(icon, size: 40, color: AppColors.primary),
            ),
            const SizedBox(height: 18),
            Text(title, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800), textAlign: TextAlign.center),
            const SizedBox(height: 6),
            Text(subtitle, style: const TextStyle(color: AppColors.muted), textAlign: TextAlign.center),
            if (action != null) ...[const SizedBox(height: 18), action!],
          ],
        ),
      ),
    );
  }
}

class ShimmerBlock extends StatelessWidget {
  final double height;
  final double? width;
  final BorderRadius? radius;
  const ShimmerBlock({super.key, required this.height, this.width, this.radius});

  @override
  Widget build(BuildContext context) {
    return Shimmer.fromColors(
      baseColor: AppColors.line,
      highlightColor: const Color(0xFFF3F4F0),
      child: Container(
        height: height,
        width: width,
        decoration: BoxDecoration(color: Colors.white, borderRadius: radius ?? BorderRadius.circular(14)),
      ),
    );
  }
}

class AppSnack {
  static void show(BuildContext context, String message, {bool error = false}) {
    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(message),
      backgroundColor: error ? AppColors.danger : AppColors.ink,
    ));
  }
}

/// A soft gradient "image" placeholder for products (the backend has no
/// product photos), seeded by product id/name so each item looks distinct.
class ProductArt extends StatelessWidget {
  final String seed;
  final BorderRadius? radius;
  const ProductArt({super.key, required this.seed, this.radius});

  @override
  Widget build(BuildContext context) {
    final hash = seed.codeUnits.fold<int>(0, (a, b) => a + b);
    final palettes = [
      [const Color(0xFF1F6B4C), const Color(0xFF3E9C72)],
      [const Color(0xFFE08A2E), const Color(0xFFF4B860)],
      [const Color(0xFF2C5F8A), const Color(0xFF5B95C7)],
      [const Color(0xFF8A4B6B), const Color(0xFFC77BA0)],
      [const Color(0xFF6B7A2E), const Color(0xFFA3B95C)],
    ];
    final colors = palettes[hash % palettes.length];
    return ClipRRect(
      borderRadius: radius ?? BorderRadius.circular(16),
      child: Container(
        decoration: BoxDecoration(gradient: LinearGradient(colors: colors, begin: Alignment.topLeft, end: Alignment.bottomRight)),
        child: Center(
          child: Icon(Icons.storefront_rounded, color: Colors.white.withOpacity(.85), size: 34),
        ),
      ),
    );
  }
}
