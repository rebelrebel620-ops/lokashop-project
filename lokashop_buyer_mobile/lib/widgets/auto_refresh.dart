import 'dart:async';
import 'package:flutter/material.dart';

/// Polls visible read-only data, never submitting forms in the background.
mixin AutoRefresh<T extends StatefulWidget> on State<T> {
  late final _VisibleRefresh _refresh;
  bool get autoRefreshEnabled => true;
  Future<void> refreshAutomatically();
  @override
  void initState() {
    super.initState();
    _refresh = _VisibleRefresh(
      visible: () =>
          mounted &&
          autoRefreshEnabled &&
          (ModalRoute.of(context)?.isCurrent ?? false),
      refresh: refreshAutomatically,
    )..start();
  }

  @override
  void dispose() {
    _refresh.dispose();
    super.dispose();
  }
}

class _VisibleRefresh with WidgetsBindingObserver {
  final bool Function() visible;
  final Future<void> Function() refresh;
  Timer? timer;
  bool foreground = true, running = false, disposed = false;
  _VisibleRefresh({required this.visible, required this.refresh});
  void start() {
    WidgetsBinding.instance.addObserver(this);
    foreground =
        WidgetsBinding.instance.lifecycleState == null ||
        WidgetsBinding.instance.lifecycleState == AppLifecycleState.resumed;
    timer = Timer.periodic(const Duration(seconds: 20), (_) => tick());
  }

  Future<void> tick() async {
    if (disposed || !foreground || running || !visible()) return;
    running = true;
    try {
      await refresh();
    } catch (_) {
      // Keep the last successful view during a temporary network interruption.
    } finally {
      running = false;
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    foreground = state == AppLifecycleState.resumed;
    if (foreground) tick();
  }

  void dispose() {
    disposed = true;
    timer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
  }
}
