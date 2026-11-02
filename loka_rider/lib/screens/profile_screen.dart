import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../models.dart';
import '../session.dart';

class ProfileScreen extends StatefulWidget {
  final Session session;
  const ProfileScreen({super.key, required this.session});
  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  late final name = TextEditingController(text: widget.session.rider?.name);
  bool busy = false;
  String? message;
  XFile? photo;
  @override
  void dispose() {
    name.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (name.text.trim().length < 2) {
      setState(() => message = 'Enter your full name.');
      return;
    }
    setState(() => busy = true);
    try {
      final d = await widget.session.api.upload(
        '/api/mobile/profile',
        {'name': name.text.trim(), 'phone': widget.session.rider?.phone ?? ''},
        {if (photo != null) 'profile_photo': photo!},
      );
      widget.session.updateRider(
        Rider.fromJson(Map<String, dynamic>.from(d['rider'])),
      );
      if (mounted) setState(() => message = 'Profile saved.');
    } catch (e) {
      if (mounted) setState(() => message = '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> pick(ImageSource source) async {
    try {
      final p = await ImagePicker().pickImage(
        source: source,
        maxWidth: 1200,
        imageQuality: 85,
      );
      if (p != null && mounted) {
        setState(() => photo = p);
        await save();
      }
    } catch (e) {
      if (mounted) setState(() => message = '$e');
    }
  }

  Future<void> security(String kind) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) =>
            AccountSecurityScreen(session: widget.session, kind: kind),
      ),
    );
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final r = widget.session.rider!;
    final raw = '${r.extra['profile_photo_url'] ?? ''}';
    return Scaffold(
      appBar: AppBar(title: const Text('Account settings')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Center(
            child: CircleAvatar(
              radius: 46,
              backgroundImage: raw.isEmpty
                  ? null
                  : NetworkImage(
                      widget.session.api.mediaUrl(raw),
                      headers: widget.session.api.imageHeaders(raw),
                    ),
              child: raw.isEmpty
                  ? const Icon(Icons.person_outline, size: 42)
                  : null,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            r.name,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleLarge,
          ),
          Wrap(
            alignment: WrapAlignment.center,
            spacing: 8,
            children: [
              TextButton.icon(
                onPressed: busy ? null : () => pick(ImageSource.gallery),
                icon: const Icon(Icons.photo_library_outlined),
                label: const Text('Choose photo'),
              ),
              TextButton.icon(
                onPressed: busy ? null : () => pick(ImageSource.camera),
                icon: const Icon(Icons.camera_alt_outlined),
                label: const Text('Take photo'),
              ),
            ],
          ),
          const SizedBox(height: 20),
          TextField(
            controller: name,
            enabled: !busy,
            decoration: const InputDecoration(labelText: 'Full name'),
          ),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: busy ? null : save,
            child: const Text('Save profile'),
          ),
          if (busy) const LinearProgressIndicator(),
          if (message != null)
            Padding(padding: const EdgeInsets.all(12), child: Text(message!)),
          const SizedBox(height: 20),
          const Text(
            'Contact & security',
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
          ),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.phone_outlined),
                  title: const Text('Phone number'),
                  subtitle: Text(r.phone ?? 'Not set'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: busy ? null : () => security('phone'),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.mail_outline),
                  title: const Text('Email address'),
                  subtitle: Text(r.email),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: busy ? null : () => security('email'),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.lock_outline),
                  title: const Text('Password'),
                  subtitle: const Text('Change your login password'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: busy ? null : () => security('password'),
                ),
              ],
            ),
          ),
          const Padding(
            padding: EdgeInsets.all(12),
            child: Text(
              'Contact changes require your current password and an email verification code.',
            ),
          ),
        ],
      ),
    );
  }
}

class AccountSecurityScreen extends StatefulWidget {
  final Session session;
  final String kind;
  const AccountSecurityScreen({
    super.key,
    required this.session,
    required this.kind,
  });
  @override
  State<AccountSecurityScreen> createState() => _AccountSecurityScreenState();
}

