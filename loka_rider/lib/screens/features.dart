import 'dart:async';
import 'dart:math';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../session.dart';
import '../models.dart';
import '../widgets/common.dart';
import '../widgets/place_fields.dart';
import 'earnings_screen.dart';

String requestKey() => List.generate(
  24,
  (_) => Random.secure().nextInt(256).toRadixString(16).padLeft(2, '0'),
).join();
void open(BuildContext c, Widget page) =>
    Navigator.push(c, MaterialPageRoute(builder: (_) => page));

class DataPage extends StatefulWidget {
  final String title, path;
  final Session session;
  final List<Widget> Function(BuildContext, Map<String, dynamic>, VoidCallback)
  content;
  const DataPage({
    super.key,
    required this.title,
    required this.path,
    required this.session,
    required this.content,
  });
  @override
  State<DataPage> createState() => _DataPageState();
}

class _DataPageState extends State<DataPage> {
  Map<String, dynamic>? data;
  Object? error;
  bool busy = false;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    if (busy) return;
    busy = true;
    try {
      final v = await widget.session.api.get(widget.path);
      if (mounted) {
        setState(() {
          data = v;
          error = null;
        });
      }
    } catch (e) {
      if (mounted) setState(() => error = e);
    } finally {
      busy = false;
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(widget.title),
      actions: [
        IconButton(
          onPressed: load,
          icon: const Icon(Icons.refresh),
          tooltip: 'Refresh',
        ),
      ],
    ),
    body: error != null
        ? LoadError(error: error!, retry: load)
        : data == null
        ? const Center(child: CircularProgressIndicator())
        : RefreshIndicator(
            onRefresh: load,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
              children: widget.content(context, data!, load),
            ),
          ),
  );
}

class OverviewScreen extends StatelessWidget {
  final Session session;
  final VoidCallback onJobs, onScan;
  const OverviewScreen({
    super.key,
    required this.session,
    required this.onJobs,
    required this.onScan,
  });
  @override
  Widget build(BuildContext context) => DataPage(
    title: 'LokaShop Courier',
    path: '/api/rider/overview',
    session: session,
    content: (c, d, reload) => [
      Container(
        padding: const EdgeInsets.all(24),
        decoration: BoxDecoration(
          color: const Color(0xFF123E32),
          borderRadius: BorderRadius.circular(24),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'HELLO, ${session.rider?.name.split(' ').first.toUpperCase() ?? 'COURIER'}',
              style: const TextStyle(
                color: Color(0xFFDDECBB),
                letterSpacing: 2,
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 12),
            const Text(
              'Every parcel.\nA promise delivered.',
              style: TextStyle(
                color: Colors.white,
                fontSize: 30,
                fontWeight: FontWeight.w800,
                height: 1.2,
              ),
            ),
            const SizedBox(height: 16),
            Text(
              '${session.rider?.courierType == 'driver' ? 'Center transfer driver' : 'Local delivery rider'} • ${session.rider?.online == true ? 'Active / Online' : 'Offline'}',
              style: const TextStyle(color: Colors.white70),
            ),
          ],
        ),
      ),
      const SizedBox(height: 16),
      Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          _metric(
            'Open offers',
            '${d['offered']}',
            Icons.mark_email_unread_outlined,
          ),
          _metric(
            'Active jobs',
            '${d['active']}',
            Icons.local_shipping_outlined,
          ),
          _metric('Today completed', '${d['today_completed']}', Icons.task_alt),
          _metric(
            'Today earned',
            peso(double.tryParse('${d['today_earnings']}') ?? 0),
            Icons.payments_outlined,
          ),
        ],
      ),
      const SizedBox(height: 16),
      FilledButton.icon(
        onPressed: onJobs,
        icon: const Icon(Icons.local_shipping_outlined),
        label: const Text('View assignments'),
      ),
      const SizedBox(height: 8),
      OutlinedButton.icon(
        onPressed: onScan,
        icon: const Icon(Icons.qr_code_scanner),
        label: const Text('Scan a shipping label'),
      ),
      InfoCard(
        title: 'Your sorting center',
        text: d['center'] == null
            ? 'No sorting center attached. Contact logistics.'
            : '${d['center']['name']}\n${d['center']['city']}, ${d['center']['province']}',
        icon: Icons.warehouse_outlined,
      ),
      InfoCard(
        title: 'Availability',
        text: 'Manage your Online / Offline status and service location.',
        icon: Icons.toggle_on_outlined,
        onTap: () => open(c, AvailabilityScreen(session: session)),
      ),
      InfoCard(
        title: 'Notifications',
        text: '${d['unread']} unread updates',
        icon: Icons.notifications_outlined,
        onTap: () => open(c, NotificationsScreen(session: session)),
      ),
      InfoCard(
        title: 'Earnings',
        text:
            '${d['completed']} completed assignments • ${peso(double.tryParse('${d['earnings']}') ?? 0)}',
        icon: Icons.payments_outlined,
        onTap: () => open(c, EarningsScreen(session: session)),
      ),
    ],
  );
  Widget _metric(String title, String value, IconData icon) => SizedBox(
    width: 155,
    child: Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: const Color(0xFF5D7043)),
            const SizedBox(height: 10),
            Text(
              value,
              style: const TextStyle(fontSize: 23, fontWeight: FontWeight.w800),
            ),
            Text(title, style: const TextStyle(fontSize: 12)),
          ],
        ),
      ),
    ),
  );
}

