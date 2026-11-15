import 'dart:math' as math;
import 'package:flutter/material.dart';

class OverviewChartsPanel extends StatelessWidget {
  final Map data;
  const OverviewChartsPanel({super.key, required this.data});
  static const colors = [Color(0xFF146B43), Color(0xFF65A46F), Color(0xFFB9DC91), Color(0xFFD5AD58), Color(0xFF608E88), Color(0xFFA6BCB1)];
  @override
  Widget build(BuildContext context) {
    final days = List<Map>.from(data['days'] ?? []), segments = List<Map>.from(data['segments'] ?? []);
    final total = segments.fold<double>(0, (sum, s) => sum + (num.tryParse('${s['value']}')?.toDouble() ?? 0));
    Widget card(Widget child) => Card(color: Colors.white, child: Padding(padding: const EdgeInsets.all(18), child: child));
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      card(Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('${data['title'] ?? 'Activity'} · last 7 days', style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Color(0xFF174B32))),
        const SizedBox(height: 6), Text('New ${data['noun'] ?? 'records'} created daily'),
        const SizedBox(height: 12), SizedBox(height: 135, width: double.infinity, child: ExcludeSemantics(child: CustomPaint(painter: _ActivityPainter(days)))),
        ExpansionTile(tilePadding: EdgeInsets.zero, title: const Text('View daily counts', style: TextStyle(fontSize: 12)), children: [for (final day in days) ListTile(dense: true, title: Text('${day['label']}'), trailing: Text('${day['value']}'))]),
      ])),
      card(Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('Status breakdown', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Color(0xFF174B32))),
        const SizedBox(height: 16), Center(child: SizedBox(width: 165, height: 165, child: Stack(alignment: Alignment.center, children: [
          Positioned.fill(child: ExcludeSemantics(child: CustomPaint(painter: _StatusPainter(segments, colors)))),
          Column(mainAxisSize: MainAxisSize.min, children: [Text('${total.toInt()}', style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w800, color: Color(0xFF146B43))), Text('total ${data['noun'] ?? 'records'}', style: const TextStyle(fontSize: 10))]),
        ]))),
        const SizedBox(height: 12),
        for (var i = 0; i < segments.length; i++) Padding(padding: const EdgeInsets.symmetric(vertical: 7), child: Row(children: [
          Icon(Icons.circle, size: 9, color: colors[i % colors.length]), const SizedBox(width: 9), Expanded(child: Text('${segments[i]['label']}')),
          Text('${segments[i]['value']} · ${total == 0 ? 0 : ((num.tryParse('${segments[i]['value']}') ?? 0) / total * 100).round()}%'),
        ])),
        if (total == 0) const Text('No activity yet. Your chart will fill as work is recorded.'),
      ])),
    ]);
  }
}
class _ActivityPainter extends CustomPainter {
  final List<Map> days;
  _ActivityPainter(this.days);
  @override
  void paint(Canvas canvas, Size size) {
    if (days.isEmpty) return;
    final values = days.map((d) => num.tryParse('${d['value']}')?.toDouble() ?? 0).toList();
    final peak = values.fold<double>(1, (a,b) => a > b ? a : b);
    final grid = Paint()..color = const Color(0xFFE3EDE3)..strokeWidth = 1;
    for (var i = 0; i < 3; i++) { final y = 10 + (size.height - 20) * i / 2; canvas.drawLine(Offset(4,y),Offset(size.width-4,y),grid); }
    final points = List.generate(values.length, (i) => Offset(5 + (size.width-10)*i/math.max(1, values.length-1), size.height-10-values[i]/peak*(size.height-25)));
    final path = Path()..moveTo(points.first.dx, points.first.dy);
    for (final point in points.skip(1)) { path.lineTo(point.dx,point.dy); }
    canvas.drawPath(path, Paint()..color=const Color(0xFF146B43)..style=PaintingStyle.stroke..strokeWidth=3);
    for (final point in points) { canvas.drawCircle(point,3,Paint()..color=const Color(0xFF146B43)); }
  }
  @override
  bool shouldRepaint(covariant _ActivityPainter oldDelegate) => oldDelegate.days != days;
}
class _StatusPainter extends CustomPainter {
  final List<Map> segments;
  final List<Color> colors;
  _StatusPainter(this.segments,this.colors);
  @override
  void paint(Canvas canvas, Size size) {
    final rect = Offset.zero & size;
    final ring = rect.deflate(12);
    canvas.drawArc(ring,0,math.pi*2,false,Paint()..color=const Color(0xFFEDF2EC)..style=PaintingStyle.stroke..strokeWidth=18);
    final total = segments.fold<double>(0, (sum,s) => sum+(num.tryParse('${s['value']}')?.toDouble() ?? 0));
    if (total <= 0) return;
    var start = -math.pi/2;
    for (var i=0;i<segments.length;i++) { final sweep=(num.tryParse('${segments[i]['value']}')?.toDouble() ?? 0)/total*math.pi*2; canvas.drawArc(ring,start,sweep,false,Paint()..color=colors[i%colors.length]..style=PaintingStyle.stroke..strokeWidth=18); start+=sweep; }
  }
  @override
  bool shouldRepaint(covariant _StatusPainter oldDelegate) => oldDelegate.segments != segments;
}
