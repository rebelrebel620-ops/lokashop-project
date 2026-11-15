import 'package:flutter/material.dart';
import '../../models.dart';

const centerForest = Color(0xFF123E32);
const centerMuted = Color(0xFF718278);
const centerLime = Color(0xFFE5F3C7);

/// Display formatting only; never used for wallet calculations or submissions.
String centerMoney(dynamic raw) {
  final value = double.tryParse('$raw'.replaceAll(',', '').replaceAll('PHP', '').replaceAll('₱', '').trim());
  if (value == null || !value.isFinite) return '$raw';
  final parts = value.abs().toStringAsFixed(2).split('.');
  final whole = parts[0].replaceAllMapped(RegExp(r'\B(?=(\d{3})+(?!\d))'), (_) => ',');
  return '${value < 0 ? '-' : ''}₱$whole.${parts[1]}';
}

class CenterSurface extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;
  const CenterSurface({super.key, required this.child, this.padding = const EdgeInsets.all(18)});
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 14), padding: padding,
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(18),
      border: Border.all(color: const Color(0xFFE2E8DD)),
      boxShadow: const [BoxShadow(color: Color(0x07123E32), blurRadius: 14, offset: Offset(0, 4))]),
    child: child,
  );
}

class CenterSectionHeader extends StatelessWidget {
  final String title, subtitle;
  const CenterSectionHeader({super.key, required this.title, required this.subtitle});
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 6, bottom: 22),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
        decoration: BoxDecoration(color: centerLime, borderRadius: BorderRadius.circular(20)),
        child: const Text('FINANCE & REPORTS', style: TextStyle(fontSize: 10, letterSpacing: 1.2, color: centerForest, fontWeight: FontWeight.w800))),
      const SizedBox(height: 12),
      Text(title, style: const TextStyle(fontSize: 27, height: 1.2, fontWeight: FontWeight.w800, color: centerForest)),
      const SizedBox(height: 8),
      Text(subtitle, style: const TextStyle(fontSize: 14, color: centerMuted)),
    ]),
  );
}

class CenterBalancePanel extends StatelessWidget {
  final String label, amount;
  final String? caption;
  final bool demo;
  const CenterBalancePanel({super.key, required this.label, required this.amount, this.caption, this.demo = false});
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 16), padding: const EdgeInsets.all(22),
    decoration: BoxDecoration(borderRadius: BorderRadius.circular(20),
      gradient: const LinearGradient(colors: [centerForest, Color(0xFF21654C)])),
    child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Row(children: [
        const Icon(Icons.account_balance_wallet_outlined, color: Color(0xFFDDECBB), size: 27),
        const SizedBox(width: 12),
        Expanded(child: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600))),
      ]),
      if (demo) const Padding(padding: EdgeInsets.only(top: 12), child: Text('DEMO CURRENCY', style: TextStyle(fontSize: 10, letterSpacing: 1.3, color: Color(0xFFDDECBB)))),
      const SizedBox(height: 14),
      FittedBox(fit: BoxFit.scaleDown, alignment: Alignment.centerLeft,
        child: Text(amount, style: const TextStyle(color: Colors.white, fontSize: 38, fontWeight: FontWeight.w800))),
      if (caption != null) ...[
        const SizedBox(height: 12),
        Text(caption!, style: const TextStyle(fontSize: 12, height: 1.5, color: Color(0xFFD4E5DB))),
      ],
    ]),
  );
}

class CenterFinanceSummary extends StatelessWidget {
  final List metrics;
  const CenterFinanceSummary({super.key, required this.metrics});
  @override
  Widget build(BuildContext context) {
    final balances = metrics.where((m) => m['label'] == 'Wallet (PHP)').toList();
    final remaining = metrics.where((m) => m['label'] != 'Wallet (PHP)').toList();
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      if (balances.isNotEmpty) CenterBalancePanel(label: 'Wallet balance', amount: centerMoney(balances.first['value'])),
      CenterSurface(child: Column(children: [
        for (int i = 0; i < remaining.length; i++) ...[
          if (i > 0) const Divider(height: 24, color: Color(0xFFE2E8DD)),
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Icon(Icons.receipt_long_outlined, color: centerMuted, size: 18),
            const SizedBox(width: 10),
            Expanded(child: Text('${remaining[i]['label']}', style: const TextStyle(fontSize: 13, color: centerMuted))),
            const SizedBox(width: 10),
            Flexible(child: Text(centerMoney(remaining[i]['value']), textAlign: TextAlign.right,
              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: centerForest))),
          ]),
        ],
      ])),
    ]);
  }
}

class CenterEmptyState extends StatelessWidget {
  final String title, message;
  final IconData icon;
  const CenterEmptyState({super.key, required this.title, required this.message, this.icon = Icons.receipt_long_outlined});
  @override
  Widget build(BuildContext context) => CenterSurface(
    padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 36),
    child: Column(children: [
      Container(padding: const EdgeInsets.all(18), decoration: const BoxDecoration(color: centerLime, shape: BoxShape.circle), child: Icon(icon, color: centerForest, size: 35)),
      const SizedBox(height: 18),
      Text(title, textAlign: TextAlign.center, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: centerForest)),
      const SizedBox(height: 8),
      Text(message, textAlign: TextAlign.center, style: const TextStyle(fontSize: 13, height: 1.5, color: centerMuted)),
    ]),
  );
}

class CenterRecordCard extends StatelessWidget {
  final Map row;
  final void Function(Map) onAction;
  const CenterRecordCard({super.key, required this.row, required this.onAction});
  @override
  Widget build(BuildContext context) {
    final facts = row['facts'] as List? ?? [];
    final status = '${row['status'] ?? ''}';
    return CenterSurface(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Text('${row['title'] ?? 'Record'}', style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: centerForest)),
      if (status.isNotEmpty) Align(alignment: Alignment.centerLeft, child: Padding(
        padding: const EdgeInsets.only(top: 10),
        child: Chip(backgroundColor: centerLime, side: BorderSide.none,
          label: Text(prettyStatus(status), style: const TextStyle(fontSize: 11, color: centerForest))),
      )),
      const SizedBox(height: 10),
      for (final fact in facts)
        Padding(padding: const EdgeInsets.symmetric(vertical: 6), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(child: Text('${fact['label']}', style: const TextStyle(fontSize: 12, color: centerMuted))),
          const SizedBox(width: 12),
          Expanded(flex: 2, child: Text(
            '${fact['label']}'.contains('(PHP)') ? centerMoney(fact['value'])
              : ['Type', 'Status'].contains('${fact['label']}') ? prettyStatus('${fact['value']}') : '${fact['value']}',
            textAlign: TextAlign.right, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: centerForest))),
        ])),
      if ((row['actions'] as List? ?? []).isNotEmpty) ...[
        const SizedBox(height: 12),
        for (final action in row['actions']) Padding(padding: const EdgeInsets.only(top: 8),
          child: OutlinedButton(onPressed: () => onAction(Map.from(action)), child: Text('${action['label']}'))),
      ],
    ]));
  }
}
