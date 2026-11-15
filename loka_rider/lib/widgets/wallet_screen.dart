import 'dart:math';
import 'package:flutter/material.dart';
import 'auto_refresh.dart';
import '../screens/center/center_finance_widgets.dart';

typedef WalletLoad = Future<dynamic> Function(Map<String, String> query);
typedef WalletSend = Future<dynamic> Function(Map<String, dynamic> fields);

/// Uses the website's wallet ledger and transfer endpoints; no local balance math.
class WalletScreen extends StatefulWidget {
  final WalletLoad fetch;
  final WalletSend transfer;
  final bool centerDesign;
  const WalletScreen({super.key, required this.fetch, required this.transfer, this.centerDesign = false});
  @override
  State<WalletScreen> createState() => _WalletScreenState();
}

class _WalletScreenState extends State<WalletScreen>
    with AutoRefresh<WalletScreen> {
  Map? data;
  String? error;
  bool loading = false, sending = false;
  int page = 1, paymentsPage = 1;
  final form = GlobalKey<FormState>();
  final recipient = TextEditingController(),
      amount = TextEditingController(),
      password = TextEditingController();
  String key = _key();
  static String _key() {
    final random = Random.secure();
    return List.generate(
      24,
      (_) => random.nextInt(256).toRadixString(16).padLeft(2, '0'),
    ).join();
  }

  static String pesos(dynamic value) =>
      '₱${(double.tryParse('$value') ?? 0).toStringAsFixed(2)}';
  static String cents(dynamic value) =>
      pesos((double.tryParse('$value') ?? 0) / 100);
  @override
  bool get autoRefreshEnabled => !loading && !sending;
  @override
  Future<void> refreshAutomatically() => load(silent: true);
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load({bool silent = false}) async {
    if (loading) {
      return;
    }
    loading = true;
    try {
      final result = await widget.fetch({
        'page': '$page',
        'payments_page': '$paymentsPage',
      });
      if (mounted) {
        setState(() {
          data = Map.from(result as Map);
          error = null;
        });
      }
    } catch (e) {
      if (mounted && !silent) {
        setState(() => error = '$e');
      }
    } finally {
      loading = false;
      if (mounted) {
        setState(() {});
      }
    }
  }

  Future<void> send() async {
    if (sending || !(form.currentState?.validate() ?? false)) {
      return;
    }
    final yes = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Send wallet funds?'),
        content: Text(
          'Send ${pesos(amount.text)} to ${recipient.text.trim()}?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(c, true),
            child: const Text('Send money'),
          ),
        ],
      ),
    );
    if (yes != true || !mounted || sending) {
      return;
    }
    setState(() => sending = true);
    try {
      await widget.transfer({
        'recipient': recipient.text.trim(),
        'amount': amount.text.trim(),
        'password': password.text,
        '_request_key': key,
      });
      key = _key();
      password.clear();
      amount.clear();
      recipient.clear();
      page = 1;
      await load();
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Transfer completed.')));
      }
    } catch (e) {
      // Retain the same key for retrying the same transfer after a timeout.
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('$e')));
      }
    } finally {
      if (mounted) {
        setState(() => sending = false);
      }
    }
  }

  @override
  void dispose() {
    recipient.dispose();
    amount.dispose();
    password.dispose();
    super.dispose();
  }

  Widget pages(Map block, int current, void Function(int) change) => Row(
    mainAxisAlignment: MainAxisAlignment.spaceBetween,
    children: [
      TextButton(
        onPressed: loading || sending || current <= 1
            ? null
            : () => change(current - 1),
        child: const Text('Previous'),
      ),
      Text('$current / ${block['last_page'] ?? 1}'),
      TextButton(
        onPressed:
            loading ||
                sending ||
                current >= (int.tryParse('${block['last_page']}') ?? 1)
            ? null
            : () => change(current + 1),
        child: const Text('Next'),
      ),
    ],
  );

  Widget centerWallet(Map transactions, Map? jobs) => Theme(
    data: Theme.of(context).copyWith(
      inputDecorationTheme: InputDecorationTheme(
        filled: true, fillColor: const Color(0xFFF6F8F4),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFDCE5DB))),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFDCE5DB))),
        errorMaxLines: 3,
      ),
    ),
    child: Scaffold(
      appBar: AppBar(
        title: const Text('LokaShop Wallet', style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700)),
        actions: [IconButton(onPressed: loading || sending ? null : () => load(), icon: const Icon(Icons.refresh), tooltip: 'Refresh')],
      ),
      body: data == null && loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: () => load(),
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(18),
                children: [
                  const CenterSectionHeader(title: 'Your wallet', subtitle: 'Balance, transfers and payment activity in one place.'),
                  if (error != null) Padding(padding: const EdgeInsets.only(bottom: 14), child: Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error))),
                  if (data != null) ...[
                    CenterBalancePanel(
                      label: 'Available balance', amount: centerMoney(data!['balance']), demo: data!['demo'] == true,
                      caption: 'Eligible earnings and refunds arrive automatically. No claim request is needed.',
                    ),
                    if ((double.tryParse('${data!['center_owed']}') ?? 0) > 0)
                      CenterSurface(child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        const Icon(Icons.info_outline, color: centerMuted, size: 20),
                        const SizedBox(width: 12),
                        const Expanded(child: Text('Legacy center funding owed', style: TextStyle(fontSize: 13, color: centerMuted))),
                        const SizedBox(width: 10),
                        Flexible(child: Text(centerMoney((double.tryParse('${data!['center_owed']}') ?? 0) / 100), textAlign: TextAlign.right, style: const TextStyle(color: centerForest, fontWeight: FontWeight.w700))),
                      ])),
                    CenterSurface(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                      const Text('Send money', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: centerForest)),
                      const SizedBox(height: 6),
                      const Text('Transfers use existing wallet funds and require your password.', style: TextStyle(fontSize: 12, height: 1.5, color: centerMuted)),
                      const SizedBox(height: 20),
                      transferForm(),
                    ])),
                    if (jobs != null) ...[
                      const Padding(padding: EdgeInsets.only(top: 10, bottom: 12), child: Text('Job earnings / courier payments', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: centerForest))),
                      if ((jobs['data'] as List? ?? []).isEmpty)
                        const CenterEmptyState(title: 'No job payments yet', message: 'Completed jobs will appear here automatically.', icon: Icons.payments_outlined),
                      for (final job in jobs['data'] as List? ?? []) CenterSurface(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('Job #${job['assignment_id']}', style: const TextStyle(fontWeight: FontWeight.w700, color: centerForest)),
                        const SizedBox(height: 8),
                        Text(centerMoney((double.tryParse('${job['amount_cents']}') ?? 0) / 100), style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: centerForest)),
                        const SizedBox(height: 8),
                        Text('${job['status'] == 'paid' ? 'Credited automatically' : 'Processing automatically'}\n${job['funding_source'] == 'shipping_pool' ? 'Paid from shipping pool' : job['center_settled'] == true ? 'Settled' : 'Legacy center funding outstanding'}', style: const TextStyle(fontSize: 12, height: 1.5, color: centerMuted)),
                      ])),
                      if ((int.tryParse('${jobs['last_page']}') ?? 1) > 1) pages(jobs, paymentsPage, (v) { setState(() => paymentsPage = v); load(); }),
                    ],
                    const Padding(padding: EdgeInsets.only(top: 12, bottom: 14), child: Text('Transaction history', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: centerForest))),
                    if ((transactions['data'] as List? ?? []).isEmpty)
                      const CenterEmptyState(title: 'No transactions yet', message: 'Your wallet activity will appear here after a transaction.'),
                    for (final transaction in transactions['data'] as List? ?? []) CenterSurface(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                      Text('${transaction['description'] ?? transaction['kind']}', style: const TextStyle(fontWeight: FontWeight.w700, color: centerForest)),
                      const SizedBox(height: 8),
                      Text('${transaction['created_at'] ?? ''}', style: const TextStyle(fontSize: 12, color: centerMuted)),
                      const SizedBox(height: 12),
                      Text('${(double.tryParse('${transaction['amount_cents']}') ?? 0) >= 0 ? '+' : ''}${centerMoney((double.tryParse('${transaction['amount_cents']}') ?? 0) / 100)}', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: centerForest)),
                      const SizedBox(height: 6),
                      Text('Balance after: ${centerMoney((double.tryParse('${transaction['balance_after_cents']}') ?? 0) / 100)}', style: const TextStyle(fontSize: 12, color: centerMuted)),
                    ])),
                    if ((int.tryParse('${transactions['last_page']}') ?? 1) > 1) pages(transactions, page, (v) { setState(() => page = v); load(); }),
                  ],
                ],
              ),
            ),
    ),
  );

  Widget transferForm() => Form(
                      key: form,
                      child: Column(
                        children: [
                          TextFormField(
                            controller: recipient,
                            enabled: !sending,
                            keyboardType: TextInputType.emailAddress,
                            decoration: const InputDecoration(
                              labelText: 'Recipient email',
                            ),
                            onChanged: (_) => key = _key(),
                            validator: (s) =>
                                RegExp(
                                  r'^[^\s@]+@[^\s@]+\.[^\s@]+$',
                                ).hasMatch(s?.trim() ?? '')
                                ? null
                                : 'Enter a valid email',
                          ),
                          if (widget.centerDesign) const SizedBox(height: 16),
                          TextFormField(
                            controller: amount,
                            enabled: !sending,
                            keyboardType: const TextInputType.numberWithOptions(
                              decimal: true,
                            ),
                            decoration: const InputDecoration(
                              labelText: 'Amount (₱)',
                            ),
                            onChanged: (_) => key = _key(),
                            validator: (s) {
                              final text = s?.trim() ?? '',
                                  n = double.tryParse(s?.trim() ?? '');
                              return RegExp(
                                        r'^\d+(\.\d{1,2})?$',
                                      ).hasMatch(text) &&
                                      n != null &&
                                      n >= 1 &&
                                      n <= 100000
                                  ? null
                                  : 'Enter ₱1–₱100,000, at most 2 decimal places';
                            },
                          ),
                          if (widget.centerDesign) const SizedBox(height: 16),
                          TextFormField(
                            controller: password,
                            enabled: !sending,
                            obscureText: true,
                            enableSuggestions: false,
                            autocorrect: false,
                            decoration: const InputDecoration(
                              labelText: 'Your password',
                            ),
                            validator: (s) => (s ?? '').isEmpty
                                ? 'Enter your password'
                                : null,
                          ),
                          const SizedBox(height: 16),
                          FilledButton(
                            onPressed: sending ? null : send,
                            child: Text(sending ? 'Sending…' : 'Send money'),
                          ),
                        ],
                      ),
                    );

  @override
  Widget build(BuildContext context) {
    final transactions = data?['transactions'] as Map? ?? {};
    final jobs = data?['courier_payments'] as Map?;
    if (widget.centerDesign) return centerWallet(transactions, jobs);
    return Scaffold(
      appBar: AppBar(
        title: const Text('LokaShop Wallet'),
        actions: [
          IconButton(
            onPressed: loading || sending ? null : () => load(),
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: data == null && loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: () => load(),
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(20),
                children: [
                  if (error != null)
                    Text(
                      error!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                  if (data != null) ...[
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (data!['demo'] == true)
                              const Text('DEMO CURRENCY'),
                            const Text('Available balance'),
                            Text(
                              pesos(data!['balance']),
                              style: const TextStyle(
                                fontSize: 34,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            const Text(
                              'Eligible earnings and refunds arrive automatically. No claim request is needed.',
                            ),
                          ],
                        ),
                      ),
                    ),
                    if ((double.tryParse('${data!['center_owed']}') ?? 0) > 0)
                      ListTile(
                        title: const Text('Legacy center funding owed'),
                        subtitle: Text(cents(data!['center_owed'])),
                      ),
                    const SizedBox(height: 16),
                    const Text(
                      'Send money',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const Text(
                      'Transfers use existing wallet funds and require your password.',
                    ),
                    transferForm(),
                    if (jobs != null) ...[
                      const Divider(height: 36),
                      const Text(
                        'Job earnings / courier payments',
                        style: TextStyle(
                          fontSize: 19,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      if ((jobs['data'] as List? ?? []).isEmpty)
                        const Text(
                          'Completed jobs will appear here automatically.',
                        ),
                      ...(jobs['data'] as List? ?? []).map(
                        (j) => ListTile(
                          title: Text(
                            'Job #${j['assignment_id']} · ${cents(j['amount_cents'])}',
                          ),
                          subtitle: Text(
                            '${j['status'] == 'paid' ? 'Credited automatically' : 'Processing automatically'}\n${j['funding_source'] == 'shipping_pool'
                                ? 'Paid from shipping pool'
                                : j['center_settled'] == true
                                ? 'Settled'
                                : 'Legacy center funding outstanding'}',
                          ),
                        ),
                      ),
                      pages(jobs, paymentsPage, (v) {
                        setState(() => paymentsPage = v);
                        load();
                      }),
                    ],
                    const Divider(height: 36),
                    const Text(
                      'Transaction history',
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    if ((transactions['data'] as List? ?? []).isEmpty)
                      const Text('No transactions yet.'),
                    ...(transactions['data'] as List? ?? []).map(
                      (t) => ListTile(
                        title: Text('${t['description'] ?? t['kind']}'),
                        isThreeLine: true,
                        subtitle: Text(
                          '${t['created_at'] ?? ''}\nBalance after: ${cents(t['balance_after_cents'])}',
                        ),
                        trailing: Text(
                          '${(double.tryParse('${t['amount_cents']}') ?? 0) >= 0 ? '+' : ''}${cents(t['amount_cents'])}',
                        ),
                      ),
                    ),
                    pages(transactions, page, (v) {
                      setState(() => page = v);
                      load();
                    }),
                  ],
                ],
              ),
            ),
    );
  }
}
