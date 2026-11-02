import 'dart:async';
import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import 'package:image_picker/image_picker.dart';
import '../session.dart';
import '../widgets/place_fields.dart';

class SignUpScreen extends StatelessWidget {
  final Session session;
  const SignUpScreen({super.key, required this.session});
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Sign up')),
    body: ListView(
      padding: const EdgeInsets.all(22),
      children: [
        Text(
          'Join LokaShop Logistics',
          style: Theme.of(context).textTheme.headlineMedium,
        ),
        const SizedBox(height: 8),
        const Text('Choose how you want to work with us.'),
        const SizedBox(height: 24),
        for (final type in ['sorting_center', 'courier'])
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: ListTile(
                leading: Icon(
                  type == 'courier'
                      ? Icons.local_shipping_outlined
                      : Icons.warehouse_outlined,
                  size: 36,
                ),
                title: Text(
                  type == 'courier' ? 'Courier' : 'Sorting center',
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                subtitle: Text(
                  type == 'courier'
                      ? 'Register as a rider or driver.'
                      : 'Apply to operate a municipality sorting center.',
                ),
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) =>
                        RegistrationScreen(session: session, type: type),
                  ),
                ),
              ),
            ),
          ),
        const SizedBox(height: 16),
        const Text(
          'Email verification comes first. Your application will then be reviewed by your sorting center or logistics administrator.',
        ),
      ],
    ),
  );
}

class RegistrationScreen extends StatefulWidget {
  final Session session;
  final String type;
  const RegistrationScreen({
    super.key,
    required this.session,
    required this.type,
  });
  @override
  State<RegistrationScreen> createState() => _RegistrationScreenState();
}

class _RegistrationScreenState extends State<RegistrationScreen> {
  final forms = List.generate(3, (_) => GlobalKey<FormState>());
  final controls = <String, TextEditingController>{};
  final files = <String, XFile>{};
  Map<String, String> address = {};
  String courier = 'rider', vehicle = 'motorcycle';
  bool terms = false, busy = false, verified = false;
  int step = 0, seconds = 0;
  String? token, message;
  final otp = TextEditingController();
  Timer? timer;
  bool get center => widget.type == 'sorting_center';
  TextEditingController field(String key) =>
      controls.putIfAbsent(key, () => TextEditingController());
  @override
  void dispose() {
    timer?.cancel();
    otp.dispose();
    for (final c in controls.values) {
      c.dispose();
    }
    super.dispose();
  }

