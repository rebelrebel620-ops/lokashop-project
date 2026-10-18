import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

class PlaceFields extends StatefulWidget {
  final Map initial;
  final ValueChanged<Map<String, String>> onChanged;
  const PlaceFields({
    super.key,
    this.initial = const {},
    required this.onChanged,
  });
  @override
  State<PlaceFields> createState() => _PlaceFieldsState();
}

class _PlaceFieldsState extends State<PlaceFields> {
  static Future<Map>? cached;
  Map? tree;
  String? region, province, city, barangay;
  List get regions => tree?['regions'] ?? [];
  List get provinces => (tree?['provinces'] as List? ?? [])
      .where((p) => p['regionCode'] == region)
      .toList();
  List get cities => (tree?['cities-municipalities'] as List? ?? [])
      .where((c) => c['provinceCode'] == province)
      .toList();
  List get barangays => (tree?['barangays'] as List? ?? [])
      .where((b) => b['cityCode'] == city || b['municipalityCode'] == city)
      .toList();
  String normalized(String s) =>
      s.toLowerCase().replaceAll(RegExp(r'city of | city|\W'), '');
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    cached ??= rootBundle
        .loadString('assets/places.json')
        .then((s) => jsonDecode(s) as Map);
    final data = await cached!;
    if (!mounted) return;
    setState(() {
      tree = data;
      final matches = (data['provinces'] as List).where(
        (p) =>
            normalized('${p['name']}') ==
            normalized('${widget.initial['province'] ?? ''}'),
      );
      if (matches.isNotEmpty) {
        province = matches.first['code'];
        region = matches.first['regionCode'];
        final found = cities.where(
          (c) =>
              normalized('${c['name']}') ==
              normalized('${widget.initial['city'] ?? ''}'),
        );
        if (found.isNotEmpty) {
          city = found.first['code'];
          final bs = barangays.where(
            (b) =>
                '${b['name']}'.toLowerCase() ==
                '${widget.initial['barangay'] ?? ''}'.toLowerCase(),
          );
          if (bs.isNotEmpty) barangay = bs.first['code'];
        }
      }
    });
  }

  void emit() {
    String name(List values, String? code) {
      for (final v in values) {
        if (v['code'] == code) return '${v['name']}';
      }
      return '';
    }

    widget.onChanged({
      'region': name(regions, region),
      'province': name(provinces, province),
      'city': name(cities, city),
      'barangay': name(barangays, barangay),
    });
  }

  Widget field(
    String label,
    List options,
    String? value,
    ValueChanged<String?> changed,
  ) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: DropdownButtonFormField<String>(
      key: ValueKey('$label:$value:${options.length}'),
      initialValue: options.any((p) => p['code'] == value) ? value : null,
      isExpanded: true,
      decoration: InputDecoration(labelText: label),
      items: options
          .map(
            (p) => DropdownMenuItem<String>(
              value: '${p['code']}',
              child: Text('${p['name']}', overflow: TextOverflow.ellipsis),
            ),
          )
          .toList(),
      onChanged: options.isEmpty ? null : changed,
      validator: (v) => v == null ? 'Choose $label' : null,
    ),
  );
  @override
  Widget build(BuildContext context) => tree == null
      ? const Padding(
          padding: EdgeInsets.all(16),
          child: LinearProgressIndicator(),
        )
      : Column(
          children: [
            field('Region', regions, region, (v) {
              setState(() {
                region = v;
                province = null;
                city = null;
                barangay = null;
              });
              emit();
            }),
            field('Province', provinces, province, (v) {
              setState(() {
                province = v;
                city = null;
                barangay = null;
              });
              emit();
            }),
            field('City / municipality', cities, city, (v) {
              setState(() {
                city = v;
                barangay = null;
              });
              emit();
            }),
            field('Barangay', barangays, barangay, (v) {
              setState(() => barangay = v);
              emit();
            }),
          ],
        );
}
