import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import '../theme.dart';
import '../widgets/design.dart';
import '../widgets/common.dart';
import 'buyer_features.dart';
import 'addresses_screen.dart';
import 'notifications_screen.dart';
import 'complaints_screen.dart';
import 'login_screen.dart';

class ProfileTab extends StatefulWidget {
  const ProfileTab({super.key});
  @override
  State<ProfileTab> createState() => _ProfileTabState();
}

class _ProfileTabState extends State<ProfileTab> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => load());
  }

  Future<void> load() async {
    if (!context.read<AppState>().isLoggedIn) return;
    try {
      await context.read<AppState>().refreshMe();
    } catch (e) {
      if (mounted) AppSnack.show(context, '$e', error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final user = app.user ?? {};
    return Scaffold(
      appBar: AppBar(title: const Text('Your little corner')),
      body: PageBody(
        child: !app.isLoggedIn
            ? EmptyState(
                icon: Icons.person_outline,
                title: 'Welcome to the neighbourhood',
                subtitle: 'Sign in for saved finds, delivery updates and more.',
                action: FilledButton(
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const LoginScreen()),
                  ),
                  child: const Text('Sign in'),
                ),
              )
            : RefreshIndicator(
                onRefresh: load,
                child: ListView(
                  padding: const EdgeInsets.all(22),
                  children: [
                    Container(
                      padding: const EdgeInsets.all(24),
                      decoration: BoxDecoration(
                        color: AppColors.primaryDark,
                        borderRadius: BorderRadius.circular(25),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (user['profile_photo_url'] != null)
                            AppPhoto(
                              '${user['profile_photo_url']}',
                              width: 70,
                              height: 70,
                            )
                          else
                            CircleAvatar(
                              radius: 32,
                              backgroundColor: AppColors.accent,
                              child: Text(
                                (user['name'] ?? app.session.name ?? 'B')
                                    .toString()
                                    .characters
                                    .first,
                                style: const TextStyle(
                                  fontSize: 26,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                          const SizedBox(height: 16),
                          Text(
                            '${user['name'] ?? app.session.name ?? 'Buyer'}',
                            style: const TextStyle(
                              fontWeight: FontWeight.w900,
                              fontSize: 24,
                              color: Colors.white,
                            ),
                          ),
                          Text(
                            '${user['email'] ?? app.session.email ?? ''}',
                            style: const TextStyle(color: Colors.white70),
                          ),
                          const SizedBox(height: 12),
                          TextButton.icon(
                            style: TextButton.styleFrom(
                              foregroundColor: AppColors.accent,
                            ),
                            onPressed: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => const ProfileEditor(),
                              ),
                            ).then((_) => load()),
                            icon: const Icon(Icons.edit_outlined, size: 17),
                            label: const Text('Edit profile & photo'),
                          ),
                        ],
                      ),
                    ),
                    const SectionHeading('Made for your shopping'),
                    tile(
                      Icons.favorite_border,
                      'Saved finds',
                      'All the things you love',
                      const WishlistScreen(),
                    ),
                    tile(
                      Icons.storefront_outlined,
                      'Following stores',
                      'Your local favourites',
                      const FollowingScreen(),
                    ),
                    tile(
                      Icons.local_offer_outlined,
                      'Vouchers',
                      'Discover and claim savings',
                      const VouchersScreen(),
                    ),
                    tile(
                      Icons.rate_review_outlined,
                      'My reviews',
                      'Share your product stories',
                      const ReviewsScreen(),
                    ),
                    tile(
                      Icons.assignment_return_outlined,
                      'Returns & resolutions',
                      'Follow an issue through to resolution',
                      const ReturnsScreen(),
                    ),
                    const SectionHeading('Account essentials'),
                    tile(
                      Icons.location_on_outlined,
                      'Delivery addresses',
                      'Add, edit and choose your default',
                      const AddressesScreen(),
                    ),
                    tile(
                      Icons.notifications_none,
                      'Notifications',
                      'Order updates and delivery OTPs',
                      const NotificationsScreen(),
                    ),
                    tile(
                      Icons.lock_outline,
                      'Password & contact details',
                      'Secure, verified account changes',
                      const SecurityScreen(),
                    ),
                    tile(
                      Icons.support_agent,
                      'My complaints',
                      'Get help from LokaShop',
                      const ComplaintsScreen(),
                    ),
                    const SizedBox(height: 18),
                    OutlinedButton.icon(
                      onPressed: () async {
                        if (!await confirmAction(
                          context,
                          'Sign out?',
                          'You can sign back in whenever you are ready.',
                          label: 'Sign out',
                        )) {
                          return;
                        }
                        await app.logout();
                        if (context.mounted) {
                          Navigator.pushAndRemoveUntil(
                            context,
                            MaterialPageRoute(
                              builder: (_) => const LoginScreen(),
                            ),
                            (_) => false,
                          );
                        }
                      },
                      icon: const Icon(Icons.logout),
                      label: const Text('Sign out'),
                    ),
                    const SizedBox(height: 20),
                    const Center(
                      child: Text(
                        'LOKASHOP · LOCAL FINDS, DELIVERED WITH CARE',
                        style: TextStyle(
                          fontSize: 9,
                          letterSpacing: 1.1,
                          color: AppColors.muted,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
      ),
    );
  }

  Widget tile(IconData icon, String title, String subtitle, Widget screen) =>
      Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: SheetCard(
          padding: const EdgeInsets.all(8),
          child: ListTile(
            leading: Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFEEF3E7),
                borderRadius: BorderRadius.circular(13),
              ),
              child: Icon(icon, color: AppColors.primary, size: 22),
            ),
            title: Text(
              title,
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
            ),
            subtitle: Text(
              subtitle,
              style: const TextStyle(fontSize: 11, color: AppColors.muted),
            ),
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => screen),
            ),
          ),
        ),
      );
}