  void cooldown() {
    seconds = 60;
    timer?.cancel();
    timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (!mounted) {
        t.cancel();
        return;
      }
      setState(() => seconds--);
      if (seconds <= 0) t.cancel();
    });
  }

  Future<void> choose(String key) async {
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['jpg', 'jpeg', 'png', 'pdf'],
      );
      final f = result?.files.single;
      if (f == null || f.path == null) return;
      if (f.size > 5 * 1024 * 1024) {
        if (mounted) {
          setState(() => message = 'Each document must be 5 MB or smaller.');
        }
        return;
      }
      if (mounted) setState(() => files[key] = XFile(f.path!, name: f.name));
    } catch (e) {
      if (mounted) setState(() => message = 'Could not open document: $e');
    }
  }

  Future<void> submit() async {
    final requiredFields = step == 0
        ? [
            'first_name',
            'last_name',
            'email',
            'phone',
            if (center) ...[
              'business_name',
              'sec_number',
              'tin',
            ] else
              'date_of_birth',
          ]
        : step == 1
        ? ['street', 'postal_code']
        : [
            'password',
            'password_confirmation',
            if (!center) ...[
              'license_number',
              'license_expires_at',
              'plate_number',
            ],
          ];
    if (requiredFields.any((key) => field(key).text.trim().isEmpty)) {
      setState(
        () => message = 'Complete all fields in this step before continuing.',
      );
      return;
    }
    if (step == 1 &&
        [
          'region',
          'province',
          'city',
          'barangay',
        ].any((key) => (address[key] ?? '').isEmpty)) {
      setState(() => message = 'Choose your complete address from the lists.');
      return;
    }
    if (step == 2 &&
        field('password').text != field('password_confirmation').text) {
      setState(() => message = 'Passwords do not match.');
      return;
    }
    if (!forms[step].currentState!.validate()) return;
    FocusManager.instance.primaryFocus?.unfocus();
    if (step < 2) {
      setState(() {
        step++;
        message = null;
      });
      return;
    }
    final docs = center
        ? ['id_document', 'sec_document', 'bir_document']
        : ['id_document', 'permit_document'];
    if (docs.any((d) => !files.containsKey(d)) || !terms) {
      setState(
        () => message = 'Attach all required documents and accept the terms.',
      );
      return;
    }
    setState(() => busy = true);
    try {
      final data = await widget.session.api.upload(
        '/api/mobile/register',
        {
          'account_type': widget.type,
          for (final e in controls.entries) e.key: e.value.text,
          ...address,
          'terms': '1',
          if (!center) ...{'courier_type': courier, 'vehicle': vehicle},
        },
        {for (final d in docs) d: files[d]!},
      );
      if (mounted) {
        setState(() {
          token = '${data['registration_token']}';
          message = '${data['message']}';
          cooldown();
        });
      }
    } catch (e) {
      if (mounted) setState(() => message = '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> verify() async {
    if (!RegExp(r'^\d{6}$').hasMatch(otp.text.trim())) {
      setState(() => message = 'Enter the 6-digit email code.');
      return;
    }
    setState(() => busy = true);
    try {
      final d = await widget.session.api.post('/api/mobile/register/verify', {
        'registration_token': token,
        'otp': otp.text.trim(),
      });
      verified = true;
      if (mounted) {
        await showDialog<void>(
          context: context,
          builder: (c) => AlertDialog(
            title: const Text('Application submitted'),
            content: Text('${d['message']}'),
            actions: [
              FilledButton(
                onPressed: () => Navigator.pop(c),
                child: const Text('Done'),
              ),
            ],
          ),
        );
      }
      if (mounted) {
        setState(() => busy = false);
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) setState(() => message = '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> resend() async {
    setState(() => busy = true);
    try {
      final d = await widget.session.api.post('/api/mobile/register/resend', {
        'registration_token': token,
      });
      if (mounted) {
        setState(() {
          message = '${d['message']}';
          cooldown();
        });
      }
    } catch (e) {
      if (mounted) setState(() => message = '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Widget input(
    String key,
    String label, {
    bool password = false,
    String? hint,
    bool date = false,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 16),
    child: TextFormField(
      controller: field(key),
      enabled: !busy,
      obscureText: password,
      readOnly: date,
      keyboardType: key == 'email'
          ? TextInputType.emailAddress
          : key == 'phone'
          ? TextInputType.phone
          : TextInputType.text,
      decoration: InputDecoration(
        labelText: label,
        helperText: hint,
        suffixIcon: date ? const Icon(Icons.calendar_month_outlined) : null,
      ),
      onTap: !date
          ? null
          : () async {
              final now = DateTime.now();
              final birth = key == 'date_of_birth';
              final d = await showDatePicker(
                context: context,
                initialDate: birth
                    ? DateTime(now.year - 18, now.month, now.day)
                    : now,
                firstDate: birth
                    ? DateTime(1900)
                    : DateTime(now.year, now.month, now.day),
                lastDate: birth
                    ? DateTime(now.year - 18, now.month, now.day)
                    : DateTime(now.year + 20),
              );
              if (d != null) {
                field(key).text = d.toIso8601String().split('T').first;
              }
            },
      validator: (v) {
        if (v == null || v.trim().isEmpty) return 'Required';
        if (key == 'password_confirmation' && v != field('password').text) {
          return 'Passwords do not match';
        }
        if (key == 'email' &&
            !RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(v.trim())) {
          return 'Enter a valid email';
        }
        return null;
      },
    ),
  );
  Widget section(int i, List<Widget> children) => TickerMode(
    enabled: step == i,
    child: Form(
      key: forms[i],
      child: ListView(padding: const EdgeInsets.all(20), children: children),
    ),
  );
  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !busy,
    onPopInvokedWithResult: (didPop, result) {
      if (didPop && token != null && !verified) {
        unawaited(
          widget.session.api
              .post('/api/mobile/register/cancel', {
                'registration_token': token,
              })
              .catchError((_) => <String, dynamic>{}),
        );
      }
    },
    child: Scaffold(
      appBar: AppBar(
        title: Text(
          token != null
              ? 'Verify email'
              : center
              ? 'Sorting center registration'
              : 'Courier registration',
        ),
      ),
      body: token != null
          ? ListView(
              padding: const EdgeInsets.all(22),
              children: [
                const Icon(Icons.mark_email_unread_outlined, size: 56),
                const SizedBox(height: 20),
                Text(
                  'Check your email',
                  style: Theme.of(context).textTheme.headlineMedium,
                ),
                Text(
                  'We sent a code to ${field('email').text}. Your account is created only after verification.',
                ),
                const SizedBox(height: 24),
                TextField(
                  controller: otp,
                  enabled: !busy,
                  keyboardType: TextInputType.number,
                  maxLength: 6,
                  decoration: const InputDecoration(
                    labelText: 'Verification code',
                  ),
                ),
                if (message != null) Text(message!),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: busy ? null : verify,
                  child: const Text('Verify and submit application'),
                ),
                TextButton(
                  onPressed: busy || seconds > 0 ? null : resend,
                  child: Text(
                    seconds > 0 ? 'Resend in ${seconds}s' : 'Resend code',
                  ),
                ),
              ],
            )
          : Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 12, 20, 8),
                  child: Row(
                    children: [
                      for (var i = 0; i < 3; i++)
                        Expanded(
                          child: Column(
                            children: [
                              LinearProgressIndicator(
                                value: i <= step ? 1 : 0,
                                minHeight: 5,
                              ),
                              const SizedBox(height: 8),
                              Text(
                                ['Details', 'Address', 'Documents'][i],
                                style: TextStyle(
                                  fontWeight: i == step
                                      ? FontWeight.w800
                                      : FontWeight.normal,
                                ),
                              ),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
                Expanded(
                  child: IndexedStack(
                    index: step,
                    children: [
                      section(0, [
                        Text(
                          center
                              ? 'Your sorting center'
                              : 'Your courier details',
                          style: Theme.of(context).textTheme.titleLarge,
                        ),
                        const SizedBox(height: 20),
                        if (!center) ...[
                          DropdownButtonFormField<String>(
                            initialValue: courier,
                            decoration: const InputDecoration(
                              labelText: 'Courier role',
                            ),
                            items: const [
                              DropdownMenuItem(
                                value: 'rider',
                                child: Text(
                                  'Rider • local pickup and delivery',
                                ),
                              ),
                              DropdownMenuItem(
                                value: 'driver',
                                child: Text(
                                  'Driver • sorting center transfers',
                                ),
                              ),
                            ],
                            onChanged: busy
                                ? null
                                : (v) => setState(() {
                                    courier = v!;
                                    vehicle = courier == 'rider'
                                        ? 'motorcycle'
                                        : '4-wheel truck';
                                  }),
                          ),
                          const SizedBox(height: 16),
                        ],
                        input('first_name', 'First name'),
                        input('last_name', 'Last name'),
                        input('email', 'Email address'),
                        input('phone', 'Phone number', hint: '09XXXXXXXXX'),
                        if (center) ...[
                          input('business_name', 'Sorting center name'),
                          input(
                            'sec_number',
                            'SEC registration number',
                            hint: 'Example: CS123456789',
                          ),
                          input('tin', 'TIN', hint: '000-000-000-000'),
                        ] else
                          input('date_of_birth', 'Date of birth', date: true),
                      ]),
                      section(1, [
                        const Text(
                          'Registered address',
                          style: TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 12),
                        if (!center)
                          const Padding(
                            padding: EdgeInsets.only(bottom: 16),
                            child: Text(
                              'An approved sorting center must serve your municipality.',
                            ),
                          ),
                        PlaceFields(
                          enabled: !busy,
                          onChanged: (v) => address = v,
                        ),
                        input('street', 'Street / house number'),
                        input('postal_code', 'Postal code', hint: '4 digits'),
                      ]),
                      section(2, [
                        if (!center) ...[
                          DropdownButtonFormField<String>(
                            key: ValueKey(courier),
                            initialValue: vehicle,
                            decoration: const InputDecoration(
                              labelText: 'Vehicle',
                            ),
                            items:
                                (courier == 'rider'
                                        ? ['motorcycle', 'tricycle']
                                        : [
                                            '4-wheel truck',
                                            '6-wheel truck',
                                            '8-wheel truck',
                                            '10-wheel truck',
                                          ])
                                    .map(
                                      (v) => DropdownMenuItem(
                                        value: v,
                                        child: Text(v),
                                      ),
                                    )
                                    .toList(),
                            onChanged: busy
                                ? null
                                : (v) => setState(() => vehicle = v!),
                          ),
                          const SizedBox(height: 16),
                          input(
                            'license_number',
                            'License number',
                            hint: 'Example: N01-23-456789',
                          ),
                          input(
                            'license_expires_at',
                            'License expiry date',
                            date: true,
                          ),
                          input('plate_number', 'Plate number'),
                        ],
                        const Text(
                          'Required documents',
                          style: TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const Text('JPG, PNG or PDF • up to 5 MB each'),
                        for (final d
                            in center
                                ? [
                                    'id_document',
                                    'sec_document',
                                    'bir_document',
                                  ]
                                : ['id_document', 'permit_document'])
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 8),
                            child: OutlinedButton.icon(
                              onPressed: busy ? null : () => choose(d),
                              icon: Icon(
                                files.containsKey(d)
                                    ? Icons.check_circle_outline
                                    : Icons.upload_file,
                              ),
                              label: Text(
                                files[d]?.name ??
                                    {
                                      'id_document': 'Upload valid ID',
                                      'sec_document': 'Upload SEC document',
                                      'bir_document': 'Upload BIR document',
                                      'permit_document':
                                          'Upload permit document',
                                    }[d]!,
                              ),
                            ),
                          ),
                        const SizedBox(height: 20),
                        input(
                          'password',
                          'Password',
                          password: true,
                          hint:
                              '8+ characters, upper/lowercase, number and symbol',
                        ),
                        input(
                          'password_confirmation',
                          'Confirm password',
                          password: true,
                        ),
                        CheckboxListTile(
                          value: terms,
                          onChanged: busy
                              ? null
                              : (v) => setState(() => terms = v ?? false),
                          title: const Text(
                            'I accept the terms and privacy policy',
                          ),
                          subtitle: const Text(
                            'I confirm these details and documents are accurate and consent to their use for account verification.',
                          ),
                          contentPadding: EdgeInsets.zero,
                        ),
                      ]),
                    ],
                  ),
                ),
                if (message != null)
                  Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 20,
                      vertical: 8,
                    ),
                    child: Text(
                      message!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                  ),
                SafeArea(
                  top: false,
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Row(
                      children: [
                        if (step > 0)
                          TextButton(
                            onPressed: busy
                                ? null
                                : () => setState(() => step--),
                            child: const Text('Back'),
                          ),
                        const Spacer(),
                        FilledButton(
                          onPressed: busy ? null : submit,
                          child: Text(
                            busy
                                ? 'Submitting…'
                                : step < 2
                                ? 'Continue'
                                : 'Send verification code',
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
    ),
  );
}
