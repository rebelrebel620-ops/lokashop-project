import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import '../api.dart';
import '../models.dart';
import 'common.dart';

Color parcelStatusColor(String s) =>
    ['DELIVERED', 'COMPLETED', 'AT_SORTING_CENTER'].contains(s)
    ? const Color(0xFF287548)
    : ['DELIVERY_FAILED', 'FAILED', 'EXPIRED', 'CANCELLED'].contains(s)
    ? Colors.red.shade700
    : const Color(0xFF526B89);

class StatusChip extends StatelessWidget {
  final String label;
  final Color color;
  const StatusChip({super.key, required this.label, required this.color});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .10),
      borderRadius: BorderRadius.circular(30),
    ),
    child: Text(
      label,
      style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w700),
    ),
  );
}

class AssignmentCard extends StatefulWidget {
  final Assignment assignment;
  final ApiClient api;
  final ValueChanged<Assignment> onUpdated;
  final String? scannedCode;
  const AssignmentCard({
    super.key,
    required this.assignment,
    required this.api,
    required this.onUpdated,
    this.scannedCode,
  });
  @override
  State<AssignmentCard> createState() => _AssignmentCardState();
}

class _AssignmentCardState extends State<AssignmentCard> {
  bool busy = false;
  String? note;
  Future<void> run(ActionItem action) async {
    final a = widget.assignment;
    if (action.status == 'DELIVERED') {
      final done = await Navigator.push<bool>(
        context,
        MaterialPageRoute(
          builder: (_) => DeliveryScreen(
            assignment: a,
            api: widget.api,
            scannedCode: widget.scannedCode,
          ),
        ),
      );
      if (done == true) await refresh();
      return;
    }
    String? reason;
    if (action.needsReason) {
      reason = await showDialog<String>(
        context: context,
        builder: (ctx) => SimpleDialog(
          title: const Text('Why did delivery fail?'),
          children: ['Buyer not home', 'Wrong address', 'Refused']
              .map(
                (s) => SimpleDialogOption(
                  onPressed: () => Navigator.pop(ctx, s),
                  child: Padding(
                    padding: const EdgeInsets.all(10),
                    child: Text(s),
                  ),
                ),
              )
              .toList(),
        ),
      );
      if (reason == null) return;
    } else {
      final yes = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: Text(action.label),
          content: Text('Parcel ${a.trackingCode}\nConfirm this action?'),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Confirm'),
            ),
          ],
        ),
      );
      if (yes != true) return;
    }
    if (!mounted) return;
    setState(() {
      busy = true;
      note = null;
    });
    try {
      if (action.type == 'accept') {
        final r = await widget.api.accept(a.id);
        if (r.assignment != null) widget.onUpdated(r.assignment!);
      } else if (action.type == 'decline') {
        await widget.api.post('/api/rider/decline', {'id': a.id});
        await refresh();
      } else if (action.type == 'transfer_depart') {
        await widget.api.post('/api/rider/transfers/${a.parcelId}/depart', {});
        await refresh();
      } else {
        final data = await widget.api.post(
          '/api/rider/parcels/${a.parcelId}/status',
          {
            'status': action.status,
            if (reason != null) 'reason': reason,
            if (widget.scannedCode != null) 'scanned_code': widget.scannedCode,
          },
        );
        if (data['assignment'] is Map) {
          widget.onUpdated(
            Assignment.fromJson(Map<String, dynamic>.from(data['assignment'])),
          );
        }
      }
      if (mounted) setState(() => note = 'Saved.');
    } catch (e) {
      if (mounted) setState(() => note = '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> refresh() async {
    try {
      final r = await widget.api.scan(widget.assignment.trackingCode);
      if (mounted) widget.onUpdated(r);
    } catch (e) {
      if (mounted) setState(() => note = '$e');
    }
  }

  @override
  Widget build(BuildContext context) {
    final a = widget.assignment;
    final pay = a.payment;
    final seller = a.seller;
    final rec = a.recipient;
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  backgroundColor: const Color(0xFFEDF2E0),
                  child: Icon(
                    a.kind == 'transfer'
                        ? Icons.airport_shuttle_outlined
                        : a.isPickup
                        ? Icons.inventory_2_outlined
                        : Icons.local_shipping_outlined,
                    color: const Color(0xFF123E32),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        prettyStatus(a.kind),
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 18,
                        ),
                      ),
                      SelectableText(
                        a.trackingCode,
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
                IconButton(
                  onPressed: () =>
                      Clipboard.setData(ClipboardData(text: a.trackingCode)),
                  icon: const Icon(Icons.copy_outlined),
                  tooltip: 'Copy tracking code',
                ),
              ],
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                StatusChip(
                  label: prettyStatus(a.parcelStatus),
                  color: parcelStatusColor(a.parcelStatus),
                ),
                StatusChip(
                  label: prettyStatus(a.status),
                  color: parcelStatusColor(a.status.toUpperCase()),
                ),
              ],
            ),
            const SizedBox(height: 12),
            if (a.city.isNotEmpty) Text('${a.city}, ${a.province}'),
            if (a.status == 'offered' && a.expiresAt != null)
              Text(
                'Offer expires: ${shortDate(a.expiresAt)}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            if (seller != null) ...[
              const Divider(height: 28),
              Text(
                'PICK UP FROM',
                style: Theme.of(context).textTheme.labelSmall,
              ),
              Text(
                seller.name,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              SelectableText(seller.phone),
              if (seller.pickupAddress != null)
                Text(
                  ['street', 'barangay', 'city', 'province', 'postal_code']
                      .map((f) => seller.pickupAddress![f])
                      .where((v) => v != null && '$v'.isNotEmpty)
                      .join(', '),
                ),
            ],
            if (rec != null) ...[
              const Divider(height: 28),
              Text('DELIVER TO', style: Theme.of(context).textTheme.labelSmall),
              Text(
                rec.name,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              SelectableText(rec.phone),
              Text(
                '${rec.street}, ${rec.barangay}\n${a.city}, ${a.province} ${rec.postalCode ?? ''}',
              ),
            ],
            ...a.centers.asMap().entries.map(
              (e) => Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Text(
                  '${e.value['stage'] ?? 'Sorting center'}: ${e.value['name']}\n${e.value['city']}, ${e.value['province']}',
                ),
              ),
            ),
            if (a.items.isNotEmpty) ...[
              const Divider(height: 24),
              ...a.items.map((i) => Text('${i.quantity} × ${i.name}')),
            ],
            if (pay != null)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: Text(
                  pay.method == 'cod'
                      ? 'Collect COD: ${peso(pay.amount)}'
                      : '${prettyStatus(pay.method)} • ${prettyStatus(pay.status)}',
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            if (a.failureReason != null) Text('Reason: ${a.failureReason}'),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                ...a.actions.map(
                  (action) => FilledButton(
                    onPressed: busy ? null : () => run(action),
                    child: Text(action.label),
                  ),
                ),
                if (a.status == 'offered' && a.actions.isNotEmpty)
                  OutlinedButton(
                    onPressed: busy
                        ? null
                        : () => run(
                            const ActionItem(
                              type: 'decline',
                              label: 'Decline assignment',
                            ),
                          ),
                    child: const Text('Decline'),
                  ),
                OutlinedButton(
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) =>
                          JobHistory(assignment: a, api: widget.api),
                    ),
                  ),
                  child: const Text('Tracking history'),
                ),
              ],
            ),
            if (note != null)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Text(note!),
              ),
            if (busy) const LinearProgressIndicator(),
          ],
        ),
      ),
    );
  }
}

