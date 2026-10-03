import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';
import '../services/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';

class ComplaintFormScreen extends StatefulWidget {
  final int orderId;
  const ComplaintFormScreen({super.key, required this.orderId});
  @override
  State<ComplaintFormScreen> createState() => _ComplaintFormScreenState();
}

class _ComplaintFormScreenState extends State<ComplaintFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _description = TextEditingController();
  bool _sending = false;

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _sending = true);
    try {
      await context.read<AppState>().api.post('/complaints', {
        'order_id': widget.orderId,
        'description': _description.text.trim(),
      });
      if (!mounted) return;
      AppSnack.show(context, 'Complaint sent. Our team will follow up.');
      Navigator.of(context).pop();
    } on ApiException catch (e) {
      if (mounted) AppSnack.show(context, e.message, error: true);
    } catch (_) {
      if (mounted) AppSnack.show(context, 'Could not send complaint.', error: true);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Report order #${widget.orderId}')),
      body: Padding(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('What went wrong?', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
              const SizedBox(height: 6),
              const Text('Describe the issue in detail so our support team can help quickly.', style: TextStyle(color: AppColors.muted)),
              const SizedBox(height: 16),
              TextFormField(
                controller: _description,
                maxLines: 8,
                decoration: const InputDecoration(hintText: 'e.g. Item arrived damaged, missing items, wrong product…', alignLabelWithHint: true),
                validator: (v) => (v == null || v.trim().length < 5) ? 'Please describe the issue (min 5 characters).' : null,
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _sending ? null : _submit,
                  child: _sending
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.4))
                      : const Text('Submit complaint'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