class ProfileEditor extends StatefulWidget {
  const ProfileEditor({super.key});
  @override
  State<ProfileEditor> createState() => _ProfileEditorState();
}

class _ProfileEditorState extends State<ProfileEditor> {
  late final name = TextEditingController(
    text:
        context.read<AppState>().user?['name'] ??
        context.read<AppState>().session.name,
  );
  File? photo;
  bool busy = false;
  @override
  void dispose() {
    name.dispose();
    super.dispose();
  }

  Future<void> save() async {
    setState(() => busy = true);
    try {
      final app = context.read<AppState>();
      await app.api.postMultipart(
        '/profile',
        {'name': name.text.trim(), 'phone': '${app.user?['phone'] ?? ''}'},
        file: photo,
        fileField: 'profile_photo',
      );
      await app.refreshMe();
      if (mounted) Navigator.pop(context);
    } catch (e) {
      if (mounted) AppSnack.show(context, '$e', error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Make it yours')),
    body: PageBody(
      child: ListView(
        padding: const EdgeInsets.all(22),
        children: [
          SheetCard(
            child: Column(
              children: [
                if (photo != null)
                  ClipRRect(
                    borderRadius: BorderRadius.circular(24),
                    child: Image.file(
                      photo!,
                      width: 120,
                      height: 120,
                      fit: BoxFit.cover,
                    ),
                  )
                else
                  AppPhoto(
                    context.read<AppState>().user?['profile_photo_url'],
                    width: 120,
                    height: 120,
                  ),
                TextButton.icon(
                  onPressed: () async {
                    final p = await ImagePicker().pickImage(
                      source: ImageSource.gallery,
                      imageQuality: 80,
                      maxWidth: 1600,
                    );
                    if (p != null && mounted) {
                      setState(() => photo = File(p.path));
                    }
                  },
                  icon: const Icon(Icons.add_a_photo_outlined),
                  label: const Text('Choose profile photo'),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: name,
                  decoration: const InputDecoration(labelText: 'Full name'),
                ),
                const SizedBox(height: 16),
                const Text(
                  'Update your email or phone through Password & contact details for secure verification.',
                  style: TextStyle(color: AppColors.muted, fontSize: 12),
                ),
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: busy ? null : save,
                  child: Text(busy ? 'Saving…' : 'Save profile'),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}