class AvailabilityScreen extends StatefulWidget {
  final Session session;
  const AvailabilityScreen({super.key, required this.session});
  @override
  State<AvailabilityScreen> createState() => _AvailabilityScreenState();
}

class _AvailabilityScreenState extends State<AvailabilityScreen> {
  bool busy = false;
  Object? error;
  bool loaded = false;
  @override
  void initState() {
    super.initState();
    refresh();
  }

  Future<void> refresh() async {
    try {
      widget.session.updateRider((await widget.session.api.me()).rider);
      if (mounted) {
        setState(() {
          loaded = true;
          error = null;
        });
      }
    } catch (e) {
      if (mounted) setState(() => error = e);
    }
  }

  Future<void> change(bool online) async {
    setState(() => busy = true);
    try {
      final d = await widget.session.api.post('/api/rider/availability', {
        'online': online,
      });
      await refresh();
      if (mounted) toast(context, d['message']);
    } catch (e) {
      if (mounted) toast(context, e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final r = widget.session.rider;
    return Scaffold(
      appBar: AppBar(title: const Text('Availability')),
      body: error != null
          ? LoadError(error: error!, retry: refresh)
          : !loaded
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      children: [
                        Icon(
                          r?.online == true ? Icons.wifi : Icons.wifi_off,
                          size: 56,
                          color: const Color(0xFF123E32),
                        ),
                        const SizedBox(height: 16),
                        Text(
                          r?.online == true
                              ? 'You are ready for jobs'
                              : 'You are offline',
                          style: Theme.of(context).textTheme.headlineSmall,
                        ),
                        const SizedBox(height: 8),
                        const Text(
                          'Finish accepted jobs before going offline. Keep the app open while waiting for offers.',
                          textAlign: TextAlign.center,
                        ),
                        SwitchListTile(
                          title: const Text('Active / Online'),
                          value: r?.online ?? false,
                          onChanged: busy ? null : change,
                        ),
                      ],
                    ),
                  ),
                ),
                InfoCard(
                  title: 'Last shared location',
                  text:
                      'Latitude: ${r?.extra['latitude'] ?? 'Not set'}\nLongitude: ${r?.extra['longitude'] ?? 'Not set'}',
                  icon: Icons.my_location,
                  onTap: () => open(
                    context,
                    EditScreen(
                      session: widget.session,
                      title: 'Update service location',
                      endpoint: '/api/rider/location',
                      initial: {
                        'latitude': '${r?.extra['latitude'] ?? ''}',
                        'longitude': '${r?.extra['longitude'] ?? ''}',
                      },
                      fields: const ['latitude', 'longitude'],
                      afterSave: refresh,
                    ),
                  ),
                ),
                const InfoCard(
                  title: 'Location privacy',
                  text:
                      'Location is submitted only when you save it. This app does not track you in the background.',
                  icon: Icons.privacy_tip_outlined,
                ),
              ],
            ),
    );
  }
}

