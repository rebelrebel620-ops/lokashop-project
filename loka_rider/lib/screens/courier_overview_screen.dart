import 'package:flutter/material.dart';
import '../models.dart';
import '../session.dart';
import '../widgets/common.dart';
import 'features.dart';
import 'earnings_screen.dart';

/// Native dashboard for the courier's actual role. Availability stays on its own tab.
class OverviewScreen extends StatelessWidget {
  final Session session;
  final ValueChanged<String> onJobs;
  final VoidCallback onScan;
  const OverviewScreen({
    super.key,
    required this.session,
    required this.onJobs,
    required this.onScan,
  });

  int count(dynamic value) => int.tryParse('$value') ?? 0;

  @override
  Widget build(BuildContext context) {
    final driver = session.rider?.isDriver == true;
    return DataPage(
      title: driver ? 'Driver overview' : 'Rider overview',
      path: '/api/rider/overview',
      session: session,
      content: (c, data, reload) {
        final work = List<Map<String, dynamic>>.from(
          (data['current_work'] as List? ?? []).map(
            (row) => Map<String, dynamic>.from(row as Map),
          ),
        );
        final metrics =
            <({String title, String value, IconData icon, VoidCallback tap})>[
              (
                title: driver ? 'Trip offers' : 'Pickup & delivery requests',
                value: '${count(data[driver ? 'trip_offers' : 'offered'])}',
                icon: Icons.inbox_outlined,
                tap: () => onJobs('new'),
              ),
              (
                title: driver ? 'Active trips' : 'Ongoing assignments',
                value: '${count(data[driver ? 'active_trips' : 'active'])}',
                icon: Icons.route_outlined,
                tap: () => onJobs('progress'),
              ),
              (
                title: driver ? 'Trips completed today' : 'Completed today',
                value:
                    '${count(data[driver ? 'today_completed_trips' : 'today_completed'])}',
                icon: Icons.task_alt,
                tap: () => onJobs('completed'),
              ),
              (
                title: 'Earned today',
                value: peso(double.tryParse('${data['today_earnings']}') ?? 0),
                icon: Icons.payments_outlined,
                tap: () => open(c, EarningsScreen(session: session)),
              ),
            ];
        return [
          Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFF123E32), Color(0xFF25664B)],
              ),
              borderRadius: BorderRadius.circular(24),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  driver ? 'CENTER TO CENTER' : 'LOCAL PICKUP & DELIVERY',
                  style: const TextStyle(
                    color: Color(0xFFDDECBB),
                    letterSpacing: 1.6,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  'Hello, ${session.rider?.name.split(' ').first ?? 'courier'}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 28,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  driver
                      ? 'Your loads, destination stops and handovers in one place.'
                      : 'Your seller collections and buyer deliveries, ready for the next stop.',
                  style: const TextStyle(color: Colors.white70, height: 1.5),
                ),
                const SizedBox(height: 18),
                FilledButton.tonalIcon(
                  onPressed: () => onJobs('progress'),
                  icon: const Icon(Icons.arrow_forward),
                  label: Text(
                    driver ? 'Open active trips' : 'Open ongoing work',
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          LayoutBuilder(
            builder: (context, constraints) {
              final columns = constraints.maxWidth >= 700 ? 4 : 2;
              final width =
                  (constraints.maxWidth - (columns - 1) * 12) / columns;
              return Wrap(
                spacing: 12,
                runSpacing: 12,
                children: metrics
                    .map(
                      (metric) => SizedBox(
                        width: width,
                        child: Card(
                          margin: EdgeInsets.zero,
                          child: InkWell(
                            borderRadius: BorderRadius.circular(22),
                            onTap: metric.tap,
                            child: Padding(
                              padding: const EdgeInsets.all(17),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Icon(
                                    metric.icon,
                                    color: const Color(0xFF286346),
                                  ),
                                  const SizedBox(height: 14),
                                  Text(
                                    metric.value,
                                    style: const TextStyle(
                                      fontSize: 25,
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                  const SizedBox(height: 5),
                                  Text(
                                    metric.title,
                                    style: const TextStyle(
                                      fontSize: 12,
                                      height: 1.4,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                    )
                    .toList(),
              );
            },
          ),
          const SizedBox(height: 22),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(
                  driver ? 'On your route' : 'Your next stops',
                  style: Theme.of(
                    c,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
                ),
              ),
              TextButton(
                onPressed: () => onJobs('progress'),
                child: const Text('View all'),
              ),
            ],
          ),
          if (work.isEmpty)
            InfoCard(
              title: 'No ongoing work',
              text:
                  'Check your requests and accept an assignment when you are ready.',
              icon: Icons.inventory_2_outlined,
              onTap: () => onJobs('new'),
            ),
          ...work.map(
            (row) => Card(
              child: ListTile(
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 18,
                  vertical: 8,
                ),
                leading: Icon(
                  driver
                      ? Icons.local_shipping_outlined
                      : Icons.inventory_2_outlined,
                ),
                title: Text(
                  '${row['tracking_code'] ?? ''}',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                subtitle: Text(
                  '${prettyStatus('${row['kind'] ?? ''}')} · ${prettyStatus('${row['parcel_status'] ?? ''}')}',
                ),
                trailing: const Icon(Icons.arrow_forward, size: 18),
                onTap: () => onJobs('progress'),
              ),
            ),
          ),
          if (driver && count(data['legacy_open']) > 0)
            InfoCard(
              title: 'Individual transfer assignments',
              text:
                  '${count(data['legacy_open'])} older parcel assignments. Open the Individual transfers option on the Trips page.',
              icon: Icons.inventory_outlined,
              onTap: () => onJobs('progress'),
            ),
          const SizedBox(height: 12),
          if (!driver)
            OutlinedButton.icon(
              onPressed: onScan,
              icon: const Icon(Icons.qr_code_scanner),
              label: const Text('Scan parcel label'),
            ),
          InfoCard(
            title: 'Your sorting center',
            text: data['center'] is Map
                ? '${data['center']['name']}\n${data['center']['city']}, ${data['center']['province']}'
                : 'Contact logistics to attach your sorting center.',
            icon: Icons.warehouse_outlined,
          ),
          InfoCard(
            title: 'Notifications',
            text: '${count(data['unread'])} unread updates',
            icon: Icons.notifications_outlined,
            onTap: () => open(c, NotificationsScreen(session: session)),
          ),
          InfoCard(
            title: 'Messages',
            text: 'Contact your sorting center about your route.',
            icon: Icons.forum_outlined,
            onTap: () => open(c, MessagesScreen(session: session)),
          ),
          InfoCard(
            title: 'Completed work',
            text: driver
                ? '${count(data['completed_trips'])} completed bulk trips'
                : '${count(data['completed'])} completed assignments',
            icon: Icons.done_all,
            onTap: () => onJobs('completed'),
          ),
          InfoCard(
            title: 'Next step',
            text: driver
                ? 'Check your manifest before departure. Confirm each handover; the destination center then confirms receipt.'
                : 'Accept a request, scan the parcel, and follow its next action. Return collected parcels to your center to complete pickup.',
            icon: Icons.info_outline,
          ),
        ];
      },
    );
  }
}
