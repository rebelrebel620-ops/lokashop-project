import 'dart:async';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';
import '../services/contracts.dart';

/// Fixed gallery frame and deliberately small set of playback controls.
class SimpleVideo extends StatefulWidget {
  final String url;
  final Map<String, String> headers;
  final bool active;
  const SimpleVideo({
    super.key,
    required this.url,
    this.headers = const {},
    this.active = true,
  });
  @override
  State<SimpleVideo> createState() => _SimpleVideoState();
}

class _SimpleVideoState extends State<SimpleVideo> with WidgetsBindingObserver {
  VideoPlayerController? controller;
  http.Client? downloadClient;
  Directory? cachedDirectory;
  String? error;
  bool ready = false;
  int loadVersion = 0;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    if (widget.active) load();
  }

  Future<void> removeCache(Directory? directory) async {
    if (directory == null) return;
    try {
      if (await directory.exists()) await directory.delete(recursive: true);
    } catch (_) {}
  }

  Future<File> download(Uri uri, int version) async {
    final client = http.Client();
    downloadClient = client;
    Directory? directory;
    try {
      final response = await client
          .send(http.Request('GET', uri)..headers.addAll(widget.headers))
          .timeout(const Duration(seconds: 30));
      if (response.statusCode != 200)
        throw HttpException('Video unavailable (${response.statusCode})');
      final type = response.headers['content-type']?.toLowerCase() ?? '';
      if (type.contains('text/') || type.contains('json'))
        throw const FormatException('Video response was not media');
      const limit = 50 * 1024 * 1024;
      if ((response.contentLength ?? 0) > limit)
        throw const FormatException('Video is too large');
      directory = await Directory.systemTemp.createTemp('loka-video-');
      if (!mounted || version != loadVersion)
        throw const HttpException('Playback cancelled');
      final file = File(
        '${directory.path}/video.${type.contains('webm') ? 'webm' : 'mp4'}',
      );
      var bytes = 0;
      await response.stream
          .map((chunk) {
            bytes += chunk.length;
            if (bytes > limit)
              throw const FormatException('Video is too large');
            return chunk;
          })
          .pipe(file.openWrite())
          .timeout(const Duration(minutes: 3));
      if (bytes == 0 || !mounted || version != loadVersion)
        throw const HttpException('Playback cancelled');
      cachedDirectory = directory;
      return file;
    } catch (_) {
      await removeCache(directory);
      rethrow;
    } finally {
      client.close();
      if (downloadClient == client) downloadClient = null;
    }
  }

  Future<void> load() async {
    final version = ++loadVersion;
    downloadClient?.close();
    downloadClient = null;
    final old = controller;
    final oldDirectory = cachedDirectory;
    controller = null;
    cachedDirectory = null;
    ready = false;
    error = null;
    await old?.dispose();
    await removeCache(oldDirectory);
    if (!mounted || version != loadVersion) return;
    VideoPlayerController? current;
    try {
      final uri = Uri.parse(mediaUrl(widget.url));
      if (!['http', 'https'].contains(uri.scheme))
        throw const FormatException('Invalid video address');
      current = VideoPlayerController.networkUrl(
        uri,
        httpHeaders: widget.headers,
      );
      controller = current;
      try {
        await current.initialize().timeout(const Duration(seconds: 20));
      } catch (_) {
        await current.dispose();
        if (!mounted || version != loadVersion) return;
        controller = null;
        final file = await download(uri, version);
        if (!mounted || version != loadVersion) return;
        current = VideoPlayerController.file(file);
        controller = current;
        await current.initialize().timeout(const Duration(seconds: 20));
      }
      if (!mounted || version != loadVersion || controller != current) return;
      setState(() => ready = true);
    } catch (_) {
      if (mounted && version == loadVersion)
        setState(
          () => error =
              'Unable to play this video. Check your connection and retry.',
        );
    }
  }

  Future<void> toggle() async {
    final current = controller;
    if (!ready || current == null) return;
    try {
      if (current.value.isPlaying) {
        await current.pause();
      } else if (widget.active) {
        if (current.value.position >= current.value.duration)
          await current.seekTo(Duration.zero);
        await current.play();
      }
    } catch (_) {
      if (mounted)
        setState(() => error = 'Unable to play this video. Please retry.');
    }
  }

  @override
  void didUpdateWidget(covariant SimpleVideo oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.url != widget.url && widget.active) {
      load();
    } else if (!widget.active) {
      controller?.pause();
    } else if (!oldWidget.active && controller == null) {
      load();
    }
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!(ModalRoute.of(context)?.isCurrent ?? true)) controller?.pause();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state != AppLifecycleState.resumed) controller?.pause();
  }

  @override
  void dispose() {
    loadVersion++;
    WidgetsBinding.instance.removeObserver(this);
    downloadClient?.close();
    final old = controller;
    final directory = cachedDirectory;
    controller = null;
    cachedDirectory = null;
    (() async {
      await old?.dispose();
      await removeCache(directory);
    })();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AspectRatio(
    aspectRatio: 1,
    child: ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: ColoredBox(
        color: const Color(0xFF123E32),
        child: error != null
            ? Center(
                child: Padding(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(error!, style: const TextStyle(color: Colors.white)),
                      TextButton(
                        onPressed: () {
                          setState(() {
                            error = null;
                            ready = false;
                          });
                          load();
                        },
                        child: const Text('Retry'),
                      ),
                    ],
                  ),
                ),
              )
            : !ready || controller == null
            ? Center(
                child: widget.active
                    ? const CircularProgressIndicator(color: Colors.white)
                    : const Icon(
                        Icons.play_circle_outline,
                        color: Colors.white,
                        size: 48,
                      ),
              )
            : ValueListenableBuilder<VideoPlayerValue>(
                valueListenable: controller!,
                builder: (context, value, _) => Stack(
                  alignment: Alignment.center,
                  children: [
                    Center(
                      child: AspectRatio(
                        aspectRatio: value.aspectRatio > 0
                            ? value.aspectRatio
                            : 1,
                        child: VideoPlayer(controller!),
                      ),
                    ),
                    if (!value.isPlaying)
                      IconButton.filled(
                        onPressed: toggle,
                        iconSize: 42,
                        icon: const Icon(Icons.play_arrow),
                        tooltip: 'Play video',
                      ),
                    Positioned(
                      left: 10,
                      right: 10,
                      bottom: 8,
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          FilledButton.tonal(
                            onPressed: toggle,
                            child: Text(value.isPlaying ? 'Pause' : 'Play'),
                          ),
                          FilledButton.tonal(
                            onPressed: () => controller!.setVolume(
                              value.volume == 0 ? 1 : 0,
                            ),
                            child: Text(value.volume == 0 ? 'Unmute' : 'Mute'),
                          ),
                        ],
                      ),
                    ),
                    if (value.hasError)
                      Positioned(
                        top: 10,
                        left: 10,
                        right: 10,
                        child: TextButton(
                          onPressed: () {
                            setState(() => ready = false);
                            load();
                          },
                          child: const Text(
                            'Playback failed. Tap to retry.',
                            style: TextStyle(color: Colors.white),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
      ),
    ),
  );
}
