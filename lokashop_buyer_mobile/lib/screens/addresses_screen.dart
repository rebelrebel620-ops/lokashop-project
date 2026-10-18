import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import '../widgets/common.dart';
import '../widgets/design.dart';
import '../widgets/address_form.dart';
import 'buyer_features.dart';

class AddressesScreen extends StatelessWidget {
  const AddressesScreen({super.key});
  Future<void> editor(
    BuildContext context,
    Future<void> Function() reload, [
    Map? address,
  ]) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => AddressForm(initial: address),
    );
    if (saved == true) await reload();
  }

  @override
  Widget build(BuildContext context) => ResourcePage(
    title: 'Your delivery spots',
    path: '/addresses',
    builder: (context, data, reload) => Column(
      children: [
        SizedBox(
          width: double.infinity,
          child: OutlinedButton.icon(
            onPressed: () => editor(context, reload),
            icon: const Icon(Icons.add_location_alt_outlined),
            label: const Text('Add address'),
          ),
        ),
        const SizedBox(height: 20),
        if ((data as List).isEmpty)
          const EmptyState(
            icon: Icons.location_on_outlined,
            title: 'Where should we deliver?',
            subtitle: 'Add a complete delivery address.',
          ),
        ...data.map(
          (a) => Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: SheetCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          '${a['recipient']}',
                          style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 18,
                          ),
                        ),
                      ),
                      if (a['is_default'] == 1)
                        const Chip(label: Text('Default')),
                    ],
                  ),
                  Text(
                    '${a['phone']}\n${a['street']}, ${a['barangay']}, ${a['city']}, ${a['province']} ${a['postal_code']}',
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 10,
                    children: [
                      TextButton(
                        onPressed: () => editor(context, reload, Map.from(a)),
                        child: const Text('Edit'),
                      ),
                      if (a['is_default'] != 1)
                        TextButton(
                          onPressed: () => runAction(context, () async {
                            await context.read<AppState>().api.post(
                              '/addresses/${a['id']}/default',
                            );
                            await reload();
                          }),
                          child: const Text('Make default'),
                        ),
                      TextButton(
                        onPressed: () async {
                          if (await confirmAction(
                                context,
                                'Delete this address?',
                                'Addresses linked to existing orders are retained for your order history.',
                                label: 'Delete',
                              ) &&
                              context.mounted) {
                            await runAction(context, () async {
                              await context.read<AppState>().api.post(
                                '/addresses/delete',
                                {'id': a['id']},
                              );
                              await reload();
                            });
                          }
                        },
                        child: const Text('Delete'),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    ),
  );
}
