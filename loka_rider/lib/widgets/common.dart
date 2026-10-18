import 'package:flutter/material.dart';

void toast(BuildContext context, Object message) => ScaffoldMessenger.of(
  context,
).showSnackBar(SnackBar(content: Text('$message')));

class InfoCard extends StatelessWidget {
  final String title;
  final String text;
  final IconData icon;
  final VoidCallback? onTap;
  const InfoCard({
    super.key,
    required this.title,
    required this.text,
    this.icon = Icons.info_outline,
    this.onTap,
  });
  @override
  Widget build(BuildContext context) => Card(
    child: ListTile(
      contentPadding: const EdgeInsets.all(18),
      leading: CircleAvatar(
        backgroundColor: const Color(0xFFEDF2E0),
        child: Icon(icon, color: const Color(0xFF123E32)),
      ),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
      subtitle: Text(text),
      onTap: onTap,
    ),
  );
}

class LoadError extends StatelessWidget {
  final Object error;
  final VoidCallback retry;
  const LoadError({super.key, required this.error, required this.retry});
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_outlined, size: 48),
          const SizedBox(height: 16),
          Text('$error', textAlign: TextAlign.center),
          const SizedBox(height: 16),
          FilledButton(onPressed: retry, child: const Text('Try again')),
        ],
      ),
    ),
  );
}
