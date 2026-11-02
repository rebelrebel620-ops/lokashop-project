import 'dart:async';
import 'package:flutter/material.dart';
import '../models.dart';
import '../session.dart';
import '../widgets/common.dart';
import 'assignments_screen.dart';
import 'scan_screen.dart';

class TripsScreen extends StatefulWidget {
  final Session session;
  final String initialTab;
  const TripsScreen({
    super.key,
    required this.session,
    this.initialTab = 'new',
  });
  @override
  State<TripsScreen> createState() => _TripsScreenState();
}

class _TripsScreenState extends State<TripsScreen> with WidgetsBindingObserver {
  Timer? timer;
  bool foreground = true, loading = true, busy = false;
  List<Map<String, dynamic>> trips = [];
  late String tab;
  int page = 1, last = 1, version = 0;
  Object? error;

  @override
  void initState() {
    super.initState();
    tab = widget.initialTab;
    WidgetsBinding.instance.addObserver(this);
    load();
    timer = Timer.periodic(const Duration(seconds: 30), (_) {
      if (foreground && !busy && !loading) load(silent: true);
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    foreground = state == AppLifecycleState.resumed;
    if (foreground && !busy) load(silent: true);
  }

  @override
  void dispose() {
    timer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  Future<void> load({bool silent = false}) async {
    if (!mounted) return;
    final attempt = ++version;
    if (!silent) {
      setState(() {
        loading = true;
        error = null;
      });
    }
    try {
      final result = await widget.session.api.get('/api/rider/trips', {
        'tab': tab,
        'page': '$page',
      });
      if (mounted && attempt == version) {
        setState(() {
          trips = (result['data'] as List? ?? [])
              .map((row) => Map<String, dynamic>.from(row as Map))
              .toList();
          last = int.tryParse('${result['last_page']}') ?? 1;
          error = null;
        });
      }
    } catch (e) {
      if (mounted && attempt == version) setState(() => error = e);
    } finally {
      if (mounted && attempt == version) setState(() => loading = false);
    }
  }

  Future<void> action(
    String path,
    String label,
    String explanation, {
    Map<String, dynamic> fields = const {},
    String nextTab = 'progress',
  }) async {
    if (busy) return;
    final yes = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('$label?'),
        content: Text(explanation),
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
    );
    if (yes != true || !mounted) return;
    setState(() => busy = true);
    try {
      await widget.session.api.post(path, fields);
      if (!mounted) return;
      setState(() {
        tab = nextTab;
        page = 1;
      });
      await load();
    } catch (e) {
      if (mounted) toast(context, e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  void individualTransfers() => Navigator.push(
    context,
    MaterialPageRoute(
      builder: (_) => AssignmentsScreen(
        session: widget.session,
        onScan: () => Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => ScanScreen(session: widget.session),
          ),
        ),
      ),
    ),
  );

  Widget tripCard(Map<String, dynamic> trip) {
    final stops = (trip['stops'] as List? ?? [])
        .map((s) => Map<String, dynamic>.from(s as Map))
        .toList();
    final parcels = stops.fold<int>(
      0,
      (n, stop) => n + (stop['parcels'] as List? ?? []).length,
    );
    final status = '${trip['status']}';
    return Card(
      margin: const EdgeInsets.only(bottom: 18),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(
                  Icons.local_shipping_outlined,
                  color: Color(0xFF256348),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Trip #${trip['id']}',
                    style: const TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 6,
              children: [
                Chip(label: Text(prettyStatus(status))),
                Chip(label: Text('$parcels parcels · ${stops.length} stops')),
              ],
            ),
            if (trip['origin_name'] != null)
              Text('From ${trip['origin_name']}'),
            if (status == 'offered') ...[
              Text(
                'Accept by ${shortDate('${trip['expires_at'] ?? ''}')}. All parcels are included in one trip.',
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 10,
                runSpacing: 8,
                children: [
                  FilledButton(
                    onPressed: busy
                        ? null
                        : () => action(
                            '/api/rider/trips/${trip['id']}/accept',
                            'Accept trip',
                            'Accept this entire manifest. Check the parcels and destination stops before departure.',
                          ),
                    child: const Text('Accept trip'),
                  ),
                  OutlinedButton(
                    onPressed: busy
                        ? null
                        : () => action(
                            '/api/rider/trips/${trip['id']}/decline',
                            'Decline trip',
                            'Return this offer to your sorting center so it can assign another driver.',
                            nextTab: 'new',
                          ),
                    child: const Text('Decline'),
                  ),
                ],
              ),
            ],
            if (status == 'accepted')
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: FilledButton.icon(
                  onPressed: busy
                      ? null
                      : () => action(
                          '/api/rider/trips/${trip['id']}/depart',
                          'Start transport',
                          'Confirm that every parcel on this manifest is loaded and you are leaving the origin sorting center.',
                        ),
                  icon: const Icon(Icons.route_outlined),
                  label: const Text('Start transport'),
                ),
              ),
            if (status == 'completed')
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Text(
                  'Completed earnings: ${peso(double.tryParse('${trip['completed_earnings']}') ?? 0)}',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
            ...stops.map((stop) => stopCard(trip, stop)),
          ],
        ),
      ),
    );
  }

