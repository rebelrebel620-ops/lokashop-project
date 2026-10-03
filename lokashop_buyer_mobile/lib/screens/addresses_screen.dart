import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';
import '../services/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';
import '../widgets/address_form.dart';

class AddressesScreen extends StatefulWidget {
  const AddressesScreen({super.key});
  @override
  State<AddressesScreen> createState() => _AddressesScreenState();
}

class _AddressesScreenState extends State<AddressesScreen> {
  bool loading = true;
  List addresses = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    try {
      final res = await context.read<AppState>().api.get('/addresses');
      setState(() => addresses = List.from(res));
    } catch (_) {
      if (mounted) AppSnack.show(context, 'Could not load addresses.', error: true);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _setDefault(int id) async {
    try {
      await context.read<AppState>().api.post('/addresses/$id/default');
      _load();
    } on ApiException catch (e) {
      if (mounted) AppSnack.show(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Delivery addresses')),
      body: loading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : addresses.isEmpty
              ? const EmptyState(icon: Icons.location_on_outlined, title: 'No addresses saved', subtitle: 'Add one during checkout, or with the button below.')
              : RefreshIndicator(
                  onRefresh: _load,
                  color: AppColors.primary,
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
                    itemCount: addresses.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, i) {
                      final a = addresses[i];
                      final isDefault = a['is_default'] == true || a['is_default'] == 1;
                      return Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(14), border: Border.all(color: AppColors.line)),
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(children: [
                                    Text('${a['recipient']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                                    if (isDefault) ...[
                                      const SizedBox(width: 8),
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                        decoration: BoxDecoration(color: AppColors.primary.withOpacity(.1), borderRadius: BorderRadius.circular(20)),
                                        child: const Text('Default', style: TextStyle(color: AppColors.primary, fontSize: 10.5, fontWeight: FontWeight.w700)),
                                      ),
                                    ],
                                  ]),
                                  const SizedBox(height: 2),
                                  Text('${a['phone']}', style: const TextStyle(color: AppColors.muted, fontSize: 12.5)),
                                  Text('${a['street']}, ${a['barangay']}, ${a['city']}, ${a['province']}', style: const TextStyle(color: AppColors.muted, fontSize: 12.5)),
                                ],
                              ),
                            ),
                            if (!isDefault)
                              TextButton(onPressed: () => _setDefault(a['id']), child: const Text('Set default')),
                          ],
                        ),
                      );
                    },
                  ),
                ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: AppColors.primary,
        onPressed: () async {
          final saved = await showModalBottomSheet<bool>(
            context: context,
            isScrollControlled: true,
            backgroundColor: Colors.transparent,
            builder: (_) => const AddressForm(),
          );
          if (saved == true) _load();
        },
        icon: const Icon(Icons.add_rounded),
        label: const Text('Add address'),
      ),
    );
  }
}
