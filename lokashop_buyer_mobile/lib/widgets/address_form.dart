import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';
import '../services/app_state.dart';
import '../theme.dart';
import 'common.dart';

/// Bottom-sheet form to add a new delivery address. Pops `true` on success.
class AddressForm extends StatefulWidget {
  const AddressForm({super.key});
  @override
  State<AddressForm> createState() => _AddressFormState();
}

class _AddressFormState extends State<AddressForm> {
  final _formKey = GlobalKey<FormState>();
  final _recipient = TextEditingController();
  final _phone = TextEditingController();
  final _street = TextEditingController();
  final _barangay = TextEditingController();
  final _city = TextEditingController();
  final _province = TextEditingController();
  final _postal = TextEditingController();
  bool _default = false;
  bool _saving = false;

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      await context.read<AppState>().api.post('/addresses', {
        'recipient': _recipient.text.trim(),
        'phone': _phone.text.trim(),
        'street': _street.text.trim(),
        'barangay': _barangay.text.trim(),
        'city': _city.text.trim(),
        'province': _province.text.trim(),
        'postal_code': _postal.text.trim().isEmpty ? null : _postal.text.trim(),
        'is_default': _default,
      });
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (mounted) AppSnack.show(context, e.message, error: true);
    } catch (_) {
      if (mounted) AppSnack.show(context, 'Could not save address.', error: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Container(
        decoration: const BoxDecoration(color: AppColors.bg, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
        padding: const EdgeInsets.fromLTRB(20, 14, 20, 24),
        child: SingleChildScrollView(
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: AppColors.line, borderRadius: BorderRadius.circular(4)))),
                const SizedBox(height: 16),
                const Text('New address', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                const SizedBox(height: 16),
                TextFormField(controller: _recipient, decoration: const InputDecoration(labelText: 'Recipient name'), validator: _req),
                const SizedBox(height: 10),
                TextFormField(controller: _phone, decoration: const InputDecoration(labelText: 'Phone'), validator: _req),
                const SizedBox(height: 10),
                TextFormField(controller: _street, decoration: const InputDecoration(labelText: 'Street / building'), validator: _req),
                const SizedBox(height: 10),
                TextFormField(controller: _barangay, decoration: const InputDecoration(labelText: 'Barangay'), validator: _req),
                const SizedBox(height: 10),
                Row(children: [
                  Expanded(child: TextFormField(controller: _city, decoration: const InputDecoration(labelText: 'City'), validator: _req)),
                  const SizedBox(width: 10),
                  Expanded(child: TextFormField(controller: _province, decoration: const InputDecoration(labelText: 'Province'), validator: _req)),
                ]),
                const SizedBox(height: 10),
                TextFormField(controller: _postal, decoration: const InputDecoration(labelText: 'Postal code (optional)')),
                const SizedBox(height: 6),
                CheckboxListTile(
                  value: _default,
                  onChanged: (v) => setState(() => _default = v ?? false),
                  title: const Text('Set as default address'),
                  contentPadding: EdgeInsets.zero,
                  controlAffinity: ListTileControlAffinity.leading,
                ),
                const SizedBox(height: 10),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: _saving ? null : _save,
                    child: _saving
                        ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.4))
                        : const Text('Save address'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  String? _req(String? v) => (v == null || v.trim().isEmpty) ? 'Required' : null;
}
