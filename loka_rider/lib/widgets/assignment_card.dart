import 'package:flutter/material.dart';

import '../api.dart';
import '../models.dart';

/// Same colour families as components/badge.blade.php on the web portal.
Color parcelStatusColor(String status) {
  switch (status.toUpperCase()) {
    case 'CONFIRMED':
    case 'SORTED':
    case 'ASSIGNED_TO_RIDER':
    case 'ACCEPTED':
      return Colors.blue;
    case 'PREPARING':
    case 'READY_FOR_PICKUP':
    case 'REQUESTED':
    case 'OFFERED':
      return Colors.amber.shade800;
    case 'PICKED_UP':
      return Colors.purple;
    case 'OUT_FOR_DELIVERY':
      return Colors.teal;
    case 'AT_SORTING_CENTER':
    case 'DELIVERED':
    case 'COMPLETED':
      return Colors.green.shade700;
    case 'DELIVERY_FAILED':
    case 'FAILED':
      return Colors.red;
    default:
      return Colors.blueGrey;
  }
}

class StatusChip extends StatelessWidget {
  final String label;
  final Color color;
  const StatusChip({super.key, required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w700)),
    );
  }
}

/// One pickup/delivery job. The buttons come from the server (`actions`), so the
/// app can never offer a step that the Workflow state machine would reject.
class AssignmentCard extends StatefulWidget {
  final Assignment assignment;
  final ApiClient api;
  final ValueChanged<Assignment> onUpdated;

  const AssignmentCard({super.key, required this.assignment, required this.api, required this.onUpdated});

  @override
  State<AssignmentCard> createState() => _AssignmentCardState();
}

class _AssignmentCardState extends State<AssignmentCard> {
  bool _busy = false;
  String? _note;
  bool _noteIsError = false;

  Future<void> _run(ActionItem action) async {
    final a = widget.assignment;
    String? reason;

    if (action.needsReason) {
      reason = await showDialog<String>(context: context, builder: (_) => const _ReasonDialog());
      if (reason == null) return;
    } else if (action.status == 'PICKED_UP' || action.status == 'DELIVERED') {
      final ok = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: Text(action.label),
          content: Text('Parcel ${a.trackingCode}\n\nThis cannot be undone. Continue?'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
            FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Confirm')),
          ],
        ),
      );
      if (ok != true) return;
    }

    if (!mounted) return;
    setState(() {
      _busy = true;
      _note = null;
    });

    try {
      final result = action.type == 'accept'
          ? await widget.api.accept(a.id)
          : await widget.api.setStatus(a.parcelId, action.status!, reason: reason);
      if (!mounted) return;
      setState(() {
        _note = result.message;
        _noteIsError = false;
      });
      final updated = result.assignment;
      if (updated != null) widget.onUpdated(updated);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _note = e.message;
          _noteIsError = true;
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final a = widget.assignment;
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurfaceVariant;
    final rec = a.recipient;
    final sel = a.seller;
    final pay = a.payment;

    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  backgroundColor: theme.colorScheme.primaryContainer,
                  child: Icon(a.isPickup ? Icons.inventory_2_outlined : Icons.local_shipping_outlined,
                      color: theme.colorScheme.onPrimaryContainer),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('${a.isPickup ? 'Pickup' : 'Delivery'} \u00B7 Parcel #${a.parcelId}',
                          style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                      SelectableText(a.trackingCode, style: theme.textTheme.bodySmall?.copyWith(color: muted)),
                    ],
                  ),
                ),
                StatusChip(label: prettyStatus(a.parcelStatus), color: parcelStatusColor(a.parcelStatus)),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Icon(Icons.place_outlined, size: 16, color: muted),
                const SizedBox(width: 4),
                Expanded(child: Text('${a.city}, ${a.province}', style: theme.textTheme.bodyMedium)),
              ],
            ),
            const SizedBox(height: 4),
            Row(
              children: [
                Icon(Icons.payments_outlined, size: 16, color: muted),
                const SizedBox(width: 4),
                Text('Earnings ${peso(a.earnings)}', style: theme.textTheme.bodyMedium),
                const Spacer(),
                StatusChip(label: 'Job: ${prettyStatus(a.status)}', color: parcelStatusColor(a.status)),
              ],
            ),
            if (rec != null) ...[
              const Divider(height: 24),
              _Section(title: 'Deliver to', lines: [
                rec.name,
                if (rec.phone.isNotEmpty) rec.phone,
                [rec.street, rec.barangay].where((s) => s.isNotEmpty).join(', '),
                [a.city, a.province, if ((rec.postalCode ?? '').isNotEmpty) rec.postalCode!].join(', '),
              ]),
            ],
            if (sel != null) ...[
              const Divider(height: 24),
              _Section(title: 'Pick up from seller', lines: [sel.name, if (sel.phone.isNotEmpty) sel.phone]),
            ],
            if (a.items.isNotEmpty) ...[
              const SizedBox(height: 12),
              _Section(title: 'Items', lines: [for (final i in a.items) '${i.quantity} \u00D7 ${i.name}']),
            ],
            if (pay != null) ...[
              const SizedBox(height: 12),
              _PaymentLine(payment: pay),
            ],
            if (a.parcelStatus == 'DELIVERY_FAILED' && (a.failureReason ?? '').isNotEmpty) ...[
              const SizedBox(height: 12),
              Text('Failure reason: ${a.failureReason}', style: TextStyle(color: theme.colorScheme.error)),
            ],
            if (_note != null) ...[
              const SizedBox(height: 12),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: (_noteIsError ? theme.colorScheme.errorContainer : Colors.green.shade50),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  _note!,
                  style: TextStyle(color: _noteIsError ? theme.colorScheme.onErrorContainer : Colors.green.shade900),
                ),
              ),
            ],
            if (a.actions.isNotEmpty) ...[
              const SizedBox(height: 14),
              if (_busy)
                const Center(child: Padding(padding: EdgeInsets.all(6), child: CircularProgressIndicator()))
              else
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final action in a.actions)
                      action.isDestructive
                          ? OutlinedButton(
                              onPressed: () => _run(action),
                              style: OutlinedButton.styleFrom(foregroundColor: theme.colorScheme.error),
                              child: Text(action.label),
                            )
                          : FilledButton(onPressed: () => _run(action), child: Text(action.label)),
                  ],
                ),
            ],
          ],
        ),
      ),
    );
  }
}