class CodScreen extends StatelessWidget {
  final Session session;
  const CodScreen({super.key, required this.session});
  @override
  Widget build(BuildContext context) => DataPage(
    title: 'Cash on delivery',
    path: '/api/rider/cod',
    session: session,
    content: (c, d, reload) {
      final pending = d['open'] as Map?;
      final rows = (d['records']?['data'] as List? ?? []);
      return [
        InfoCard(
          title: 'Unremitted collections',
          text: peso(double.tryParse('${d['unremitted']}') ?? 0),
          icon: Icons.account_balance_wallet_outlined,
        ),
        const Padding(
          padding: EdgeInsets.all(8),
          child: Text(
            'COD is money collected for orders. It is separate from your earnings.',
          ),
        ),
        if (pending == null && (double.tryParse('${d['unremitted']}') ?? 0) > 0)
          FilledButton.icon(
            onPressed: () => open(
              c,
              EditScreen(
                session: session,
                title: 'Submit remittance',
                endpoint: '/api/rider/remit',
                fields: const ['amount', 'reference'],
                afterSave: reload,
              ),
            ),
            icon: const Icon(Icons.send_outlined),
            label: const Text('Submit COD remittance'),
          ),
        if (pending != null)
          InfoCard(
            title:
                'Remittance #${pending['id']} • ${prettyStatus('${pending['status']}')}',
            text:
                'Submitted ${peso(double.tryParse('${pending['amount']}') ?? 0)} / expected ${peso(double.tryParse('${pending['expected']}') ?? 0)}\nAwaiting sorting-center confirmation.',
            icon: Icons.hourglass_empty,
          ),
        if (pending?['status'] == 'short')
          FilledButton(
            onPressed: () => open(
              c,
              EditScreen(
                session: session,
                title: 'Top up remittance',
                endpoint: '/api/rider/remit/topup',
                fields: const ['amount', 'reference'],
                hidden: {'id': '${pending!['id']}'},
                afterSave: reload,
              ),
            ),
            child: const Text('Resolve short remittance'),
          ),
        const SizedBox(height: 20),
        Text('Remittance history', style: Theme.of(c).textTheme.titleLarge),
        if (rows.isEmpty)
          const InfoCard(
            title: 'No remittances yet',
            text: 'Your submitted collections will appear here.',
          ),
        ...rows.map(
          (x) => InfoCard(
            title: '#${x['id']} • ${prettyStatus('${x['status']}')}',
            text:
                '${peso(double.tryParse('${x['amount']}') ?? 0)}\n${x['reference'] ?? ''}\n${shortDate(x['created_at']?.toString())}',
            icon: Icons.receipt_long,
          ),
        ),
        if ((d['records']?['last_page'] as num? ?? 1) > 1)
          TextButton(
            onPressed: () => open(
              c,
              RecordList(
                session: session,
                title: 'All remittances',
                path: '/api/rider/cod',
                nested: 'records',
              ),
            ),
            child: const Text('View all pages'),
          ),
      ];
    },
  );
}

