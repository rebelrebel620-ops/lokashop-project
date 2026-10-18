import 'dart:async';
import 'package:flutter/material.dart';
import '../session.dart';
import '../models.dart';
import '../widgets/assignment_card.dart';
import '../widgets/common.dart';

class AssignmentsScreen extends StatefulWidget {
  final Session session;
  final VoidCallback onScan;
  const AssignmentsScreen({
    super.key,
    required this.session,
    required this.onScan,
  });
  @override
  State<AssignmentsScreen> createState() => _AssignmentsScreenState();
}

class _AssignmentsScreenState extends State<AssignmentsScreen>
    with WidgetsBindingObserver {
  List<Assignment> rows = [];
  Object? error;
  bool loading = true;
  String filter = 'active';
  int page = 1, last = 1;
  Timer? timer;
  bool foreground = true;
  int version = 0;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    load();
    timer = Timer.periodic(const Duration(seconds: 30), (_) {
      if (foreground && !loading) load(silent: true);
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState s) {
    foreground = s == AppLifecycleState.resumed;
  }

  @override
  void dispose() {
    timer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  Future<void> load({bool silent = false}) async {
    final v = ++version;
    if (!silent) setState(() => loading = true);
    try {
      final data = await widget.session.api.get('/api/rider/assignments', {
        'filter': filter,
        'page': '$page',
      });
      if (mounted && v == version) {
        setState(() {
          rows = (data['data'] as List? ?? [])
              .map((x) => Assignment.fromJson(Map<String, dynamic>.from(x)))
              .toList();
          last = (data['last_page'] as num? ?? 1).toInt();
          error = null;
        });
      }
    } catch (e) {
      if (mounted && v == version) setState(() => error = e);
    } finally {
      if (mounted && v == version) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Your assignments'),
      actions: [
        IconButton(
          onPressed: widget.onScan,
          icon: const Icon(Icons.qr_code_scanner),
          tooltip: 'Scan parcel',
        ),
        IconButton(
          onPressed: () => load(),
          icon: const Icon(Icons.refresh),
          tooltip: 'Refresh',
        ),
      ],
    ),
    body: Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: Wrap(
            spacing: 8,
            children: ['active', 'all', 'completed']
                .map(
                  (f) => ChoiceChip(
                    label: Text(prettyStatus(f)),
                    selected: filter == f,
                    onSelected: (_) {
                      setState(() {
                        filter = f;
                        page = 1;
                      });
                      load();
                    },
                  ),
                )
                .toList(),
          ),
        ),
        Expanded(
          child: loading
              ? const Center(child: CircularProgressIndicator())
              : error != null
              ? LoadError(error: error!, retry: () => load())
              : RefreshIndicator(
                  onRefresh: () => load(),
                  child: ListView(
                    padding: const EdgeInsets.only(bottom: 24),
                    children: [
                      if (rows.isEmpty)
                        const InfoCard(
                          title: 'No jobs here yet',
                          text:
                              'New offers appear after your sorting center assigns them. Go to Availability to get ready.',
                          icon: Icons.inventory_2_outlined,
                        ),
                      ...rows.map(
                        (a) => AssignmentCard(
                          assignment: a,
                          api: widget.session.api,
                          onUpdated: (_) => load(silent: true),
                        ),
                      ),
                      Padding(
                        padding: const EdgeInsets.all(16),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            IconButton(
                              onPressed: page > 1
                                  ? () {
                                      setState(() => page--);
                                      load();
                                    }
                                  : null,
                              icon: const Icon(Icons.chevron_left),
                            ),
                            Text('$page / $last'),
                            IconButton(
                              onPressed: page < last
                                  ? () {
                                      setState(() => page++);
                                      load();
                                    }
                                  : null,
                              icon: const Icon(Icons.chevron_right),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
        ),
      ],
    ),
  );
}
