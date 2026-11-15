import 'package:flutter/material.dart';

class PhotoViewer extends StatelessWidget {
  final String url;
  final Map<String, String> headers;
  const PhotoViewer({super.key, required this.url, required this.headers});
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Original delivery photo')),
    body: Center(
      child: InteractiveViewer(
        minScale: 0.5,
        maxScale: 5,
        child: Image.network(
          url,
          headers: headers,
          cacheWidth: 2048,
          fit: BoxFit.contain,
          errorBuilder: (_, _, _) => const Text(
            'Photo unavailable. Check your connection and access.',
          ),
        ),
      ),
    ),
  );
}