  Widget stopCard(Map<String, dynamic> trip, Map<String, dynamic> stop) {
    final address = stop['address'] is Map
        ? Map<String, dynamic>.from(stop['address'] as Map)
        : <String, dynamic>{};
    final parcels = stop['parcels'] as List? ?? [];
    final received = parcels.where((p) => p['received_at'] != null).length;
    return Container(
      margin: const EdgeInsets.only(top: 18),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFF1F6EE),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Stop ${stop['position']} · ${stop['center_name']}',
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 5),
          Text('${stop['city']}, ${stop['province']}'),
          if (address.isNotEmpty) ...[
            Text(
              [
                address['street'],
                address['barangay'],
              ].where((v) => v != null && '$v'.isNotEmpty).join(', '),
            ),
            if (address['phone'] != null) Text('Contact: ${address['phone']}'),
          ],
          const Divider(height: 24),
          Text(
            '$received / ${parcels.length} parcels received',
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 8),
          ...parcels.map(
            (p) => Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(
                children: [
                  Icon(
                    p['received_at'] != null
                        ? Icons.check_circle_outline
                        : Icons.inventory_2_outlined,
                    size: 16,
                  ),
                  const SizedBox(width: 8),
                  Expanded(child: Text('${p['tracking_code']}')),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          if (stop['received_at'] != null)
            const Text(
              'Destination center confirmed receipt.',
              style: TextStyle(fontWeight: FontWeight.w700),
            )
          else if (stop['handed_over_at'] != null)
            const Text(
              'Handover recorded. Waiting for the destination center to confirm all remaining parcels.',
            )
          else if (trip['status'] == 'in_transit')
            FilledButton.icon(
              onPressed: busy
                  ? null
                  : () => action(
                      '/api/rider/trip-stops/${stop['id']}/handover',
                      'Confirm handover',
                      'Confirm that you physically handed these parcels to this destination sorting center. The center must confirm receipt separately.',
                      fields: {'confirm_handover': true},
                    ),
              icon: const Icon(Icons.handshake_outlined),
              label: const Text('Confirm handover'),
            ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Transport trips'),
      actions: [
        IconButton(
          onPressed: busy ? null : () => load(),
          tooltip: 'Refresh trips',
          icon: const Icon(Icons.refresh),
        ),
        PopupMenuButton<String>(
          enabled: !busy,
          tooltip: 'More trip options',
          onSelected: (_) => individualTransfers(),
          itemBuilder: (_) => const [
            PopupMenuItem(
              value: 'individual',
              child: Text('Individual transfers'),
            ),
          ],
        ),
      ],
    ),
    body: Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
          child: SizedBox(
            width: double.infinity,
            child: SegmentedButton<String>(
              segments: const [
                ButtonSegment(value: 'new', label: Text('Requests')),
                ButtonSegment(value: 'progress', label: Text('Ongoing')),
                ButtonSegment(value: 'completed', label: Text('Completed')),
              ],
              selected: {tab},
              onSelectionChanged: busy
                  ? null
                  : (values) {
                      setState(() {
                        tab = values.first;
                        page = 1;
                      });
                      load();
                    },
            ),
          ),
        ),
        Expanded(
          child: loading
              ? const Center(child: CircularProgressIndicator())
              : error != null && trips.isEmpty
              ? LoadError(error: error!, retry: () => load())
              : RefreshIndicator(
                  onRefresh: () => load(),
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                    children: [
                      if (error != null)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: Text(
                            'Could not refresh: $error',
                            style: TextStyle(
                              color: Theme.of(context).colorScheme.error,
                            ),
                          ),
                        ),
                      if (trips.isEmpty)
                        const InfoCard(
                          title: 'No trips in this stage',
                          text:
                              'Pull down to refresh. Trip requests appear when your center assigns a load.',
                          icon: Icons.local_shipping_outlined,
                        ),
                      ...trips.map(tripCard),
                      if (last > 1)
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            TextButton(
                              onPressed: page > 1 && !busy
                                  ? () {
                                      page--;
                                      load();
                                    }
                                  : null,
                              child: const Text('Previous'),
                            ),
                            Text('$page / $last'),
                            TextButton(
                              onPressed: page < last && !busy
                                  ? () {
                                      page++;
                                      load();
                                    }
                                  : null,
                              child: const Text('Next'),
                            ),
                          ],
                        ),
                    ],
                  ),
                ),
        ),
      ],
    ),
  );
}
