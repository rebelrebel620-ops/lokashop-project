import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import '../../session.dart';
import '../../models.dart';
import '../../widgets/auto_refresh.dart';
import '../../widgets/common.dart';
import 'center_contracts.dart';
import 'center_form.dart';

class CenterPage extends StatefulWidget {
  final Session session;
  final String module;
  final Map<String, String> initialQuery;
  const CenterPage({
    super.key,
    required this.session,
    required this.module,
    this.initialQuery = const {},
  });
  @override
  State<CenterPage> createState() => _CenterPageState();
}

class _CenterPageState extends State<CenterPage> with AutoRefresh<CenterPage> {
  Map<String, dynamic>? data;
  late Map<String, String> query;
  final search = TextEditingController();
  bool loading = false;
  String? error;
  @override
  bool get autoRefreshEnabled => !loading;
  @override
  Future<void> refreshAutomatically() => load(quiet: true);
  @override
  void initState() {
    super.initState();
    query = Map.from(widget.initialQuery);
    load();
  }

  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  Future<void> load({bool quiet = false}) async {
    if (loading) {
      return;
    }
    setState(() => loading = true);
    final snapshot = Map<String, String>.from(query);
    try {
      final r = await widget.session.api.get(
        centerPath(widget.module),
        snapshot,
      );
      if (mounted &&
          snapshot.length == query.length &&
          snapshot.entries.every((e) => query[e.key] == e.value)) {
        setState(() {
          data = r;
          error = null;
        });
      }
    } catch (e) {
      if (mounted && !quiet) {
        setState(() => error = '$e');
      }
    } finally {
      loading = false;
      if (mounted) {
        setState(() {});
        if (snapshot.length != query.length ||
            snapshot.entries.any((e) => query[e.key] != e.value)) {
          load();
        }
      }
    }
  }

  Future<void> download(String path) async {
    try {
      final r = await widget.session.api.download(path, query);
      await FilePicker.platform.saveFile(fileName: r.filename, bytes: r.bytes);
    } catch (e) {
      if (mounted) {
        toast(context, e);
      }
    }
  }