class _Section extends StatelessWidget {
  final String title;
  final List<String> lines;
  const _Section({required this.title, required this.lines});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title.toUpperCase(),
            style: theme.textTheme.labelSmall
                ?.copyWith(color: theme.colorScheme.onSurfaceVariant, fontWeight: FontWeight.w700, letterSpacing: 0.6)),
        const SizedBox(height: 4),
        for (final l in lines.where((l) => l.trim().isNotEmpty)) SelectableText(l, style: theme.textTheme.bodyMedium),
      ],
    );
  }
}

class _PaymentLine extends StatelessWidget {
  final Payment payment;
  const _PaymentLine({required this.payment});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final collect = payment.method.toLowerCase() == 'cod' && payment.status.toLowerCase() != 'paid';
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: collect ? Colors.amber.shade50 : theme.colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        collect
            ? 'Collect ${peso(payment.amount)} (cash on delivery)'
            : 'Payment: ${payment.method.toUpperCase()} \u00B7 ${prettyStatus(payment.status)} \u00B7 ${peso(payment.amount)}',
        style: TextStyle(fontWeight: FontWeight.w600, color: collect ? Colors.amber.shade900 : null),
      ),
    );
  }
}

class _ReasonDialog extends StatefulWidget {
  const _ReasonDialog();

  @override
  State<_ReasonDialog> createState() => _ReasonDialogState();
}

class _ReasonDialogState extends State<_ReasonDialog> {
  final _controller = TextEditingController();
  String? _error;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _submit() {
    final text = _controller.text.trim();
    if (text.isEmpty) {
      setState(() => _error = 'Enter a failure reason.');
      return;
    }
    Navigator.pop(context, text);
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Delivery failed'),
      content: TextField(
        controller: _controller,
        autofocus: true,
        maxLines: 3,
        maxLength: 2000,
        decoration: InputDecoration(labelText: 'Reason', errorText: _error, border: const OutlineInputBorder()),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
        FilledButton(onPressed: _submit, child: const Text('Submit')),
      ],
    );
  }
}