class AccountScreen extends StatelessWidget {
  final Session session;
  const AccountScreen({super.key, required this.session});
  @override
  Widget build(BuildContext context) => DataPage(
    title: 'Your account',
    path: '/api/rider/overview',
    session: session,
    content: (c, d, reload) {
      final r = session.rider!;
      return [
        if ('${r.extra['profile_photo_url'] ?? ''}'.isNotEmpty)
          Center(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(50),
              child: Image.network(
                session.api.mediaUrl('${r.extra['profile_photo_url']}'),
                width: 88,
                height: 88,
                fit: BoxFit.cover,
                errorBuilder: (_, e, s) => const Icon(Icons.person, size: 64),
              ),
            ),
          ),
        InfoCard(
          title: r.name,
          text:
              '${r.email}\n${r.courierType == 'driver' ? 'Driver' : 'Rider'} • ${r.vehicle ?? 'Vehicle not set'}',
          icon: Icons.person_outline,
        ),
        InfoCard(
          title: 'Profile',
          text: 'Name and profile photo',
          icon: Icons.badge_outlined,
          onTap: () => open(
            c,
            EditScreen(
              session: session,
              title: 'Edit profile',
              endpoint: '/api/rider/profile',
              fields: const ['name'],
              initial: {'name': r.name},
              hidden: {'phone': r.phone ?? ''},
              photoFields: const ['profile_photo'],
              afterSave: () async {
                session.updateRider((await session.api.me()).rider);
                reload();
              },
            ),
          ),
        ),
        InfoCard(
          title: 'Availability',
          text: 'Online status and service location',
          icon: Icons.toggle_on_outlined,
          onTap: () => open(c, AvailabilityScreen(session: session)),
        ),
        InfoCard(
          title: 'Vehicle and documents',
          text: '${r.vehicle ?? ''} • ${r.extra['plate_number'] ?? ''}',
          icon: Icons.two_wheeler,
          onTap: () => open(c, VehicleScreen(session: session)),
        ),
        InfoCard(
          title: 'Registered address',
          text: 'Update address details within your registered municipality',
          icon: Icons.location_on_outlined,
          onTap: () => open(
            c,
            AddressScreen(
              session: session,
              initial: Map<String, dynamic>.from(d['address'] ?? {}),
            ),
          ),
        ),
        InfoCard(
          title: 'Messages',
          text: 'Talk to your sorting center',
          icon: Icons.chat_bubble_outline,
          onTap: () => open(c, MessagesScreen(session: session)),
        ),
        InfoCard(
          title: 'Notifications',
          text: 'Assignment and delivery updates',
          icon: Icons.notifications_outlined,
          onTap: () => open(c, NotificationsScreen(session: session)),
        ),
        InfoCard(
          title: 'Earnings',
          text: 'Completed pickups, deliveries and transfers',
          icon: Icons.payments_outlined,
          onTap: () => open(c, EarningsScreen(session: session)),
        ),
        Padding(
          padding: const EdgeInsets.all(8),
          child: Text(
            'Connected to ${session.serverUrl}',
            style: Theme.of(c).textTheme.bodySmall,
          ),
        ),
        OutlinedButton.icon(
          onPressed: () async {
            final yes = await showDialog<bool>(
              context: c,
              builder: (ctx) => AlertDialog(
                title: const Text('Do you want to log out?'),
                actions: [
                  TextButton(
                    onPressed: () => Navigator.pop(ctx, false),
                    child: const Text('No'),
                  ),
                  FilledButton(
                    onPressed: () => Navigator.pop(ctx, true),
                    child: const Text('Yes, log out'),
                  ),
                ],
              ),
            );
            if (yes == true) await session.logout();
          },
          icon: const Icon(Icons.logout),
          label: const Text('Log out'),
        ),
      ];
    },
  );
}

class VehicleScreen extends StatelessWidget {
  final Session session;
  const VehicleScreen({super.key, required this.session});
  @override
  Widget build(BuildContext context) {
    final r = session.rider!;
    return EditScreen(
      session: session,
      title: 'Vehicle and documents',
      endpoint: '/api/rider/vehicle',
      fields: const [
        'vehicle',
        'license_number',
        'license_expires_at',
        'plate_number',
      ],
      initial: {
        'vehicle': r.vehicle ?? '',
        'license_number': '${r.extra['license_number'] ?? ''}',
        'license_expires_at': '${r.extra['license_expires_at'] ?? ''}'
            .split(' ')
            .first,
        'plate_number': '${r.extra['plate_number'] ?? ''}',
      },
      choices: {
        'vehicle': r.courierType == 'driver'
            ? [
                '4-wheel truck',
                '6-wheel truck',
                '8-wheel truck',
                '10-wheel truck',
              ]
            : ['motorcycle', 'tricycle'],
      },
      photoFields: const ['id_document', 'permit_document'],
      afterSave: () async {
        session.updateRider((await session.api.me()).rider);
      },
    );
  }
}

class EditScreen extends StatefulWidget {
  final Session session;
  final String title, endpoint;
  final List<String> fields, photoFields;
  final Map<String, String> initial, hidden;
  final Map<String, List<String>> choices;
  final FutureOr<void> Function()? afterSave;
  const EditScreen({
    super.key,
    required this.session,
    required this.title,
    required this.endpoint,
    required this.fields,
    this.initial = const {},
    this.hidden = const {},
    this.choices = const {},
    this.photoFields = const [],
    this.afterSave,
  });
  @override
  State<EditScreen> createState() => _EditScreenState();
}

