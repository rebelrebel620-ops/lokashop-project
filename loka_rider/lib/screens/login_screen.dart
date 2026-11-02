import 'package:flutter/material.dart';
import '../session.dart';
import 'registration_screen.dart';

class LoginScreen extends StatefulWidget {
  final Session session;
  const LoginScreen({super.key, required this.session});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final form = GlobalKey<FormState>();
  final email = TextEditingController(), password = TextEditingController();
  bool busy = false, obscure = true;
  String? error;
  @override
  void dispose() {
    email.dispose();
    password.dispose();
    super.dispose();
  }

  Future<void> login() async {
    if (busy || !form.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.session.login(email.text.trim(), password.text);
    } catch (e) {
      if (mounted) setState(() => error = '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  void register() => Navigator.push(
    context,
    MaterialPageRoute(builder: (_) => SignUpScreen(session: widget.session)),
  );
  @override
  Widget build(BuildContext context) => Scaffold(
    body: Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFE5EFE8), Color(0xFFF8FAF4)],
        ),
      ),
      child: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 520),
            child: SingleChildScrollView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              padding: const EdgeInsets.all(22),
              child: AutofillGroup(
                child: Form(
                  key: form,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          Image.asset(
                            'assets/icons/rider_logo.png',
                            width: 54,
                            height: 54,
                          ),
                          const SizedBox(width: 12),
                          const Text(
                            'LokaShop Logistics',
                            style: TextStyle(
                              fontWeight: FontWeight.w900,
                              fontSize: 22,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 25),
                      Container(
                        padding: const EdgeInsets.all(25),
                        decoration: BoxDecoration(
                          color: const Color(0xFF123E32),
                          borderRadius: BorderRadius.circular(26),
                        ),
                        child: const Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'YOUR LOGISTICS WORKSPACE',
                              style: TextStyle(
                                color: Color(0xFFDDECBB),
                                fontSize: 10,
                                letterSpacing: 2,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            SizedBox(height: 14),
                            Text(
                              'Move parcels.\nConnect communities.',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 31,
                                fontWeight: FontWeight.w900,
                                height: 1.15,
                              ),
                            ),
                            SizedBox(height: 14),
                            Text(
                              'One sign-in for riders, drivers and sorting centers.',
                              style: TextStyle(
                                color: Colors.white70,
                                height: 1.5,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 20),
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(22),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              const Text(
                                'Log in',
                                style: TextStyle(
                                  fontSize: 24,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              const SizedBox(height: 6),
                              const Text(
                                'Use your approved logistics account.',
                              ),
                              const SizedBox(height: 22),
                              TextFormField(
                                controller: email,
                                enabled: !busy,
                                keyboardType: TextInputType.emailAddress,
                                textInputAction: TextInputAction.next,
                                autocorrect: false,
                                autofillHints: const [
                                  AutofillHints.username,
                                  AutofillHints.email,
                                ],
                                decoration: const InputDecoration(
                                  labelText: 'Email address',
                                  prefixIcon: Icon(Icons.mail_outline),
                                ),
                                validator: (v) =>
                                    RegExp(
                                      r'^[^@\s]+@[^@\s]+\.[^@\s]+$',
                                    ).hasMatch((v ?? '').trim())
                                    ? null
                                    : 'Enter a valid email',
                              ),
                              const SizedBox(height: 16),
                              TextFormField(
                                controller: password,
                                enabled: !busy,
                                obscureText: obscure,
                                autofillHints: const [AutofillHints.password],
                                textInputAction: TextInputAction.done,
                                onFieldSubmitted: (_) => login(),
                                decoration: InputDecoration(
                                  labelText: 'Password',
                                  prefixIcon: const Icon(Icons.lock_outline),
                                  suffixIcon: IconButton(
                                    tooltip: obscure
                                        ? 'Show password'
                                        : 'Hide password',
                                    onPressed: () =>
                                        setState(() => obscure = !obscure),
                                    icon: Icon(
                                      obscure
                                          ? Icons.visibility_off_outlined
                                          : Icons.visibility_outlined,
                                    ),
                                  ),
                                ),
                                validator: (v) => v == null || v.isEmpty
                                    ? 'Enter your password'
                                    : null,
                              ),
                              if (error != null)
                                Padding(
                                  padding: const EdgeInsets.only(top: 14),
                                  child: Text(
                                    error!,
                                    style: TextStyle(
                                      color: Theme.of(
                                        context,
                                      ).colorScheme.error,
                                    ),
                                  ),
                                ),
                              const SizedBox(height: 22),
                              FilledButton(
                                onPressed: busy ? null : login,
                                child: Text(busy ? 'Logging in…' : 'Log in'),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 20),
                      const Text(
                        'New to LokaShop Logistics?',
                        textAlign: TextAlign.center,
                        style: TextStyle(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 12),
                      OutlinedButton.icon(
                        onPressed: busy ? null : register,
                        icon: const Icon(Icons.person_add_alt_1),
                        label: const Text('Sign up'),
                      ),
                      const SizedBox(height: 12),
                      const Text(
                        'Verify your email during registration, then wait for approval.',
                        textAlign: TextAlign.center,
                        style: TextStyle(fontSize: 12),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    ),
  );
}
