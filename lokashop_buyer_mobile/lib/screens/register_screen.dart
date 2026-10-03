import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';
import '../services/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';
import 'login_screen.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});
  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  File? _idDocument;
  bool _loading = false;

  Future<void> _pickDocument() async {
    final picked = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 85);
    if (picked != null) setState(() => _idDocument = File(picked.path));
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_idDocument == null) {
      AppSnack.show(context, 'Please attach a photo of a valid ID.', error: true);
      return;
    }
    setState(() => _loading = true);
    try {
      final message = await context.read<AppState>().register(
            name: _name.text.trim(),
            phone: _phone.text.trim(),
            email: _email.text.trim(),
            password: _password.text,
            passwordConfirmation: _confirm.text,
            idDocument: _idDocument!,
          );
      if (!mounted) return;
      showDialog(
        context: context,
        builder: (_) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Text('Registration submitted'),
          content: Text(message),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.of(context).pop();
                Navigator.of(context).pushAndRemoveUntil(MaterialPageRoute(builder: (_) => const LoginScreen()), (r) => false);
              },
              child: const Text('Go to login'),
            ),
          ],
        ),
      );
    } on ApiException catch (e) {
      if (mounted) AppSnack.show(context, e.message, error: true);
    } catch (e) {
      if (mounted) AppSnack.show(context, 'Could not reach the server. Check your connection.', error: true);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Create account')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Image(image: AssetImage('assets/lokashop_logo.png'), width: 76, height: 76),
                const SizedBox(height: 16),
                const Text('Join as a buyer', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
                const SizedBox(height: 4),
                const Text('Your account needs a quick admin approval before you can log in.',
                    style: TextStyle(color: AppColors.muted)),
                const SizedBox(height: 24),
                TextFormField(
                  controller: _name,
                  decoration: const InputDecoration(labelText: 'Full name', prefixIcon: Icon(Icons.person_outline_rounded)),
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _phone,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(labelText: 'Phone number', prefixIcon: Icon(Icons.call_outlined)),
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(labelText: 'Email', prefixIcon: Icon(Icons.mail_outline_rounded)),
                  validator: (v) => (v == null || !v.contains('@')) ? 'Enter a valid email' : null,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _password,
                  obscureText: true,
                  decoration: const InputDecoration(labelText: 'Password', prefixIcon: Icon(Icons.lock_outline_rounded)),
                  validator: (v) => (v == null || v.length < 8) ? 'At least 8 characters' : null,
                ),
                const SizedBox(height: 14),
                TextFormField(
                  controller: _confirm,
                  obscureText: true,
                  decoration: const InputDecoration(labelText: 'Confirm password', prefixIcon: Icon(Icons.lock_outline_rounded)),
                  validator: (v) => (v != _password.text) ? 'Passwords do not match' : null,
                ),
                const SizedBox(height: 18),
                const Text('Valid ID', style: TextStyle(fontWeight: FontWeight.w700)),
                const SizedBox(height: 8),
                InkWell(
                  onTap: _pickDocument,
                  borderRadius: BorderRadius.circular(14),
                  child: Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: AppColors.surface,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: AppColors.line),
                    ),
                    child: _idDocument == null
                        ? const Row(
                            children: [
                              Icon(Icons.upload_file_rounded, color: AppColors.primary),
                              SizedBox(width: 10),
                              Text('Upload a photo of your ID', style: TextStyle(color: AppColors.muted)),
                            ],
                          )
                        : Row(
                            children: [
                              ClipRRect(
                                borderRadius: BorderRadius.circular(10),
                                child: Image.file(_idDocument!, width: 44, height: 44, fit: BoxFit.cover),
                              ),
                              const SizedBox(width: 12),
                              const Expanded(child: Text('ID selected — tap to change')),
                              const Icon(Icons.check_circle_rounded, color: AppColors.success),
                            ],
                          ),
                  ),
                ),
                const SizedBox(height: 26),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: _loading ? null : _submit,
                    child: _loading
                        ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.4))
                        : const Text('Submit registration'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
