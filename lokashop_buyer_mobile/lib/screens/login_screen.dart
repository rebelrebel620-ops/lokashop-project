import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';
import '../services/app_state.dart';
import '../theme.dart';
import '../widgets/auth_layout.dart';
import 'home_shell.dart';
import 'register_screen.dart';
import 'verify_email_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController(), _password = TextEditingController();
  bool _loading = false, _obscure = true;
  String? _error;
  @override
  void dispose() { _email.dispose(); _password.dispose(); super.dispose(); }

  Future<void> _submit() async {
    if (_loading || !_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    setState(() { _loading = true; _error = null; });
    try {
      await context.read<AppState>().login(_email.text.trim(), _password.text);
      TextInput.finishAutofillContext();
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(MaterialPageRoute(builder: (_) => const HomeShell()), (r) => false);
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.data?['verification_required'] == true && e.data?['verification_token'] is String) {
        Navigator.of(context).push(MaterialPageRoute(builder: (_) => VerifyEmailScreen(
          token: e.data!['verification_token'] as String,
          email: '${e.data?['email'] ?? _email.text.trim()}',
          initialCooldown: int.tryParse('${e.data?['retry_after'] ?? 0}') ?? 0,
        )));
      } else { setState(() => _error = e.message); }
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not sign in. Check your connection and try again.');
    } finally { if (mounted) setState(() => _loading = false); }
  }

  @override
  Widget build(BuildContext context) => AuthLayout(
    title: 'Welcome back,\nlocal shopper.',
    subtitle: 'Your saved finds, favourite stores and delivery updates are waiting.',
    child: AutofillGroup(child: Form(key: _formKey, child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text('Sign in', style: TextStyle(fontSize: 23, fontWeight: FontWeight.w800)),
        const SizedBox(height: 6),
        const Text('Use your LokaShop buyer account.', style: TextStyle(color: AppColors.muted)),
        const SizedBox(height: 24),
        TextFormField(controller: _email, enabled: !_loading,
          autofillHints: const [AutofillHints.username, AutofillHints.email],
          keyboardType: TextInputType.emailAddress, textInputAction: TextInputAction.next,
          autocorrect: false,
          decoration: const InputDecoration(labelText: 'Email address', prefixIcon: Icon(Icons.mail_outline_rounded)),
          validator: (v) => RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch((v ?? '').trim()) ? null : 'Enter a valid email',
        ),
        const SizedBox(height: 16),
        TextFormField(controller: _password, enabled: !_loading, obscureText: _obscure,
          autofillHints: const [AutofillHints.password], textInputAction: TextInputAction.done,
          onFieldSubmitted: (_) => _submit(), enableSuggestions: false, autocorrect: false,
          decoration: InputDecoration(labelText: 'Password', prefixIcon: const Icon(Icons.lock_outline_rounded),
            suffixIcon: IconButton(tooltip: _obscure ? 'Show password' : 'Hide password',
              icon: Icon(_obscure ? Icons.visibility_off_outlined : Icons.visibility_outlined),
              onPressed: () => setState(() => _obscure = !_obscure))),
          validator: (v) => v == null || v.isEmpty ? 'Enter your password' : null,
        ),
        if (_error != null) Padding(padding: const EdgeInsets.only(top: 16), child: Text(_error!, style: const TextStyle(color: AppColors.danger))),
        const SizedBox(height: 24),
        FilledButton(onPressed: _loading ? null : _submit,
          child: _loading ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Text('Sign in')),
        const SizedBox(height: 12),
        OutlinedButton(onPressed: _loading ? null : () => Navigator.push(context, MaterialPageRoute(builder: (_) => const RegisterScreen())), child: const Text('Create a buyer account')),
        TextButton(onPressed: _loading ? null : () => Navigator.pushAndRemoveUntil(context, MaterialPageRoute(builder: (_) => const HomeShell()), (_) => false), child: const Text('Continue browsing as a guest')),
      ],
    ))),
  );
}
