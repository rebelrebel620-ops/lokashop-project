import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import '../services/api_service.dart';
import '../theme.dart';

class SheetCard extends StatelessWidget {
  final Widget child;
  final EdgeInsets padding;
  const SheetCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(20),
  });
  @override
  Widget build(BuildContext context) => Container(
    padding: padding,
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(22),
      border: Border.all(color: AppColors.line),
    ),
    child: child,
  );
}

class Eyebrow extends StatelessWidget {
  final String text;
  const Eyebrow(this.text, {super.key});
  @override
  Widget build(BuildContext context) => Text(
    text.toUpperCase(),
    style: const TextStyle(
      letterSpacing: 2,
      fontSize: 10,
      fontWeight: FontWeight.w800,
      color: AppColors.muted,
    ),
  );
}

class SectionHeading extends StatelessWidget {
  final String title;
  final Widget? trailing;
  const SectionHeading(this.title, {super.key, this.trailing});
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 16),
    child: Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 19),
          ),
        ),
        if (trailing != null) trailing!,
      ],
    ),
  );
}

class PageBody extends StatelessWidget {
  final Widget child;
  const PageBody({super.key, required this.child});
  @override
  Widget build(BuildContext context) => Align(
    alignment: Alignment.topCenter,
    child: ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 940),
      child: child,
    ),
  );
}

class AppPhoto extends StatelessWidget {
  final String? url;
  final double? width, height;
  final BoxFit fit;
  final bool private;
  const AppPhoto(
    this.url, {
    super.key,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.private = false,
  });
  @override
  Widget build(BuildContext context) {
    final api = context.read<AppState>().api;
    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: mediaUrl(url).isEmpty
          ? Container(
              width: width,
              height: height,
              color: const Color(0xFFEBF0E3),
              alignment: Alignment.center,
              child: const Icon(
                Icons.inventory_2_outlined,
                color: Color(0xFF819B76),
                size: 34,
              ),
            )
          : Image.network(
              mediaUrl(url),
              width: width,
              height: height,
              fit: fit,
              headers: private ? api.imageHeaders(url!) : null,
              errorBuilder: (_, __, ___) => Container(
                width: width,
                height: height,
                color: const Color(0xFFEBF0E3),
                alignment: Alignment.center,
                child: const Icon(
                  Icons.image_not_supported_outlined,
                  color: AppColors.muted,
                ),
              ),
              loadingBuilder: (context, child, event) => event == null
                  ? child
                  : SizedBox(
                      width: width,
                      height: height,
                      child: const Center(
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                    ),
            ),
    );
  }
}

String? productPhoto(Map product) {
  if (product['image_url'] != null) return '${product['image_url']}';
  final urls = product['image_urls'];
  if (urls is List && urls.isNotEmpty) return '${urls.first}';
  try {
    final images = product['images'] is String
        ? jsonDecode(product['images'])
        : product['images'];
    if (images is List && images.isNotEmpty) return '${images.first}';
  } catch (_) {}
  return product['image']?.toString();
}

Future<bool> confirmAction(
  BuildContext context,
  String title,
  String body, {
  String label = 'Confirm',
}) async =>
    await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: Text(body),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Go back'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(label),
          ),
        ],
      ),
    ) ??
    false;
Future<String?> askText(
  BuildContext context,
  String title, {
  String hint = '',
  int minLength = 1,
}) async {
  final ctrl = TextEditingController();
  final value = await showDialog<String>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(title),
      content: TextField(
        controller: ctrl,
        minLines: 2,
        maxLines: 5,
        maxLength: 300,
        decoration: InputDecoration(hintText: hint),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(ctx),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: () {
            if (ctrl.text.trim().length >= minLength) {
              Navigator.pop(ctx, ctrl.text.trim());
            }
          },
          child: const Text('Continue'),
        ),
      ],
    ),
  );
  ctrl.dispose();
  return value;
}

class StatusBadge extends StatelessWidget {
  final String status;
  const StatusBadge({super.key, required this.status});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
    decoration: BoxDecoration(
      color: ['CANCELLED', 'DELIVERY_FAILED', 'RETURNED'].contains(status)
          ? const Color(0xFFFBECE7)
          : const Color(0xFFE8F0DE),
      borderRadius: BorderRadius.circular(30),
    ),
    child: Text(
      status.replaceAll('_', ' '),
      style: const TextStyle(
        fontSize: 10,
        fontWeight: FontWeight.w800,
        color: AppColors.ink,
      ),
    ),
  );
}