  Future<void> action(Map a) async {
    if (a['download'] != null) {
      await download('${a['download']}');
      return;
    }
    if (a['module'] != null) {
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => CenterPage(
            session: widget.session,
            module: '${a['module']}',
            initialQuery: Map<String, String>.from(a['query'] ?? {}),
          ),
        ),
      );
    } else {
      await Navigator.push<bool>(
        context,
        MaterialPageRoute(
          builder: (_) => CenterForm(session: widget.session, action: a),
        ),
      );
    }
    if (mounted) {
      load();
    }
  }

  Widget card(Map row) => Card(
    margin: const EdgeInsets.symmetric(vertical: 8),
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '${row['title']}',
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
          ),
          if ('${row['status'] ?? ''}'.isNotEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Chip(label: Text(prettyStatus('${row['status']}'))),
            ),
          for (final f in row['facts'] as List? ?? [])
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 3),
              child: Text('${f['label']}: ${f['value']}'),
            ),
          if ((row['actions'] as List? ?? []).isNotEmpty)
            Wrap(
              spacing: 8,
              children: [
                for (final a in row['actions'])
                  OutlinedButton(
                    onPressed: () => action(Map.from(a)),
                    child: Text('${a['label']}'),
                  ),
              ],
            ),
        ],
      ),
    ),
  );
  Widget pagination(Map block) => Row(
    mainAxisAlignment: MainAxisAlignment.spaceBetween,
    children: [
      TextButton(
        onPressed: loading || (block['current_page'] ?? 1) <= 1
            ? null
            : () {
                query['${block['page_parameter'] ?? 'page'}'] =
                    '${block['current_page'] - 1}';
                load();
              },
        child: const Text('Previous'),
      ),
      Text('${block['current_page'] ?? 1} / ${block['last_page'] ?? 1}'),
      TextButton(
        onPressed:
            loading || (block['current_page'] ?? 1) >= (block['last_page'] ?? 1)
            ? null
            : () {
                query['${block['page_parameter'] ?? 'page'}'] =
                    '${block['current_page'] + 1}';
                load();
              },
        child: const Text('Next'),
      ),
    ],
  );
  Future<void> filters() async {
    final from = TextEditingController(text: query['from']),
        to = TextEditingController(text: query['to']),
        status = TextEditingController(text: query['status']);
    String entryType = query['type'] ?? '';
    final result = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Report filters'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              for (final item in [(from, 'From date'), (to, 'To date')])
                TextField(
                  controller: item.$1,
                  readOnly: true,
                  decoration: InputDecoration(
                    labelText: item.$2,
                    suffixIcon: IconButton(
                      onPressed: () => item.$1.clear(),
                      icon: const Icon(Icons.clear),
                    ),
                  ),
                  onTap: () async {
                    final d = await showDatePicker(
                      context: c,
                      initialDate:
                          DateTime.tryParse(item.$1.text) ?? DateTime.now(),
                      firstDate: DateTime(2000),
                      lastDate: DateTime(2100),
                    );
                    if (d != null) {
                      item.$1.text = d.toIso8601String().substring(0, 10);
                    }
                  },
                ),
              if (widget.module == 'finance')
                DropdownButtonFormField<String>(
                  initialValue: entryType,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Entry type'),
                  items: [
                    const DropdownMenuItem(
                      value: '',
                      child: Text('All entry types'),
                    ),
                    for (final type in [
                      'shipping_income',
                      'courier_earnings',
                      'courier_allocation',
                      'center_settlement',
                      'cod_collection',
                      'cod_remittance',
                    ])
                      DropdownMenuItem(
                        value: type,
                        child: Text(prettyStatus(type)),
                      ),
                  ],
                  onChanged: (v) => entryType = v ?? '',
                ),
              if (widget.module == 'finance')
                TextField(
                  controller: status,
                  decoration: const InputDecoration(
                    labelText: 'Status (optional)',
                  ),
                ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(c, true),
            child: const Text('Apply'),
          ),
        ],
      ),
    );
    if (result == true && mounted) {
      for (final e in {
        'from': from.text,
        'to': to.text,
        'status': status.text,
        if (widget.module == 'finance') 'type': entryType,
      }.entries) {
        if (e.value.isEmpty) {
          query.remove(e.key);
        } else {
          query[e.key] = e.value;
        }
      }
      query.remove('page');
      await load();
    }
    from.dispose();
    to.dispose();
    status.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final tabs = Map<String, dynamic>.from(data?['tabs'] ?? {});
    final tabParam = '${data?['tab_parameter'] ?? 'tab'}';
    final selected = query[tabParam] ?? (tabs.isEmpty ? '' : tabs.keys.first);
    final canSearch = [
      'couriers',
      'tracking',
      'reports',
      'finance',
    ].contains(widget.module);
    return Scaffold(
      appBar: AppBar(
        title: Text('${data?['title'] ?? 'Sorting center'}'),
        actions: [
          if (['overview', 'reports', 'finance'].contains(widget.module))
            IconButton(
              onPressed: filters,
              icon: const Icon(Icons.filter_alt_outlined),
              tooltip: 'Filter report',
            ),
          if (data?['export'] != null)
            IconButton(
              onPressed: () => download('${data!['export']}'),
              icon: const Icon(Icons.picture_as_pdf_outlined),
              tooltip: 'Save PDF',
            ),
          IconButton(
            onPressed: loading ? null : () => load(),
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh',
          ),
        ],
      ),
      body: data == null && loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: () => load(),
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                children: [
                  if (error != null)
                    Padding(
                      padding: const EdgeInsets.all(12),
                      child: Text(
                        error!,
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                    ),
                  if (data?['notice'] != null)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      child: Text('${data!['notice']}'),
                    ),
                  if (canSearch)
                    TextField(
                      controller: search,
                      decoration: InputDecoration(
                        labelText: widget.module == 'couriers'
                            ? 'Search name or email'
                            : 'Search records',
                        suffixIcon: IconButton(
                          icon: const Icon(Icons.search),
                          onPressed: () {
                            query['q'] = search.text.trim();
                            query.remove('page');
                            load();
                          },
                        ),
                      ),
                      onSubmitted: (v) {
                        query['q'] = v.trim();
                        query.remove('page');
                        load();
                      },
                    ),
                  if (tabs.isNotEmpty)
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: Row(
                        children: [
                          for (final e in tabs.entries)
                            Padding(
                              padding: const EdgeInsets.only(right: 8),
                              child: ChoiceChip(
                                label: Text('${e.value}'),
                                selected: selected == e.key,
                                onSelected: loading
                                    ? null
                                    : (_) {
                                        query[tabParam] = e.key;
                                        query.remove('page');
                                        query.remove('trips_page');
                                        query.remove('legacy_page');
                                        load();
                                      },
                              ),
                            ),
                        ],
                      ),
                    ),
                  if (data?['metrics'] != null)
                    Wrap(
                      spacing: 10,
                      runSpacing: 10,
                      children: [
                        for (final m in data!['metrics'])
                          SizedBox(
                            width: 155,
                            child: Card(
                              child: Padding(
                                padding: const EdgeInsets.all(14),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      '${m['value']}',
                                      style: const TextStyle(
                                        fontSize: 24,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                    Text('${m['label']}'),
                                  ],
                                ),
                              ),
                            ),
                          ),
                      ],
                    ),
                  for (final a in data?['actions'] as List? ?? [])
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      child: FilledButton(
                        onPressed: () => action(Map.from(a)),
                        child: Text('${a['label']}'),
                      ),
                    ),
                  if (data != null && (data!['records'] as List? ?? []).isEmpty)
                    const Padding(
                      padding: EdgeInsets.all(32),
                      child: Text('No records in this section.'),
                    ),
                  for (final row in data?['records'] as List? ?? [])
                    card(Map.from(row)),
                  if (data != null) pagination(data!),
                  if (data?['secondary'] != null) ...[
                    const Text(
                      'Individual transfers',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    for (final row in data!['secondary']['records'])
                      card(Map.from(row)),
                    pagination(Map.from(data!['secondary'])),
                  ],
                ],
              ),
            ),
    );
  }
}