class DeliveryScreen extends StatefulWidget {
  final Assignment assignment;
  final ApiClient api;
  final String? scannedCode;
  const DeliveryScreen({
    super.key,
    required this.assignment,
    required this.api,
    this.scannedCode,
  });
  @override
  State<DeliveryScreen> createState() => _DeliveryScreenState();
}

class _DeliveryScreenState extends State<DeliveryScreen> {
  final otp = TextEditingController(), cod = TextEditingController();
  final form = GlobalKey<FormState>();
  XFile? proof;
  bool busy = false;
  String? error;
  @override
  void dispose() {
    otp.dispose();
    cod.dispose();
    super.dispose();
  }

  Future<void> photo(ImageSource source) async {
    try {
      final x = await ImagePicker().pickImage(
        source: source,
        maxWidth: 1800,
        imageQuality: 80,
      );
      if (x != null && mounted) {
        if (await x.length() > 5 * 1024 * 1024) {
          if (mounted) toast(context, 'Photo must be smaller than 5 MB.');
          return;
        }
        setState(() => proof = x);
      }
    } catch (e) {
      if (mounted) toast(context, e);
    }
  }

  Future<void> submit() async {
    if (!form.currentState!.validate()) return;
    if (proof == null) {
      setState(() => error = 'Take or choose the delivery proof photo first.');
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.api.upload(
        '/api/rider/parcels/${widget.assignment.parcelId}/status',
        {
          'status': 'DELIVERED',
          'otp': otp.text.trim(),
          if (widget.assignment.payment?.method == 'cod')
            'cod_amount': cod.text.trim(),
          if (widget.scannedCode != null) 'scanned_code': widget.scannedCode!,
        },
        {'proof': proof!},
      );
      if (mounted) {
        toast(context, 'Delivery saved. The buyer can now view the proof.');
        Navigator.pop(context, true);
      }
    } catch (e) {
      if (mounted) setState(() => error = '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final pay = widget.assignment.payment;
    return Scaffold(
      appBar: AppBar(title: const Text('Complete delivery')),
      body: Form(
        key: form,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            InfoCard(
              title: widget.assignment.trackingCode,
              text:
                  '1. Hand the parcel to the buyer\n2. Take a proof photo\n3. Ask for their delivery OTP\n4. Confirm payment and submit',
              icon: Icons.task_alt,
            ),
            const SizedBox(height: 12),
            if (proof != null)
              ClipRRect(
                borderRadius: BorderRadius.circular(18),
                child: Image.file(
                  File(proof!.path),
                  height: 230,
                  fit: BoxFit.contain,
                ),
              ),
            Wrap(
              spacing: 8,
              children: [
                OutlinedButton.icon(
                  onPressed: busy ? null : () => photo(ImageSource.camera),
                  icon: const Icon(Icons.camera_alt_outlined),
                  label: const Text('Take photo'),
                ),
                OutlinedButton.icon(
                  onPressed: busy ? null : () => photo(ImageSource.gallery),
                  icon: const Icon(Icons.photo_outlined),
                  label: const Text('Choose photo'),
                ),
              ],
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: otp,
              enabled: !busy,
              keyboardType: TextInputType.number,
              inputFormatters: [
                FilteringTextInputFormatter.digitsOnly,
                LengthLimitingTextInputFormatter(6),
              ],
              decoration: const InputDecoration(
                labelText: 'Buyer delivery OTP',
                helperText: 'The buyer receives this in their notifications.',
              ),
              validator: (s) => RegExp(r'^\d{6}$').hasMatch(s ?? '')
                  ? null
                  : 'Enter the 6-digit OTP',
            ),
            const SizedBox(height: 16),
            if (pay?.method == 'cod')
              TextFormField(
                controller: cod,
                enabled: !busy,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: InputDecoration(
                  labelText: 'COD collected',
                  helperText: 'Exact amount required: ${peso(pay!.amount)}',
                ),
                validator: (s) => double.tryParse(s ?? '') == pay.amount
                    ? null
                    : 'Collect and enter exactly ${peso(pay.amount)}',
              ),
            if (pay != null && pay.method != 'cod')
              Text(
                'Payment: ${prettyStatus(pay.method)} • ${prettyStatus(pay.status)}',
              ),
            if (error != null)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 14),
                child: Text(
                  error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            const SizedBox(height: 20),
            FilledButton.icon(
              onPressed: busy ? null : submit,
              icon: const Icon(Icons.check_circle_outline),
              label: Text(
                busy ? 'Uploading proof…' : 'Confirm parcel delivered',
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class JobHistory extends StatefulWidget {
  final Assignment assignment;
  final ApiClient api;
  const JobHistory({super.key, required this.assignment, required this.api});
  @override
  State<JobHistory> createState() => _JobHistoryState();
}

class _JobHistoryState extends State<JobHistory> {
  Map<String, dynamic>? data;
  Object? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final d = await widget.api.get(
        '/api/rider/assignments/${widget.assignment.id}',
      );
      if (mounted) setState(() => data = d);
    } catch (e) {
      if (mounted) setState(() => error = e);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Parcel history')),
    body: error != null
        ? LoadError(error: error!, retry: load)
        : data == null
        ? const Center(child: CircularProgressIndicator())
        : ListView(
            padding: const EdgeInsets.all(16),
            children: [
              InfoCard(
                title: '${data!['tracking_code']}',
                text: prettyStatus('${data!['parcel_status']}'),
                icon: Icons.route_outlined,
              ),
              ...(data!['events'] as List? ?? []).map(
                (x) => ListTile(
                  leading: const Icon(Icons.check_circle_outline),
                  title: Text(prettyStatus('${x['to_status']}')),
                  subtitle: Text(
                    '${x['reason'] ?? ''}\n${shortDate(x['created_at']?.toString())}',
                  ),
                ),
              ),
              if (data!['proof_url'] != null) ...[
                const Padding(
                  padding: EdgeInsets.all(12),
                  child: Text(
                    'Delivery proof',
                    style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
                  ),
                ),
                Image.network(
                  widget.api.mediaUrl(data!['proof_url']),
                  headers: widget.api.imageHeaders(data!['proof_url']),
                  errorBuilder: (_, e, s) => const Text(
                    'Proof unavailable. Check shared media storage.',
                  ),
                ),
              ],
            ],
          ),
  );
}
