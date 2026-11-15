import 'package:flutter/material.dart';

/// Informational overview only. All values come from the center API response.
class CenterOverviewContent extends StatelessWidget {
  final Map<String, dynamic> data;
  const CenterOverviewContent({super.key, required this.data});

  static const forest = Color(0xFF123E32);
  static const muted = Color(0xFF718278);
  static const lime = Color(0xFFE5F3C7);
  static const border = Color(0xFFE2E8DD);
  static const parcelMetrics = [
    ('Awaiting dispatch', Icons.inventory_2_outlined),
    ('Out for delivery', Icons.local_shipping_outlined),
    ('Delivered / completed', Icons.task_alt_outlined),
    ('Incoming transfers', Icons.move_to_inbox_outlined),
  ];
  static const teamMetrics = [
    ('Approved riders', Icons.delivery_dining_outlined),
    ('Approved drivers', Icons.local_shipping_outlined),
    ('Couriers online (last 30 minutes)', Icons.wifi_outlined),
    ('Pending applications', Icons.people_outline),
    ('Delivery areas', Icons.location_on_outlined),
  ];

  Map<String, String> get values => {
    for (final metric in data['metrics'] as List? ?? [])
      '${metric['label']}': '${metric['value'] ?? '—'}',
  };

  Widget heading(String text) => Padding(
    padding: const EdgeInsets.only(top: 24, bottom: 12),
    child: Text(text, style: const TextStyle(
      fontSize: 20, fontWeight: FontWeight.w800, color: forest,
    )),
  );

  Widget iconBadge(IconData icon) => Container(
    padding: const EdgeInsets.all(10),
    decoration: BoxDecoration(color: lime, borderRadius: BorderRadius.circular(12)),
    child: Icon(icon, color: forest, size: 25),
  );

  Widget metricCard(String label, IconData icon, String value) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: Colors.white,
      border: Border.all(color: border),
      borderRadius: BorderRadius.circular(18),
      boxShadow: const [BoxShadow(color: Color(0x08123E32), blurRadius: 12, offset: Offset(0, 4))],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        iconBadge(icon),
        const SizedBox(height: 12),
        Text(label, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: forest)),
        const SizedBox(height: 7),
        FittedBox(
          fit: BoxFit.scaleDown,
          alignment: Alignment.centerLeft,
          child: Text(value, style: const TextStyle(fontSize: 32, fontWeight: FontWeight.w800, color: forest)),
        ),
      ],
    ),
  );

  Widget parcelGrid(Map<String, String> metrics) => LayoutBuilder(
    builder: (context, constraints) {
      final single = constraints.maxWidth < 300 || MediaQuery.textScalerOf(context).scale(14) > 21;
      return Column(
        children: [
          for (int i = 0; i < parcelMetrics.length; i += single ? 1 : 2)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: IntrinsicHeight(
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Expanded(child: metricCard(parcelMetrics[i].$1, parcelMetrics[i].$2, metrics[parcelMetrics[i].$1] ?? '—')),
                    if (!single && i + 1 < parcelMetrics.length) ...[
                      const SizedBox(width: 12),
                      Expanded(child: metricCard(parcelMetrics[i + 1].$1, parcelMetrics[i + 1].$2, metrics[parcelMetrics[i + 1].$1] ?? '—')),
                    ],
                  ],
                ),
              ),
            ),
        ],
      );
    },
  );

  Widget teamRow(String label, IconData icon, String value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 13),
    child: Row(
      children: [
        Icon(icon, size: 25, color: forest),
        const SizedBox(width: 14),
        Expanded(child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label == 'Couriers online (last 30 minutes)' ? 'Couriers online' : label,
              style: const TextStyle(fontSize: 14, color: forest, fontWeight: FontWeight.w500)),
            if (label == 'Couriers online (last 30 minutes)')
              const Text('Last 30 minutes', style: TextStyle(fontSize: 12, color: muted)),
          ],
        )),
        const SizedBox(width: 12),
        ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 96),
          child: FittedBox(fit: BoxFit.scaleDown, alignment: Alignment.centerRight,
            child: Text(value, textAlign: TextAlign.right,
              style: const TextStyle(fontSize: 23, fontWeight: FontWeight.w800, color: forest))),
        ),
      ],
    ),
  );

  @override
  Widget build(BuildContext context) {
    final metrics = values;
    final known = {
      'Parcels in selected period', 'Unremitted COD',
      ...parcelMetrics.map((m) => m.$1), ...teamMetrics.map((m) => m.$1),
    };
    return Align(
      alignment: Alignment.topCenter,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 900),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Align(
              alignment: Alignment.centerLeft,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: BoxDecoration(color: lime, borderRadius: BorderRadius.circular(20)),
                child: const Text('CENTER OVERVIEW', style: TextStyle(fontSize: 11, letterSpacing: 1.5, fontWeight: FontWeight.w800, color: forest)),
              ),
            ),
            const SizedBox(height: 14),
            Text('${data['title'] ?? 'Sorting Center'}', style: const TextStyle(fontSize: 28, height: 1.15, fontWeight: FontWeight.w800, color: forest)),
            const SizedBox(height: 8),
            const Text('Your operations at a glance', style: TextStyle(fontSize: 15, color: muted)),
            const SizedBox(height: 22),
            Container(
              padding: const EdgeInsets.all(22),
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: [forest, Color(0xFF21654C)]),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Row(
                children: [
                  const Icon(Icons.inventory_2_outlined, size: 42, color: Colors.white),
                  const SizedBox(width: 20),
                  Expanded(child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Parcels in selected period', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Colors.white)),
                      const SizedBox(height: 8),
                      FittedBox(fit: BoxFit.scaleDown, alignment: Alignment.centerLeft,
                        child: Text(metrics['Parcels in selected period'] ?? '—', style: const TextStyle(fontSize: 42, height: 1.1, fontWeight: FontWeight.w800, color: Colors.white))),
                    ],
                  )),
                ],
              ),
            ),
            heading('Parcel Operations'),
            parcelGrid(metrics),
            heading('Team & Coverage'),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 3),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(18), border: Border.all(color: border)),
              child: Column(children: [
                for (int i = 0; i < teamMetrics.length; i++) ...[
                  if (i > 0) const Divider(height: 1, color: border),
                  teamRow(teamMetrics[i].$1, teamMetrics[i].$2, metrics[teamMetrics[i].$1] ?? '—'),
                ],
              ]),
            ),
            const SizedBox(height: 22),
            Container(
              padding: const EdgeInsets.all(22),
              decoration: BoxDecoration(color: lime, borderRadius: BorderRadius.circular(20)),
              child: Row(children: [
                const Icon(Icons.account_balance_wallet_outlined, color: forest, size: 38),
                const SizedBox(width: 18),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('Unremitted COD', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: forest)),
                  const SizedBox(height: 8),
                  FittedBox(fit: BoxFit.scaleDown, alignment: Alignment.centerLeft,
                    child: Text(metrics['Unremitted COD'] ?? '—', style: const TextStyle(fontSize: 30, fontWeight: FontWeight.w800, color: forest))),
                ])),
              ]),
            ),
            if (metrics.keys.any((key) => !known.contains(key))) ...[
              heading('Other Summaries'),
              for (final entry in metrics.entries.where((entry) => !known.contains(entry.key)))
                teamRow(entry.key, Icons.analytics_outlined, entry.value),
            ],
          ],
        ),
      ),
    );
  }
}
