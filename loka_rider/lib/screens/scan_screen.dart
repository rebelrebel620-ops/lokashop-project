import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../api.dart';
import '../models.dart';
import '../session.dart';
import '../widgets/assignment_card.dart';

/// Scan the QR code on a shipping label. The server finds the parcel, checks it
/// belongs to this rider and returns the actions allowed right now
/// (accept / confirm pickup / out for delivery / delivered / failed).
class ScanScreen extends StatefulWidget {
  final Session session;
  const ScanScreen({super.key, required this.session});

  @override
  State<ScanScreen> createState() => _ScanScreenState();
}

class _ScanScreenState extends State<ScanScreen> with WidgetsBindingObserver {
  final MobileScannerController _controller = MobileScannerController(formats: const [BarcodeFormat.qrCode]);

  bool _busy = false; // a scan is being processed or its result sheet is open
  bool _loading = false; // waiting for the server
  String? _lastRaw;
  DateTime _ignoreUntil = DateTime.fromMillisecondsSinceEpoch(0);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _controller.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (!mounted) return;
    if (state == AppLifecycleState.resumed) {
      if (!_busy) _safe(_controller.start);
    } else if (state == AppLifecycleState.inactive || state == AppLifecycleState.paused) {
      _safe(_controller.stop);
    }
  }

  Future<void> _safe(Future<void> Function() action) async {
    try {
      await action();
    } catch (_) {
      // start()/stop() throw if the camera is already in that state - harmless.
    }
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_busy) return;
    String? raw;
    for (final b in capture.barcodes) {
      final v = b.rawValue;
      if (v != null && v.trim().isNotEmpty) {
        raw = v.trim();
        break;
      }
    }
    if (raw == null) return;
    // The label is usually still in view when the sheet closes - don't reopen it instantly.
    if (raw == _lastRaw && DateTime.now().isBefore(_ignoreUntil)) return;
    await _handle(raw);
  }

  Future<void> _handle(String raw) async {
    _busy = true;
    await _safe(_controller.stop);
    if (mounted) setState(() => _loading = true);

    try {
      final assignment = await widget.session.api.scan(raw);
      if (!mounted) return;
      setState(() => _loading = false);
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        showDragHandle: true,
        useSafeArea: true,
        builder: (_) => _ResultSheet(assignment: assignment, api: widget.session.api),
      );
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _loading = false);
        await showDialog<void>(
          context: context,
          builder: (ctx) => AlertDialog(
            icon: const Icon(Icons.error_outline),
            title: const Text('Cannot use this QR code'),
            content: Text(e.message),
            actions: [TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('OK'))],
          ),
        );
      }
    } finally {
      _lastRaw = raw;
      _ignoreUntil = DateTime.now().add(const Duration(seconds: 3));
      _busy = false;
      if (mounted) {
        setState(() => _loading = false);
        await _safe(_controller.start);
      }
    }
  }

  Future<void> _manualEntry() async {
    if (_busy) return;
    final code = await showDialog<String>(context: context, builder: (_) => const _CodeDialog());
    if (code != null && code.trim().isNotEmpty) await _handle(code.trim());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        title: const Text('Scan shipping label'),
        actions: [
          IconButton(tooltip: 'Enter code manually', icon: const Icon(Icons.keyboard_alt_outlined), onPressed: _manualEntry),
          IconButton(tooltip: 'Toggle flash', icon: const Icon(Icons.flash_on), onPressed: () => _safe(_controller.toggleTorch)),
          IconButton(tooltip: 'Switch camera', icon: const Icon(Icons.cameraswitch), onPressed: () => _safe(_controller.switchCamera)),
        ],
      ),
      body: Stack(
        fit: StackFit.expand,
        children: [
          MobileScanner(
            controller: _controller,
            onDetect: _onDetect,
            errorBuilder: (context, error, child) {
              final denied = error.errorCode == MobileScannerErrorCode.permissionDenied;
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(28),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.no_photography_outlined, size: 56, color: Colors.white70),
                      const SizedBox(height: 16),
                      Text(
                        denied
                            ? 'Camera permission is needed to scan parcels. Allow it in Android Settings \u2192 Apps \u2192 LokaShop Rider \u2192 Permissions.'
                            : 'The camera could not be started.',
                        textAlign: TextAlign.center,
                        style: const TextStyle(color: Colors.white),
                      ),
                      const SizedBox(height: 16),
                      OutlinedButton(
                        onPressed: _manualEntry,
                        style: OutlinedButton.styleFrom(foregroundColor: Colors.white),
                        child: const Text('Enter code manually'),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
          Center(
            child: Container(
              width: 250,
              height: 250,
              decoration: BoxDecoration(
                border: Border.all(color: Colors.white, width: 3),
                borderRadius: BorderRadius.circular(16),
              ),
            ),
          ),
          Positioned(
            bottom: 32,
            left: 16,
            right: 16,
            child: Text(
              'Point the camera at the QR code on the shipping label',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: Colors.white, fontWeight: FontWeight.w600),
            ),
          ),
          if (_loading)
            const ColoredBox(color: Colors.black54, child: Center(child: CircularProgressIndicator())),
        ],
      ),
    );
  }
}

class _ResultSheet extends StatefulWidget {
  final Assignment assignment;
  final ApiClient api;
  const _ResultSheet({required this.assignment, required this.api});

  @override
  State<_ResultSheet> createState() => _ResultSheetState();
}

class _ResultSheetState extends State<_ResultSheet> {
  late Assignment _current = widget.assignment;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom + 8),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          AssignmentCard(
            key: ValueKey('scan-${_current.id}'),
            assignment: _current,
            api: widget.api,
            onUpdated: (updated) => setState(() => _current = updated),
          ),
          if (_current.actions.isEmpty)
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 8, 24, 0),
              child: Text(
                'No action available for this parcel right now.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant),
              ),
            ),
          Padding(
            padding: const EdgeInsets.all(12),
            child: TextButton.icon(
              onPressed: () => Navigator.pop(context),
              icon: const Icon(Icons.qr_code_scanner),
              label: const Text('Scan next parcel'),
            ),
          ),
        ],
      ),
    );
  }
}

class _CodeDialog extends StatefulWidget {
  const _CodeDialog();

  @override
  State<_CodeDialog> createState() => _CodeDialogState();
}

class _CodeDialogState extends State<_CodeDialog> {
  final _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Enter tracking code'),
      content: TextField(
        controller: _controller,
        autofocus: true,
        textCapitalization: TextCapitalization.characters,
        decoration: const InputDecoration(hintText: 'LKS-XXXXXXXXXXXX', border: OutlineInputBorder()),
        onSubmitted: (v) => Navigator.pop(context, v),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
        FilledButton(onPressed: () => Navigator.pop(context, _controller.text), child: const Text('Find parcel')),
      ],
    );
  }
}
