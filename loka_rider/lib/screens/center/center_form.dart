import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:file_picker/file_picker.dart';
import '../../session.dart';
import '../../widgets/place_fields.dart';
import '../features.dart' show requestKey;
import 'center_contracts.dart';

class CenterForm extends StatefulWidget {
  final Session session;
  final Map action;
  const CenterForm({super.key, required this.session, required this.action});
  @override
  State<CenterForm> createState() => _CenterFormState();
}

class _CenterFormState extends State<CenterForm> {
  final form = GlobalKey<FormState>();
  final controllers = <String, TextEditingController>{};
  final values = <String, dynamic>{};
  final files = <String, XFile>{};
  final key = requestKey();
  bool busy = false;
  String? error;
  List get fields => widget.action['fields'] as List? ?? [];
  @override
  void initState() {
    super.initState();
    for (final f in fields) {
      final name = '${f['name']}';
      if (f['type'] == 'multi') {
        values[name] = <String>{};
      } else if (f['type'] == 'place') {
        values[name] = Map<String, String>.from(f['initial'] ?? {});
      } else if (f['type'] == 'select') {
        values[name] = f['initial']?.toString();
      } else {
        controllers[name] = TextEditingController(
          text: f['initial']?.toString() ?? '',
        );
      }
    }
  }

