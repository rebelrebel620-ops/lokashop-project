import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import 'common.dart';
import 'place_fields.dart';

class AddressForm extends StatefulWidget {
  final Map? initial;
  const AddressForm({super.key, this.initial});
  @override
  State<AddressForm> createState() => _AddressFormState();
}

class _AddressFormState extends State<AddressForm> {
  final form = GlobalKey<FormState>();
  late final recipient = TextEditingController(
        text: widget.initial?['recipient'],
      ),
      phone = TextEditingController(text: widget.initial?['phone']),
      street = TextEditingController(text: widget.initial?['street']),
      postal = TextEditingController(text: widget.initial?['postal_code']);
  late Map<String, String> place = {
    for (final k in ['region', 'province', 'city', 'barangay'])
      k: '${widget.initial?[k] ?? ''}',
  };
  late bool defaultAddress = widget.initial?['is_default'] == 1;
  bool busy = false;
  @override
  void dispose() {
    for (final c in [recipient, phone, street, postal]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> save() async {
    if (!form.currentState!.validate()) return;
    setState(() => busy = true);
    try {
      await context.read<AppState>().api.post('/addresses', {
        if (widget.initial != null) 'id': widget.initial!['id'],
        'recipient': recipient.text.trim(),
        'phone': phone.text.trim(),
        'street': street.text.trim(),
        'postal_code': postal.text.trim(),
        'is_default': defaultAddress,
        ...place,
      });
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) AppSnack.show(context, '$e', error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => SafeArea(
    child: Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
      ),
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * .83,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: form,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  widget.initial == null
                      ? 'A new delivery spot'
                      : 'Edit delivery address',
                  style: const TextStyle(
                    fontSize: 23,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 20),
                TextFormField(
                  controller: recipient,
                  decoration: const InputDecoration(
                    labelText: 'Recipient name',
                  ),
                  validator: required,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: phone,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(
                    labelText: 'Contact number',
                  ),
                  validator: (v) => RegExp(r'^09\d{9}$').hasMatch(v ?? '')
                      ? null
                      : 'Use 09 followed by 9 digits',
                ),
                const SizedBox(height: 12),
                PlaceFields(
                  initial: widget.initial ?? {},
                  onChanged: (v) => place = v,
                ),
                TextFormField(
                  controller: street,
                  decoration: const InputDecoration(
                    labelText: 'Street / house number',
                  ),
                  validator: required,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: postal,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Four-digit postal code',
                  ),
                  validator: (v) => RegExp(r'^\d{4}$').hasMatch(v ?? '')
                      ? null
                      : 'Enter four digits',
                ),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: defaultAddress,
                  onChanged: (v) => setState(() => defaultAddress = v ?? false),
                  title: const Text('Use as my default address'),
                ),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: busy ? null : save,
                    child: Text(busy ? 'Saving…' : 'Save address'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
  String? required(String? s) =>
      s == null || s.trim().isEmpty ? 'This field is required' : null;
}
