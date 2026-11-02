import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import 'design.dart';

class CustomerReviews extends StatefulWidget {
  final int productId;
  final Map initial;
  const CustomerReviews({super.key, required this.productId, required this.initial});
  @override
  State<CustomerReviews> createState() => _CustomerReviewsState();
}
class _CustomerReviewsState extends State<CustomerReviews> {
  late Map page = Map.from(widget.initial['reviews'] ?? {});
  bool busy = false;
  String? error;
  Future<void> change(int number) async {
    setState(() { busy = true; error = null; });
    try {
      final result = await context.read<AppState>().api.get('/products/${widget.productId}', query: {'review_page': number});
      if (mounted) setState(() => page = Map.from(result['reviews'] ?? {}));
    } catch (e) { if (mounted) setState(() => error = '$e'); }
    finally { if (mounted) setState(() => busy = false); }
  }
  @override
  Widget build(BuildContext context) {
    final summary = widget.initial['review_summary'] as Map? ?? {};
    final count = int.tryParse('${summary['count']}') ?? 0;
    final distribution = summary['distribution'];
    final current = int.tryParse('${page['current_page']}') ?? 1;
    final last = int.tryParse('${page['last_page']}') ?? 1;
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const SectionHeading('Customer reviews'),
      Text(count == 0 ? 'No customer reviews yet' : '${summary['average']} out of 5 · $count reviews', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
      if (count == 0) const Text('Buyers can review after completing their purchase.'),
      if (count > 0) ...[5,4,3,2,1].map((stars) {
        final n = distribution is Map ? int.tryParse('${distribution['$stars']}') ?? 0 : 0;
        return Padding(padding: const EdgeInsets.symmetric(vertical: 4), child: Row(children: [
          Text('$stars ★  '), Expanded(child: LinearProgressIndicator(value: n / count)), Text('  $n'),
        ]));
      }),
      ...(page['data'] as List? ?? []).map<Widget>((r) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: SheetCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('${r['reviewer_name']} · ${r['stars']} ★', style: const TextStyle(fontWeight: FontWeight.bold)),
          Text('Verified purchase · ${r['created_at']}', style: const TextStyle(fontSize: 11)),
          const SizedBox(height: 8), Text('${r['comment'] ?? 'This buyer left a star rating.'}'),
          if ((r['photo_urls'] as List? ?? []).isNotEmpty) SizedBox(height: 90, child: ListView(
            scrollDirection: Axis.horizontal,
            children: (r['photo_urls'] as List).map<Widget>((url) => Padding(
              padding: const EdgeInsets.all(4), child: InkWell(
                onTap: () => showDialog<void>(context: context, builder: (_) => Dialog(child: InteractiveViewer(child: AppPhoto('$url', fit: BoxFit.contain)))),
                child: AppPhoto('$url', width: 80, height: 80),
              ),
            )).toList(),
          )),
          if (r['seller_response'] != null) ...[
            const Divider(), const Text("Seller's reply", style: TextStyle(fontWeight: FontWeight.bold)), Text('${r['seller_response']}'),
          ],
        ])),
      )),
      if (error != null) Text(error!),
      if (last > 1) Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        TextButton(onPressed: busy || current <= 1 ? null : () => change(current - 1), child: const Text('Previous')),
        Text(busy ? 'Loading…' : '$current / $last'),
        TextButton(onPressed: busy || current >= last ? null : () => change(current + 1), child: const Text('Next')),
      ]),
    ]);
  }
}
