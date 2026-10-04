import 'package:flutter/material.dart';

import '../api.dart';
import '../models.dart';
import '../session.dart';
import '../widgets/assignment_card.dart';

class AssignmentsScreen extends StatefulWidget {
  final Session session;
  final VoidCallback onScan;
  const AssignmentsScreen({super.key, required this.session, required this.onScan});

  @override
  State<AssignmentsScreen> createState() => _AssignmentsScreenState();
}

class _AssignmentsScreenState extends State<AssignmentsScreen> {
  List<Assignment>? _items;
  RiderStats _stats = const RiderStats();
  String _filter = 'active';
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = widget.session.api;
      final assignmentsFuture = api.assignments(filter: _filter);
      final meFuture = api.me();
      final items = await assignmentsFuture;
      final me = await meFuture;
      if (!mounted) return;
      setState(() {
        _items = items;
        _stats = me.stats;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _replace(Assignment updated) {
    final items = _items;
    if (items == null) return;
    setState(() {
      _items = [for (final a in items) a.id == updated.id ? updated : a];
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final name = widget.session.rider?.name ?? '';
    final items = _items;

    return Scaffold(
      appBar: AppBar(
        title: const Text('My jobs'),
        actions: [
          IconButton(tooltip: 'Scan parcel', icon: const Icon(Icons.qr_code_scanner), onPressed: widget.onScan),
          IconButton(tooltip: 'Refresh', icon: const Icon(Icons.refresh), onPressed: _loading ? null : _load),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.only(bottom: 24),
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
              child: Text('Good day, $name', style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
              child: Row(
                children: [
                  _StatCard(icon: Icons.inventory_2_outlined, value: '${_stats.pickupsAssigned}', label: 'Pickups assigned'),
                  _StatCard(icon: Icons.local_shipping_outlined, value: '${_stats.deliveriesInProgress}', label: 'Deliveries in progress'),
                  _StatCard(icon: Icons.check_circle_outline, value: '${_stats.completed}', label: 'Completed'),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: SegmentedButton<String>(
                segments: const [
                  ButtonSegment(value: 'active', label: Text('Active')),
                  ButtonSegment(value: 'completed', label: Text('Completed')),
                  ButtonSegment(value: 'all', label: Text('All')),
                ],
                selected: {_filter},
                onSelectionChanged: (s) {
                  setState(() => _filter = s.first);
                  _load();
                },
              ),
            ),
            if (_loading && items == null)
              const Padding(padding: EdgeInsets.all(48), child: Center(child: CircularProgressIndicator()))
            else if (_error != null)
              _Message(icon: Icons.cloud_off, text: _error!, actionLabel: 'Try again', onAction: _load)
            else if (items == null || items.isEmpty)
              _Message(
                icon: Icons.inbox_outlined,
                text: _filter == 'completed' ? 'No completed jobs yet.' : 'No jobs right now.',
              )
            else
              for (final a in items)
                AssignmentCard(
                  key: ValueKey('assignment-${a.id}'),
                  assignment: a,
                  api: widget.session.api,
                  onUpdated: _replace,
                ),
          ],
        ),
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final IconData icon;
  final String value;
  final String label;
  const _StatCard({required this.icon, required this.value, required this.label});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Expanded(
      child: Card(
        margin: const EdgeInsets.symmetric(horizontal: 4),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
          child: Column(
            children: [
              Icon(icon, color: theme.colorScheme.primary),
              const SizedBox(height: 4),
              Text(value, style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
              Text(label,
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  style: theme.textTheme.labelSmall?.copyWith(color: theme.colorScheme.onSurfaceVariant)),
            ],
          ),
        ),
      ),
    );
  }
}

class _Message extends StatelessWidget {
  final IconData icon;
  final String text;
  final String? actionLabel;
  final VoidCallback? onAction;
  const _Message({required this.icon, required this.text, this.actionLabel, this.onAction});

  @override
  Widget build(BuildContext context) {
    final muted = Theme.of(context).colorScheme.onSurfaceVariant;
    return Padding(
      padding: const EdgeInsets.all(40),
      child: Column(
        children: [
          Icon(icon, size: 48, color: muted),
          const SizedBox(height: 12),
          Text(text, textAlign: TextAlign.center, style: TextStyle(color: muted)),
          if (actionLabel != null) ...[
            const SizedBox(height: 12),
            OutlinedButton(onPressed: onAction, child: Text(actionLabel!)),
          ],
        ],
      ),
    );
  }
}
