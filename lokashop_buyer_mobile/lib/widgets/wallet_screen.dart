import 'dart:math';
import 'package:flutter/material.dart';
import 'auto_refresh.dart';

typedef WalletLoad = Future<dynamic> Function(Map<String, String> query);
typedef WalletSend = Future<dynamic> Function(Map<String, dynamic> fields);

/// Uses the website's wallet ledger and transfer endpoints; no local balance math.
class WalletScreen extends StatefulWidget {
  final WalletLoad fetch;
  final WalletSend transfer;
  const WalletScreen({super.key, required this.fetch, required this.transfer});
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
  @override
  Widget build(BuildContext context) {
    final transactions = data?['transactions'] as Map? ?? {};
    final jobs = data?['courier_payments'] as Map?;
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
                    Form(
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
                    ),
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