class _EditScreenState extends State<EditScreen> {
  final form = GlobalKey<FormState>();
  late final controls = {
    for (final f in widget.fields)
      f: TextEditingController(text: widget.initial[f] ?? ''),
  };
  final files = <String, XFile>{};
  final keyValue = requestKey();
  bool busy = false;
  Object? error;
  @override
  void dispose() {
    for (final c in controls.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> save() async {
    if (!form.currentState!.validate()) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final fields = {
        ...widget.hidden,
        for (final e in controls.entries) e.key: e.value.text.trim(),
        '_request_key': keyValue,
      };
      final d = files.isEmpty
          ? await widget.session.api.post(widget.endpoint, fields)
          : await widget.session.api.upload(widget.endpoint, fields, files);
      if (widget.afterSave != null) await widget.afterSave!();
      if (mounted) {
        toast(context, d['message'] ?? 'Saved.');
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) setState(() => error = e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(widget.title)),
    body: Form(
      key: form,
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          ...widget.fields.map(
            (f) => Padding(
              padding: const EdgeInsets.only(bottom: 14),
              child: widget.choices.containsKey(f)
                  ? DropdownButtonFormField<String>(
                      isExpanded: true,
                      initialValue:
                          widget.choices[f]!.contains(controls[f]!.text)
                          ? controls[f]!.text
                          : null,
                      decoration: InputDecoration(labelText: prettyStatus(f)),
                      items: widget.choices[f]!
                          .map(
                            (v) => DropdownMenuItem(value: v, child: Text(v)),
                          )
                          .toList(),
                      onChanged: busy
                          ? null
                          : (v) => controls[f]!.text = v ?? '',
                      validator: (v) => v == null ? 'Choose an option' : null,
                    )
                  : TextFormField(
                      controller: controls[f],
                      enabled: !busy,
                      decoration: InputDecoration(
                        labelText: prettyStatus(f),
                        hintText: f == 'license_expires_at'
                            ? 'YYYY-MM-DD'
                            : f == 'license_number'
                            ? 'A01-23-456789'
                            : null,
                      ),
                      keyboardType:
                          ['amount', 'latitude', 'longitude'].contains(f)
                          ? const TextInputType.numberWithOptions(
                              decimal: true,
                              signed: true,
                            )
                          : TextInputType.text,
                      validator: (v) =>
                          v == null || v.trim().isEmpty ? 'Required' : null,
                    ),
            ),
          ),
          ...widget.photoFields.map(
            (f) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: OutlinedButton.icon(
                onPressed: busy
                    ? null
                    : () async {
                        try {
                          final x = await ImagePicker().pickImage(
                            source: ImageSource.gallery,
                            imageQuality: 80,
                            maxWidth: 1800,
                          );
                          if (x != null && mounted) {
                            setState(() => files[f] = x);
                          }
                        } catch (e) {
                          if (mounted) toast(this.context, e);
                        }
                      },
                icon: const Icon(Icons.add_photo_alternate_outlined),
                label: Text(
                  files[f] == null
                      ? 'Choose ${prettyStatus(f)} (optional)'
                      : '${prettyStatus(f)}: ${files[f]!.name}',
                ),
              ),
            ),
          ),
          if (error != null)
            Padding(
              padding: const EdgeInsets.all(12),
              child: Text(
                '$error',
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          FilledButton(
            onPressed: busy ? null : save,
            child: Text(busy ? 'Saving…' : 'Save'),
          ),
        ],
      ),
    ),
  );
}

class AddressScreen extends StatefulWidget {
  final Session session;
  final Map<String, dynamic> initial;
  const AddressScreen({
    super.key,
    required this.session,
    required this.initial,
  });
  @override
  State<AddressScreen> createState() => _AddressScreenState();
}

class _AddressScreenState extends State<AddressScreen> {
  late final name = TextEditingController(
    text: widget.initial['recipient'] ?? widget.session.rider?.name ?? '',
  );
  late final phone = TextEditingController(
    text: widget.initial['phone'] ?? widget.session.rider?.phone ?? '',
  );
  late final street = TextEditingController(
    text: widget.initial['street'] ?? '',
  );
  late final postal = TextEditingController(
    text: widget.initial['postal_code'] ?? '',
  );
  late Map<String, String> place = {
    for (final k in ['region', 'province', 'city', 'barangay'])
      k: '${widget.initial[k] ?? ''}',
  };
  bool busy = false;
  @override
  void dispose() {
    for (final c in [name, phone, street, postal]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> save() async {
    setState(() => busy = true);
    try {
      final d = await widget.session.api.post('/api/rider/address', {
        ...place,
        'recipient': name.text,
        'phone': phone.text,
        'street': street.text,
        'postal_code': postal.text,
      });
      if (mounted) {
        toast(context, d['message']);
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) toast(context, e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Registered address')),
    body: ListView(
      padding: const EdgeInsets.all(20),
      children: [
        const Text(
          'Your service municipality stays the same. Ask logistics to review territory changes.',
        ),
        const SizedBox(height: 16),
        ...[
          (name, 'Recipient'),
          (phone, 'Phone'),
          (street, 'Street'),
          (postal, 'Postal code'),
        ].map(
          (x) => Padding(
            padding: const EdgeInsets.only(bottom: 14),
            child: TextField(
              controller: x.$1,
              decoration: InputDecoration(labelText: x.$2),
            ),
          ),
        ),
        PlaceFields(initial: widget.initial, onChanged: (v) => place = v),
        const SizedBox(height: 16),
        FilledButton(
          onPressed: busy ? null : save,
          child: Text(busy ? 'Saving…' : 'Save address'),
        ),
      ],
    ),
  );
}

class RecordList extends StatefulWidget {
  final Session session;
  final String title, path;
  final String? nested;
  const RecordList({
    super.key,
    required this.session,
    required this.title,
    required this.path,
    this.nested,
  });
  @override
  State<RecordList> createState() => _RecordListState();
}

class _RecordListState extends State<RecordList> {
  int page = 1;
  @override
  Widget build(BuildContext context) => DataPage(
    key: ValueKey(page),
    title: widget.title,
    path: '${widget.path}?page=$page',
    session: widget.session,
    content: (c, d, reload) {
      final data = widget.nested == null ? d : d[widget.nested] as Map;
      final rows = data['data'] as List? ?? [];
      return [
        if (widget.path == '/api/rider/notifications')
          Align(
            alignment: Alignment.centerRight,
            child: TextButton.icon(
              onPressed: () async {
                try {
                  final result = await widget.session.api.post(
                    '/api/rider/notifications/read',
                    {},
                  );
                  if (c.mounted) {
                    toast(c, result['message']);
                    reload();
                  }
                } catch (e) {
                  if (c.mounted) toast(c, e);
                }
              },
              icon: const Icon(Icons.done_all),
              label: const Text('Mark all as read'),
            ),
          ),
        if (rows.isEmpty)
          const InfoCard(
            title: 'Nothing here yet',
            text: 'Updates appear here as you work.',
          ),
        ...rows.map(
          (x) => InfoCard(
            title: '${x['body'] ?? x['message'] ?? 'Remittance #${x['id']}'}',
            text:
                '${x['status'] ?? ''} ${x['amount'] ?? ''}\n${shortDate(x['created_at']?.toString())}',
            icon: Icons.notifications_outlined,
          ),
        ),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            IconButton(
              onPressed: page > 1 ? () => setState(() => page--) : null,
              icon: const Icon(Icons.chevron_left),
            ),
            Text('$page / ${data['last_page'] ?? 1}'),
            IconButton(
              onPressed: page < (data['last_page'] as num? ?? 1)
                  ? () => setState(() => page++)
                  : null,
              icon: const Icon(Icons.chevron_right),
            ),
          ],
        ),
      ];
    },
  );
}

class NotificationsScreen extends StatelessWidget {
  final Session session;
  const NotificationsScreen({super.key, required this.session});
  @override
  Widget build(BuildContext context) => RecordList(
    session: session,
    title: 'Notifications',
    path: '/api/rider/notifications',
  );
}

class MessagesScreen extends StatefulWidget {
  final Session session;
  const MessagesScreen({super.key, required this.session});
  @override
  State<MessagesScreen> createState() => _MessagesScreenState();
}

class _MessagesScreenState extends State<MessagesScreen>
    with WidgetsBindingObserver {
  Map<String, dynamic>? data;
  Object? error;
  int? selected;
  bool busy = false, loading = false, foreground = true;
  final body = TextEditingController();
  XFile? image;
  Timer? timer;
  int version = 0;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    load();
    timer = Timer.periodic(const Duration(seconds: 12), (_) {
      if (foreground && !loading && !busy) load();
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState s) {
    foreground = s == AppLifecycleState.resumed;
  }

  @override
  void dispose() {
    timer?.cancel();
    body.dispose();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  Future<void> load() async {
    final v = ++version;
    loading = true;
    try {
      final r = await widget.session.api.get(
        '/api/rider/messages',
        selected == null ? null : {'with': '$selected'},
      );
      if (mounted && v == version) {
        setState(() {
          data = r;
          error = null;
          selected = (r['selected']?['id'] as num?)?.toInt();
        });
      }
    } catch (e) {
      if (mounted && v == version) setState(() => error = e);
    } finally {
      if (v == version) loading = false;
    }
  }

  Future<void> send() async {
    if (busy || selected == null || body.text.trim().isEmpty) return;
    setState(() => busy = true);
    try {
      await widget.session.api.upload('/api/rider/messages', {
        'recipient_id': '$selected',
        'body': body.text.trim(),
      }, image == null ? {} : {'attachment': image!});
      body.clear();
      if (mounted) setState(() => image = null);
      await load();
    } catch (e) {
      if (mounted) toast(context, e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final contacts = data?['users'] as List? ?? [];
    final messages = data?['messages'] as List? ?? [];
    return Scaffold(
      appBar: AppBar(
        title: const Text('Sorting-center messages'),
        actions: [IconButton(onPressed: load, icon: const Icon(Icons.refresh))],
      ),
      body: data == null
          ? error == null
                ? const Center(child: CircularProgressIndicator())
                : LoadError(error: error!, retry: load)
          : Column(
              children: [
                if (contacts.isEmpty)
                  const InfoCard(
                    title: 'No contacts available',
                    text: 'Your approved sorting center will appear here.',
                  ),
                if (contacts.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.all(16),
                    child: DropdownButtonFormField<int>(
                      initialValue: selected,
                      isExpanded: true,
                      decoration: const InputDecoration(
                        labelText: 'Conversation',
                      ),
                      items: contacts
                          .map(
                            (u) => DropdownMenuItem<int>(
                              value: (u['id'] as num).toInt(),
                              child: Text('${u['name']}'),
                            ),
                          )
                          .toList(),
                      onChanged: busy
                          ? null
                          : (v) {
                              setState(() {
                                selected = v;
                                data = null;
                              });
                              load();
                            },
                    ),
                  ),
                if (error != null)
                  Text(
                    '$error',
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                Expanded(
                  child: ListView(
                    reverse: true,
                    padding: const EdgeInsets.all(16),
                    children: messages.reversed.map((m) {
                      final own = m['sender_id'] == widget.session.rider?.id;
                      return Align(
                        alignment: own
                            ? Alignment.centerRight
                            : Alignment.centerLeft,
                        child: Container(
                          constraints: const BoxConstraints(maxWidth: 420),
                          margin: const EdgeInsets.only(bottom: 10),
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: own ? const Color(0xFFDDECBB) : Colors.white,
                            borderRadius: BorderRadius.circular(18),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('${m['body'] ?? ''}'),
                              if (m['attachment_url'] != null)
                                Padding(
                                  padding: const EdgeInsets.only(top: 8),
                                  child: Image.network(
                                    widget.session.api.mediaUrl(
                                      m['attachment_url'],
                                    ),
                                    headers: widget.session.api.imageHeaders(
                                      m['attachment_url'],
                                    ),
                                    height: 180,
                                    fit: BoxFit.contain,
                                    errorBuilder: (_, e, s) =>
                                        const Text('Attachment unavailable'),
                                  ),
                                ),
                              Text(
                                shortDate(m['created_at']?.toString()),
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                            ],
                          ),
                        ),
                      );
                    }).toList(),
                  ),
                ),
                if (data?['selected']?['can_message'] == true)
                  SafeArea(
                    top: false,
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(
                        children: [
                          if (image != null) Text('Attached: ${image!.name}'),
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              IconButton(
                                onPressed: busy
                                    ? null
                                    : () async {
                                        try {
                                          final x = await ImagePicker()
                                              .pickImage(
                                                source: ImageSource.gallery,
                                                imageQuality: 80,
                                                maxWidth: 1600,
                                              );
                                          if (mounted) {
                                            setState(() => image = x);
                                          }
                                        } catch (e) {
                                          if (context.mounted) {
                                            toast(context, e);
                                          }
                                        }
                                      },
                                icon: const Icon(
                                  Icons.add_photo_alternate_outlined,
                                ),
                              ),
                              Expanded(
                                child: TextField(
                                  controller: body,
                                  minLines: 1,
                                  maxLines: 4,
                                  maxLength: 1000,
                                  decoration: const InputDecoration(
                                    hintText: 'Write a message',
                                    counterText: '',
                                  ),
                                ),
                              ),
                              IconButton(
                                onPressed: busy ? null : send,
                                icon: Icon(
                                  busy ? Icons.hourglass_top : Icons.send,
                                ),
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
}
