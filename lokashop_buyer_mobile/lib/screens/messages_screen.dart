import 'package:flutter/material.dart';
import '../widgets/design.dart';
import '../theme.dart';
import 'buyer_features.dart';
import 'message_thread_screen.dart';

class MessagesScreen extends StatelessWidget {
  const MessagesScreen({super.key});
  @override
  Widget build(BuildContext context) => ResourcePage(
    title: 'Your conversations',
    path: '/messages/contacts',
    builder: (context, data, reload) => Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Eyebrow('A DIRECT LINE TO YOUR LOCAL SELLERS'),
        const SizedBox(height: 20),
        if ((data as List).isEmpty)
          const SheetCard(
            child: Text(
              'Available sellers and LokaShop support will appear here.',
            ),
          ),
        ...data.map(
          (c) => Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: SheetCard(
              padding: const EdgeInsets.all(8),
              child: ListTile(
                leading: CircleAvatar(
                  backgroundColor: AppColors.accent,
                  child: Text('${c['name']}'.characters.first),
                ),
                title: Text(
                  '${c['name']}',
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                subtitle: Text(
                  c['role'] == 'admin' ? 'LokaShop support' : 'Local seller',
                ),
                trailing: const Icon(
                  Icons.forum_outlined,
                  color: AppColors.primary,
                ),
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => MessageThreadScreen(
                      userId: c['id'],
                      userName: '${c['name']}',
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ],
    ),
  );
}