  @override
  void dispose() {
    for (final c in controllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> chooseFile(Map f) async {
    final name = '${f['name']}';
    if (f['type'] == 'document') {
      final r = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['jpg', 'jpeg', 'png', 'pdf'],
      );
      if (r?.files.single.path != null && mounted) {
        setState(() => files[name] = XFile(r!.files.single.path!));
      }
    } else {
      final source = await showModalBottomSheet<ImageSource>(
        context: context,
        builder: (c) => SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                leading: const Icon(Icons.camera_alt_outlined),
                title: const Text('Take photo'),
                onTap: () => Navigator.pop(c, ImageSource.camera),
              ),
              ListTile(
                leading: const Icon(Icons.photo_library_outlined),
                title: const Text('Choose photo'),
                onTap: () => Navigator.pop(c, ImageSource.gallery),
              ),
            ],
          ),
        ),
      );
      if (source == null) {
        return;
      }
      final image = await ImagePicker().pickImage(
        source: source,
        maxWidth: 1800,
        imageQuality: 85,
      );
      if (image != null && mounted) {
        setState(() => files[name] = image);
      }
    }
  }

  Widget field(Map f) {
    final name = '${f['name']}', type = '${f['type']}', label = '${f['label']}';
    final required = f['required'] != false;
    if (type == 'place') {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label),
            PlaceFields(
              initial: Map.from(f['initial'] ?? {}),
              enabled: !busy,
              onChanged: (v) => values[name] = v,
            ),
          ],
        ),
      );
    }
    if (type == 'select') {
      var options = f['options'] as List? ?? [];
      if (name == 'vehicle' && values['courier_type'] != null) {
        options = options
            .where(
              (o) => values['courier_type'] == 'driver'
                  ? '${o['value']}'.contains('truck')
                  : ['motorcycle', 'tricycle'].contains(o['value']),
            )
            .toList();
      }
      return DropdownButtonFormField<String>(
        key: ValueKey('$name:${values['courier_type'] ?? ''}'),
        initialValue: values[name] as String?,
        isExpanded: true,
        decoration: InputDecoration(
          labelText: label,
          helperText: options.isEmpty
              ? 'No eligible choices are available.'
              : null,
        ),
        items: [
          for (final o in options)
            DropdownMenuItem(
              value: '${o['value']}',
              child: Text(
                '${o['label']}',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            ),
        ],
        onChanged: busy
            ? null
            : (v) => setState(() {
                values[name] = v;
                if (name == 'courier_type') {
                  values['vehicle'] = null;
                }
              }),
        validator: (v) =>
            required && (v ?? '').isEmpty ? 'Choose $label' : null,
      );
    }
    if (type == 'multi') {
      return FormField<Set<String>>(
        initialValue: values[name] as Set<String>,
        validator: (v) => required && (v == null || v.isEmpty)
            ? 'Select at least one parcel'
            : null,
        builder: (state) => Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Text(
                label,
                style: const TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
            for (final o in f['options'] as List? ?? [])
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                title: Text('${o['label']}'),
                value: (values[name] as Set<String>).contains('${o['value']}'),
                onChanged: busy
                    ? null
                    : (checked) {
                        setState(() {
                          final selected = values[name] as Set<String>;
                          if (checked == true) {
                            selected.add('${o['value']}');
                          } else {
                            selected.remove('${o['value']}');
                          }
                          state.didChange(selected);
                        });
                      },
              ),
            if (state.hasError)
              Text(
                state.errorText!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
          ],
        ),
      );
    }
    if (type == 'file' || type == 'document') {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label),
            OutlinedButton.icon(
              onPressed: busy ? null : () => chooseFile(f),
              icon: const Icon(Icons.attach_file),
              label: Text(files[name]?.name ?? 'Choose file'),
            ),
            if (files[name] != null)
              TextButton(
                onPressed: busy
                    ? null
                    : () => setState(() => files.remove(name)),
                child: const Text('Remove attachment'),
              ),
          ],
        ),
      );
    }
    return TextFormField(
      controller: controllers[name],
      enabled: !busy,
      readOnly: f['readonly'] == true || type == 'date',
      maxLines: f['multiline'] == true ? 4 : 1,
      maxLength: f['max_length'] as int?,
      keyboardType: ['money', 'number'].contains(type)
          ? const TextInputType.numberWithOptions(decimal: true)
          : name == 'email'
          ? TextInputType.emailAddress
          : name == 'phone'
          ? TextInputType.phone
          : TextInputType.text,
      decoration: InputDecoration(labelText: label),
      onTap: type != 'date'
          ? null
          : () async {
              final date = await showDatePicker(
                context: context,
                initialDate:
                    DateTime.tryParse(controllers[name]!.text) ??
                    DateTime.now(),
                firstDate: DateTime(1900),
                lastDate: DateTime(2100),
              );
              if (date != null) {
                controllers[name]!.text = date.toIso8601String().substring(
                  0,
                  10,
                );
              }
            },
      validator: (v) {
        final s = v?.trim() ?? '';
        if (required && s.isEmpty) {
          return 'Enter $label';
        }
        if (s.isNotEmpty &&
            ['number', 'money'].contains(type) &&
            double.tryParse(s) == null) {
          return 'Enter a valid number';
        }
        if (s.isNotEmpty && s.length < (f['min_length'] as int? ?? 0)) {
          return 'Use at least ${f['min_length']} characters';
        }
        return null;
      },
    );
  }

  Future<void> submit() async {
    if (busy || !(form.currentState?.validate() ?? false)) {
      return;
    }
    final payload = <String, dynamic>{
      ...Map<String, dynamic>.from(widget.action['fixed'] ?? {}),
      '_request_key': key,
    };
    for (final f in fields) {
      final name = '${f['name']}', type = '${f['type']}';
      if (type == 'file' || type == 'document') {
        if (f['required'] != false && !files.containsKey(name)) {
          setState(() => error = 'Attach ${f['label']}.');
          return;
        }
      } else if (type == 'place') {
        final location = Map<String, String>.from(values[name] ?? {});
        if ([
          'province',
          'city',
          'barangay',
        ].any((k) => (location[k] ?? '').isEmpty)) {
          setState(
            () => error = 'Choose the province, municipality and barangay.',
          );
          return;
        }
        payload.addAll(location);
      } else if (type == 'multi') {
        payload[name] = (values[name] as Set<String>).map(int.parse).toList();
      } else if (type == 'select') {
        payload[name] = values[name];
      } else {
        payload[name] = controllers[name]!.text.trim();
      }
    }
    if (payload['status'] == 'DAMAGED' && !files.containsKey('proof')) {
      setState(() => error = 'Attach a photo of the damaged parcel.');
      return;
    }
    final yes = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text('${widget.action['label']}?'),
        content: const Text(
          'Confirm these details before saving. The same sorting-center rules used on the website apply.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(c, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(c, true),
            child: const Text('Confirm'),
          ),
        ],
      ),
    );
    if (yes != true || !mounted || busy) {
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final path =
          '/api/center/action/${widget.action['key']}/${widget.action['id'] ?? 0}';
      final result = files.isEmpty
          ? await widget.session.api.post(path, payload)
          : await widget.session.api.upload(
              path,
              centerMultipart(payload),
              files,
            );
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${result['message'] ?? 'Saved.'}')),
      );
      Navigator.pop(context, true);
    } catch (e) {
      if (mounted) {
        setState(() => error = '$e');
      }
    } finally {
      if (mounted) {
        setState(() => busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text('${widget.action['label']}')),
    body: Form(
      key: form,
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          if (fields.isEmpty)
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text('Continue to confirm this action.'),
            ),
          for (final f in fields)
            Padding(
              padding: const EdgeInsets.only(bottom: 14),
              child: field(Map.from(f)),
            ),
          if (error != null)
            Padding(
              padding: const EdgeInsets.all(12),
              child: Text(
                error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          FilledButton(
            onPressed: busy ? null : submit,
            child: Text(busy ? 'Saving…' : 'Continue'),
          ),
        ],
      ),
    ),
  );
}
