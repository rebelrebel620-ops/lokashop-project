import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:webview_flutter_android/webview_flutter_android.dart';
import '../session.dart';
import 'profile_screen.dart';

/// Uses the same authenticated role pages and permissions as logistics web.
class WorkspaceScreen extends StatefulWidget {
  final Session session;
  final bool authenticated;
  final String initialPath, title;
  const WorkspaceScreen({
    super.key,
    required this.session,
    this.authenticated = true,
    this.initialPath = '/dashboard',
    this.title = 'Logistics workspace',
  });
  @override
  State<WorkspaceScreen> createState() => _WorkspaceScreenState();
}

class _WorkspaceScreenState extends State<WorkspaceScreen> {
  late final WebViewController controller;
  bool loading = true, initialized = false;
  String? error;
  int version = 0;
  Uri get origin => Uri.parse(widget.session.serverUrl);
  @override
  void initState() {
    super.initState();
    controller = WebViewController();
    start();
  }

  Future<void> start() async {
    final attempt = ++version;
    setState(() {
      loading = true;
      error = null;
    });
    try {
      if (!initialized) {
        await controller.setJavaScriptMode(JavaScriptMode.unrestricted);
        await controller.setBackgroundColor(const Color(0xFFF5F8F5));
        await controller.setNavigationDelegate(
          NavigationDelegate(
            onNavigationRequest: (request) async {
              final uri = Uri.tryParse(request.url);
              if (uri == null ||
                  !['http', 'https'].contains(uri.scheme) ||
                  uri.origin != origin.origin) {
                return NavigationDecision.prevent;
              }
              if (widget.authenticated &&
                  request.isMainFrame &&
                  uri.path == '/profile') {
                if (mounted) {
                  await Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => ProfileScreen(session: widget.session),
                    ),
                  );
                }
                return NavigationDecision.prevent;
              }
              if (widget.authenticated &&
                  request.isMainFrame &&
                  (uri.queryParameters['format'] == 'pdf' ||
                      RegExp(r'^/center/documents/\d+$').hasMatch(uri.path))) {
                await saveDocument(uri);
                return NavigationDecision.prevent;
              }
              return NavigationDecision.navigate;
            },
            onPageStarted: (_) {
              if (mounted) {
                setState(() {
                  loading = true;
                  error = null;
                });
              }
            },
            onPageFinished: (_) async {
              if (mounted) setState(() => loading = false);
              await controller.runJavaScript(
                "document.querySelectorAll('a[target=\"_blank\"]').forEach(a => a.target = '_self');",
              );
            },
            onWebResourceError: (failure) {
              if (mounted && failure.isForMainFrame == true) {
                setState(() {
                  error =
                      'Could not load logistics. Check the connection and retry.';
                  loading = false;
                });
              }
            },
          ),
        );
        final platform = controller.platform;
        if (platform is AndroidWebViewController) {
          await platform.setOnShowFileSelector((params) async {
            final result = await FilePicker.platform.pickFiles(
              allowMultiple: params.mode == FileSelectorMode.openMultiple,
            );
            return result?.files
                    .where((f) => f.path != null)
                    .map((f) => Uri.file(f.path!).toString())
                    .toList() ??
                [];
          });
        }
        initialized = true;
      }
      await widget.session.api.ping();
      if (widget.authenticated) await WebViewCookieManager().clearCookies();
      if (widget.authenticated) {
        final ticket = await widget.session.api.post(
          '/api/mobile/workspace-ticket',
          {},
        );
        if (!mounted || attempt != version) return;
        // Android's POST WebView request uses a form body; no custom auth headers.
        await controller.loadRequest(
          origin.resolve('/mobile/session'),
          method: LoadRequestMethod.post,
          body: Uint8List.fromList(
            'ticket=${Uri.encodeQueryComponent('${ticket['ticket']}')}'
                .codeUnits,
          ),
        );
      } else {
        if (!mounted || attempt != version) return;
        await controller.loadRequest(origin.resolve(widget.initialPath));
      }
    } catch (e) {
      if (mounted && attempt == version) {
        setState(() {
          error = '$e';
          loading = false;
        });
      }
    }
  }

  Future<void> saveDocument(Uri uri) async {
    try {
      final path = uri.path.startsWith('/center/documents/')
          ? '/api/mobile/documents/${uri.pathSegments.last}'
          : '/api/mobile/report';
      final result = await widget.session.api.download(
        path,
        uri.queryParameters,
      );
      final saved = await FilePicker.platform.saveFile(
        fileName: result.filename,
        bytes: result.bytes,
      );
      if (mounted && saved != null) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('File saved.')));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  Future<void> signOut() async {
    final yes = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Sign out of logistics?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('No'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Yes, sign out'),
          ),
        ],
      ),
    );
    if (yes == true) {
      await WebViewCookieManager().clearCookies();
      await widget.session.logout();
    }
  }

  Map<String, String> get sections => widget.session.rider?.isCenter == true
      ? {
          'Overview': '/dashboard',
          'Pickups': '/center/pickups',
          'Deliveries': '/center/delivery',
          'Transfers': '/ops/transfers',
          'History': '/center/history',
          'Setup & couriers': '/center/setup',
          'Areas': '/center/areas',
          'Applications': '/center/approvals',
          'Reports': '/center/reports',
          'Messages': '/messages',
          'Account': '/profile',
        }
      : {
          'Overview': '/dashboard',
          'Trips': '/driver/trips',
          'Availability': '/ops/availability',
          'Vehicle': '/ops/vehicle',
          'Earnings': '/rider/earnings',
          'Messages': '/messages',
          'Account': '/profile',
        };

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: false,
    onPopInvokedWithResult: (didPop, result) async {
      if (didPop) return;
      if (await controller.canGoBack()) {
        await controller.goBack();
      } else if (context.mounted && Navigator.canPop(context)) {
        Navigator.pop(context);
      }
    },
    child: Scaffold(
      appBar: AppBar(
        title: Text(widget.title),
        actions: [
          if (widget.authenticated)
            PopupMenuButton<String>(
              tooltip: 'Workspace sections',
              onSelected: (path) async {
                if (path == '/profile') {
                  await Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => ProfileScreen(session: widget.session),
                    ),
                  );
                } else {
                  await controller.loadRequest(origin.resolve(path));
                }
              },
              itemBuilder: (_) => sections.entries
                  .map((e) => PopupMenuItem(value: e.value, child: Text(e.key)))
                  .toList(),
              icon: const Icon(Icons.grid_view_outlined),
            ),
          IconButton(
            tooltip: 'Reconnect',
            onPressed: start,
            icon: const Icon(Icons.refresh),
          ),
          if (widget.authenticated)
            IconButton(
              tooltip: 'Sign out',
              onPressed: signOut,
              icon: const Icon(Icons.logout),
            ),
        ],
      ),
      body: Column(
        children: [
          if (loading) const LinearProgressIndicator(),
          if (error != null)
            Expanded(
              child: Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.cloud_off_outlined, size: 44),
                      const SizedBox(height: 12),
                      Text(error!, textAlign: TextAlign.center),
                      const SizedBox(height: 16),
                      FilledButton(
                        onPressed: start,
                        child: const Text('Retry connection'),
                      ),
                    ],
                  ),
                ),
              ),
            )
          else
            Expanded(child: WebViewWidget(controller: controller)),
        ],
      ),
    ),
  );
}
