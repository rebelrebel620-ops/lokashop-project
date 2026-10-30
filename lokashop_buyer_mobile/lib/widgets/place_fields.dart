import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

class PlaceFields extends StatefulWidget {
  final Map initial;
  final Map<String, String> errors;
  final bool enabled;
  final ValueChanged<Map<String, String>> onChanged;
  const PlaceFields({super.key, this.initial = const {}, this.errors = const {},
    this.enabled = true, required this.onChanged});
  @override
  State<PlaceFields> createState() => _PlaceFieldsState();
}

class _PlaceFieldsState extends State<PlaceFields> {
  static Future<Map>? cached;
  Map? tree;
  String? error, region, province, city, barangay;
  List get regions => tree?['regions'] ?? [];
  List get provinces => (tree?['provinces'] as List? ?? []).where((p) => p['regionCode'] == region).toList();
  List get cities => (tree?['cities-municipalities'] as List? ?? []).where((c) => c['provinceCode'] == province).toList();
  List get barangays => (tree?['barangays'] as List? ?? []).where((b) => b['cityCode'] == city || b['municipalityCode'] == city).toList();
  String normalized(String s) => s.toLowerCase().replaceAll(RegExp(r'city of | city|\W'), '');
  @override
  void initState() { super.initState(); load(); }

  Future<void> load() async {
    setState(() => error = null);
    try {
      cached ??= rootBundle.loadString('assets/places.json').then((s) => jsonDecode(s) as Map);
      final data = await cached!;
      if (!mounted) return;
      setState(() {
        tree = data;
        final matches = (data['provinces'] as List).where((p) => normalized('${p['name']}') == normalized('${widget.initial['province'] ?? ''}'));
        if (matches.isNotEmpty) {
          province = '${matches.first['code']}';
          region = '${matches.first['regionCode']}';
          final found = cities.where((c) => normalized('${c['name']}') == normalized('${widget.initial['city'] ?? ''}'));
          if (found.isNotEmpty) {
            city = '${found.first['code']}';
            final bs = barangays.where((b) => '${b['name']}'.toLowerCase().trim() == '${widget.initial['barangay'] ?? ''}'.toLowerCase().trim());
            if (bs.isNotEmpty) barangay = '${bs.first['code']}';
          }
        }
      });
      // Saved addresses may omit region. Infer it from the saved province.
      emit();
    } catch (_) {
      cached = null;
      if (mounted) setState(() => error = 'Address choices could not load. Please retry.');
    }
  }

  void emit() {
    String name(List values, String? code) {
      for (final v in values) { if ('${v['code']}' == code) return '${v['name']}'; }
      return '';
    }
    widget.onChanged({'region': name(regions, region), 'province': name(provinces, province),
      'city': name(cities, city), 'barangay': name(barangays, barangay)});
  }

  Widget field(String key, String label, List options, String? value, ValueChanged<String?> changed) => Padding(
    padding: const EdgeInsets.only(bottom: 14),
    child: DropdownButtonFormField<String>(
      key: ValueKey('$key:$value:${options.length}'),
      initialValue: options.any((p) => '${p['code']}' == value) ? value : null,
      isExpanded: true,
      decoration: InputDecoration(labelText: label, errorText: widget.errors[key]),
      items: options.map((p) => DropdownMenuItem<String>(value: '${p['code']}', child: Text('${p['name']}', overflow: TextOverflow.ellipsis))).toList(),
      onChanged: !widget.enabled || options.isEmpty ? null : changed,
      validator: (v) => v == null ? 'Choose $label' : null,
    ),
  );

  @override
  Widget build(BuildContext context) => tree == null
    ? FormField<bool>(validator: (_) => error ?? 'Wait for address choices to load.', builder: (state) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 16),
        child: Column(children: [
          if (error == null) ...[const LinearProgressIndicator(), const SizedBox(height: 10), const Text('Loading address choices…')]
          else ...[Text(error!), TextButton.icon(onPressed: widget.enabled ? load : null, icon: const Icon(Icons.refresh), label: const Text('Retry'))],
          if (state.hasError) Text(state.errorText!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
        ]),
      ))
    : Column(children: [
        field('region', 'Region', regions, region, (v) { setState(() { region = v; province = null; city = null; barangay = null; }); emit(); }),
        field('province', 'Province', provinces, province, (v) { setState(() { province = v; city = null; barangay = null; }); emit(); }),
        field('city', 'City / municipality', cities, city, (v) { setState(() { city = v; barangay = null; }); emit(); }),
        field('barangay', 'Barangay', barangays, barangay, (v) { setState(() => barangay = v); emit(); }),
      ]);
}
