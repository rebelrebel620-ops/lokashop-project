import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';
import '../services/app_state.dart';
import '../widgets/common.dart';
import '../widgets/place_fields.dart';
import 'verify_email_screen.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});
  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _form = GlobalKey<FormState>();
  final _fields = <String, TextEditingController>{
    for (final name in [
      'first_name',
      'last_name',
      'phone',
      'email',
      'password',
      'password_confirmation',
      'region',
      'province',
      'city',
      'barangay',
      'street',
      'postal_code',
    ])
      name: TextEditingController(),
  };
  final _dobText = TextEditingController();
  final _errors = <String, String>{};
  DateTime? _dob;
  bool _terms = false, _busy = false, _hidePassword = true, _hideConfirm = true;

  @override
  void dispose() {
    for (final controller in _fields.values) {
      controller.dispose();
    }
    _dobText.dispose();
    super.dispose();
  }

  Future<void> _pickBirthday() async {
    final now = DateTime.now();
    final latest = DateTime(now.year - 18, now.month, now.day);
    final chosen = await showDatePicker(
      context: context,
      initialDate: _dob ?? latest,
      firstDate: DateTime(1900),
      lastDate: latest,
    );
    if (chosen != null && mounted) {
      setState(() {
        _dob = chosen;
        _dobText.text =
            '${chosen.year}-${chosen.month.toString().padLeft(2, '0')}-${chosen.day.toString().padLeft(2, '0')}';
        _errors.remove('date_of_birth');
      });
    }
  }

  void _showTerms() {
    showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Terms and Privacy Policy'),
        content: const SingleChildScrollView(
          child: Text(
            'LokaShop is a student e-commerce project. Use fictional details and sample documents when testing.\n\n'
            'Accounts\nKeep your password and verification code private. Provide accurate account details. Buyers must be at least 18. Sellers require email verification and administrator approval before selling.\n\n'
            'Information used by this project\nThe system stores your contact details, address, account credentials, order information and, for sellers, submitted business documents. These support account verification, application review and delivery. Passwords are hashed. Administrators review seller documents.\n\n'
            'Project testing\nContact your project administrator to correct or remove test information. Do not upload real identity documents during classroom testing.',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }

  Future<void> _submit() async {
    if (_busy) return;
    setState(() => _errors.clear());
    if (!_form.currentState!.validate()) return;
    if (!_terms) {
      setState(
        () => _errors['terms'] = 'Please accept the Terms and Privacy Policy.',
      );
      return;
    }
    setState(() => _busy = true);
    try {
      final data = <String, dynamic>{
        for (final entry in _fields.entries)
          entry.key: entry.key.startsWith('password')
              ? entry.value.text
              : entry.value.text.trim(),
        'date_of_birth': _dobText.text,
        'terms': true,
      };
      final result = await context.read<AppState>().register(data);
      if (!mounted) return;
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => VerifyEmailScreen(
            token: result['verification_token'] as String,
            email: result['email'] as String,
            initialCooldown: 60,
          ),
        ),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        for (final entry in (e.errors ?? {}).entries) {
          final value = entry.value;
          _errors[entry.key] = value is List && value.isNotEmpty
              ? '${value.first}'
              : '$value';
        }
      });
      AppSnack.show(context, e.message, error: true);
    } catch (_) {
      if (mounted) {
        AppSnack.show(
          context,
          'Could not reach the server. Check your connection.',
          error: true,
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _field(
    String name,
    String label, {
    TextInputType? keyboard,
    String? helper,
  }) {
    final secret = name.startsWith('password');
    final confirmation = name == 'password_confirmation';
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: TextFormField(
        controller: _fields[name],
        enabled: !_busy,
        keyboardType: keyboard,
        obscureText: secret && (confirmation ? _hideConfirm : _hidePassword),
        decoration: InputDecoration(
          labelText: label,
          helperText: helper,
          errorText: _errors[name],
          suffixIcon: secret
              ? IconButton(
                  icon: Icon(
                    (confirmation ? _hideConfirm : _hidePassword)
                        ? Icons.visibility_off
                        : Icons.visibility,
                  ),
                  onPressed: () => setState(() {
                    if (confirmation) {
                      _hideConfirm = !_hideConfirm;
                    } else {
                      _hidePassword = !_hidePassword;
                    }
                  }),
                )
              : null,
        ),
        onChanged: (_) {
          if (_errors.containsKey(name)) setState(() => _errors.remove(name));
        },
        validator: (value) {
          final v = secret ? (value ?? '') : (value ?? '').trim();
          if (v.isEmpty) return 'Required';
          if ((name == 'first_name' || name == 'last_name') &&
              (v.length < 2 || v.length > 50)) {
            return 'Use 2–50 characters';
          }
          if (name == 'email' &&
              !RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(v)) {
            return 'Enter a valid email';
          }
          if (name == 'phone' &&
              !RegExp(r'^(09\d{9}|\+639\d{9})$').hasMatch(v)) {
            return 'Use 09XXXXXXXXX or +639XXXXXXXXX';
          }
          if (name == 'postal_code' && !RegExp(r'^\d{4}$').hasMatch(v)) {
            return 'Enter four digits';
          }
          if (confirmation && v != _fields['password']!.text) {
            return 'Passwords do not match';
          }
          if (name == 'password' &&
              (v.length < 8 ||
                  !RegExp('[A-Z]').hasMatch(v) ||
                  !RegExp('[a-z]').hasMatch(v) ||
                  !RegExp('[0-9]').hasMatch(v) ||
                  !RegExp(r'[^a-zA-Z0-9\s]').hasMatch(v))) {
            return 'Use 8+ characters with uppercase, lowercase, number and symbol';
          }
          return null;
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Create buyer account')),
    body: SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Form(
        key: _form,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Verify your email to start shopping. Buyers do not need admin approval.',
            ),
            const SizedBox(height: 20),
            _field('first_name', 'First name'),
            _field('last_name', 'Last name'),
            TextFormField(
              controller: _dobText,
              readOnly: true,
              onTap: _busy ? null : _pickBirthday,
              decoration: InputDecoration(
                labelText: 'Date of birth',
                helperText: 'You must be at least 18.',
                errorText: _errors['date_of_birth'],
                suffixIcon: const Icon(Icons.calendar_today),
              ),
              validator: (v) =>
                  _dob == null ? 'Select your date of birth' : null,
            ),
            const SizedBox(height: 14),
            _field('phone', 'Mobile number', keyboard: TextInputType.phone),
            _field('email', 'Email', keyboard: TextInputType.emailAddress),
            _field('password', 'Password'),
            _field('password_confirmation', 'Confirm password'),
            const Text(
              'Delivery address',
              style: TextStyle(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 14),
            PlaceFields(
              onChanged: (values) {
                for (final entry in values.entries) {
                  _fields[entry.key]?.text = entry.value;
                }
              },
            ),
            _field('street', 'Street / house number'),
            _field(
              'postal_code',
              'Postal code',
              keyboard: TextInputType.number,
            ),
            TextButton(
              onPressed: _showTerms,
              child: const Text('Read Terms and Privacy Policy'),
            ),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              value: _terms,
              title: const Text('I accept the Terms and Privacy Policy'),
              onChanged: _busy
                  ? null
                  : (value) => setState(() {
                      _terms = value ?? false;
                      _errors.remove('terms');
                    }),
            ),
            if (_errors['terms'] != null)
              Text(
                _errors['terms']!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: _busy ? null : _submit,
              child: Text(_busy ? 'Creating account…' : 'Create account'),
            ),
          ],
        ),
      ),
    ),
  );
}