class _AccountSecurityScreenState extends State<AccountSecurityScreen> {
  final form = GlobalKey<FormState>();
  final current = TextEditingController(),
      value = TextEditingController(),
      confirm = TextEditingController(),
      otp = TextEditingController();
  bool busy = false, verifying = false;
  String? message;
  @override
  void dispose() {
    for (final c in [current, value, confirm, otp]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> submit() async {
    if (!form.currentState!.validate()) return;
    setState(() => busy = true);
    try {
      final api = widget.session.api;
      if (widget.kind == 'password') {
        await api.post('/api/mobile/password', {
          'current_password': current.text,
          'password': value.text,
          'password_confirmation': confirm.text,
        });
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Password changed. Log in with your new password.'),
          ),
        );
        Navigator.pop(context);
        await widget.session.logout();
      } else if (verifying) {
        final d = await api.post('/api/mobile/contact/verify', {
          'otp': otp.text.trim(),
        });
        widget.session.updateRider(
          Rider.fromJson(Map<String, dynamic>.from(d['rider'])),
        );
        if (mounted) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(const SnackBar(content: Text('Contact updated.')));
          Navigator.pop(context);
        }
      } else {
        final d = await api.post('/api/mobile/contact', {
          'kind': widget.kind,
          widget.kind: value.text.trim(),
          'password': current.text,
        });
        if (mounted) {
          setState(() {
            verifying = true;
            message = '${d['message']}';
          });
        }
      }
    } catch (e) {
      if (mounted) setState(() => message = '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text('Change ${widget.kind}')),
    body: Form(
      key: form,
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Text(
            verifying ? 'Verify your change' : 'Keep your account up to date',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 20),
          if (!verifying) ...[
            TextFormField(
              controller: current,
              obscureText: true,
              enabled: !busy,
              decoration: const InputDecoration(labelText: 'Current password'),
              validator: (v) => v == null || v.isEmpty ? 'Required' : null,
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: value,
              enabled: !busy,
              obscureText: widget.kind == 'password',
              keyboardType: widget.kind == 'phone'
                  ? TextInputType.phone
                  : widget.kind == 'email'
                  ? TextInputType.emailAddress
                  : TextInputType.text,
              decoration: InputDecoration(
                labelText: 'New ${widget.kind}',
                helperText: widget.kind == 'password'
                    ? '8+ characters, uppercase, lowercase, number and symbol'
                    : null,
              ),
              validator: (v) =>
                  v == null || v.trim().isEmpty ? 'Required' : null,
            ),
            if (widget.kind == 'password') ...[
              const SizedBox(height: 16),
              TextFormField(
                controller: confirm,
                enabled: !busy,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'Confirm new password',
                ),
                validator: (v) =>
                    v != value.text ? 'Passwords do not match' : null,
              ),
            ],
          ] else
            TextFormField(
              controller: otp,
              enabled: !busy,
              keyboardType: TextInputType.number,
              maxLength: 6,
              decoration: const InputDecoration(labelText: 'Verification code'),
              validator: (v) => RegExp(r'^\d{6}$').hasMatch(v ?? '')
                  ? null
                  : 'Enter the 6-digit code',
            ),
          if (message != null)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 16),
              child: Text(message!),
            ),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: busy ? null : submit,
            child: Text(
              busy
                  ? 'Please wait…'
                  : verifying
                  ? 'Verify and save'
                  : widget.kind == 'password'
                  ? 'Update password'
                  : 'Send verification code',
            ),
          ),
          if (verifying)
            TextButton(
              onPressed: busy
                  ? null
                  : () => setState(() {
                      verifying = false;
                      otp.clear();
                    }),
              child: const Text('Change details or resend code'),
            ),
        ],
      ),
    ),
  );
}
