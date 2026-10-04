import 'package:flutter/material.dart';

import '../api.dart';
import '../models.dart';
import '../session.dart';

class EarningsScreen extends StatefulWidget {
  final Session session;
  const EarningsScreen({super.key, required this.session});

  @override
  State<EarningsScreen> createState() => _EarningsScreenState();
}

class _EarningsScreenState extends State<EarningsScreen> {
  EarningsSummary? _data;
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
      final data = await widget.session.api.earnings();
      if (mounted) setState(() => _data = data);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final data = _data;

    return Scaffold(
      appBar: AppBar(title: const Text('Completed trips & earnings')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            if (_loading && data == null)
              const Padding(padding: EdgeInsets.all(48), child: Center(child: CircularProgressIndicator()))
            else if (_error != null)
              Padding(
                padding: const EdgeInsets.all(40),
                child: Column(
                  children: [
                    Text(_error!, textAlign: TextAlign.center),
                    const SizedBox(height: 12),
                    OutlinedButton(onPressed: _load, child: const Text('Try again')),
                  ],
                ),
              )
            else if (data != null) ...[
              Card(
                margin: const EdgeInsets.all(16),
                color: theme.colorScheme.primaryContainer,
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Total earnings', style: theme.textTheme.labelLarge?.copyWith(color: theme.colorScheme.onPrimaryContainer)),
                      const SizedBox(height: 4),
                      Text(peso(data.total),
                          style: theme.textTheme.headlineLarge
                              ?.copyWith(fontWeight: FontWeight.w800, color: theme.colorScheme.onPrimaryContainer)),
                      const SizedBox(height: 2),
                      Text('${data.count} completed ${data.count == 1 ? 'trip' : 'trips'}',
                          style: TextStyle(color: theme.colorScheme.onPrimaryContainer)),
                    ],
                  ),
                ),
              ),
              if (data.entries.isEmpty)
                Padding(
                  padding: const EdgeInsets.all(40),
                  child: Text('No completed trips yet.',
                      textAlign: TextAlign.center, style: TextStyle(color: theme.colorScheme.onSurfaceVariant)),
                )
              else
                for (final e in data.entries)
                  ListTile(
                    leading: CircleAvatar(
                      backgroundColor: theme.colorScheme.secondaryContainer,
                      child: Icon(e.kind == 'pickup' ? Icons.inventory_2_outlined : Icons.local_shipping_outlined),
                    ),
                    title: Text('${e.kind == 'pickup' ? 'Pickup' : 'Delivery'} \u00B7 Parcel #${e.parcelId}'),
                    subtitle: Text('${e.trackingCode}\n${shortDate(e.completedAt)}'),
                    isThreeLine: true,
                    trailing: Text(peso(e.earnings), style: const TextStyle(fontWeight: FontWeight.w700)),
                  ),
            ],
          ],
        ),
      ),
    );
  }
}
