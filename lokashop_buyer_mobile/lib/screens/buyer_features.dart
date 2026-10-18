import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../services/app_state.dart';
import '../services/api_service.dart';
import '../widgets/common.dart';
import '../widgets/design.dart';
import '../widgets/product_card.dart';
import '../theme.dart';
import 'product_detail_screen.dart';
import 'login_screen.dart';

class ResourcePage extends StatefulWidget {
  final String title, path;
  final bool allowGuest;
  final Widget Function(BuildContext, dynamic, Future<void> Function()) builder;
  const ResourcePage({
    super.key,
    this.allowGuest = false,
    required this.title,
    required this.path,
    required this.builder,
  });
  @override
  State<ResourcePage> createState() => _ResourcePageState();
}

class _ResourcePageState extends State<ResourcePage> {
  dynamic data;
  String? error;
  bool loading = true;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    if (!context.read<AppState>().isLoggedIn && !widget.allowGuest) {
      setState(() => loading = false);
      return;
    }
    try {
      final result = await context.read<AppState>().api.get(widget.path);
      if (mounted) {
        setState(() {
          data = result;
          error = null;
        });
      }
    } catch (e) {
      if (mounted) setState(() => error = '$e');
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(widget.title)),
    body: PageBody(
      child: !context.watch<AppState>().isLoggedIn && !widget.allowGuest
          ? EmptyState(
              icon: Icons.lock_outline,
              title: 'Make yourself at home',
              subtitle: 'Sign in to use this part of your buyer account.',
              action: FilledButton(
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const LoginScreen()),
                ),
                child: const Text('Sign in'),
              ),
            )
          : loading
          ? const Center(child: CircularProgressIndicator())
          : error != null
          ? Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(error!),
                  TextButton(onPressed: load, child: const Text('Try again')),
                ],
              ),
            )
          : RefreshIndicator(
              onRefresh: load,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(22),
                child: widget.builder(context, data, load),
              ),
            ),
    ),
  );
}

Future<void> runAction(
  BuildContext context,
  Future<dynamic> Function() action, {
  String success = 'Saved.',
}) async {
  try {
    await action();
    if (context.mounted) AppSnack.show(context, success);
  } catch (e) {
    if (context.mounted) AppSnack.show(context, '$e', error: true);
  }
}

class WishlistScreen extends StatelessWidget {
  const WishlistScreen({super.key});
  @override
  Widget build(BuildContext context) => ResourcePage(
    title: 'Saved finds',
    path: '/wishlist',
    builder: (context, data, reload) {
      final rows = List.from(data);
      return rows.isEmpty
          ? const EmptyState(
              icon: Icons.favorite_outline,
              title: 'Keep the ones you love',
              subtitle: 'Tap the heart on a product to save it here.',
            )
          : Column(
              children: rows
                  .map(
                    (p) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: SheetCard(
                        child: Row(
                          children: [
                            AppPhoto(productPhoto(p), width: 72, height: 80),
                            const SizedBox(width: 14),
                            Expanded(
                              child: InkWell(
                                onTap: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) =>
                                        ProductDetailScreen(productId: p['id']),
                                  ),
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      '${p['name']}',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                    Text(
                                      money(p['effective_price'] ?? p['price']),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                            IconButton(
                              tooltip: 'Remove saved find',
                              onPressed: () => runAction(context, () async {
                                await context.read<AppState>().api.post(
                                  '/wishlist',
                                  {'product_id': p['id'], 'enabled': false},
                                );
                                await reload();
                              }),
                              icon: const Icon(
                                Icons.favorite,
                                color: AppColors.primary,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  )
                  .toList(),
            );
    },
  );
}

class VouchersScreen extends StatelessWidget {
  const VouchersScreen({super.key});
  @override
  Widget build(BuildContext context) => ResourcePage(
    title: 'A little extra off',
    path: '/vouchers',
    builder: (context, data, reload) {
      final rows = List.from(data);
      return rows.isEmpty
          ? const EmptyState(
              icon: Icons.local_offer_outlined,
              title: 'New offers are on their way',
              subtitle: 'Available vouchers will appear here.',
            )
          : Column(
              children: rows
                  .map(
                    (v) => Padding(
                      padding: const EdgeInsets.only(bottom: 14),
                      child: SheetCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Eyebrow('LOKASHOP VOUCHER'),
                            const SizedBox(height: 10),
                            Text(
                              v['type'] == 'percent'
                                  ? '${v['value']}% off'
                                  : money(v['value']),
                              style: const TextStyle(
                                fontSize: 28,
                                fontWeight: FontWeight.w900,
                                color: AppColors.primary,
                              ),
                            ),
                            Text(
                              v['type'] == 'shipping'
                                  ? 'Shipping discount'
                                  : v['seller_id'] == null
                                  ? 'Platform savings'
                                  : 'Store-specific savings',
                            ),
                            const SizedBox(height: 12),
                            Text(
                              'Minimum spend: ${money(v['minimum_spend'])}',
                              style: const TextStyle(
                                color: AppColors.muted,
                                fontSize: 12,
                              ),
                            ),
                            const Divider(height: 28),
                            Row(
                              children: [
                                Expanded(
                                  child: SelectableText(
                                    '${v['code']}',
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                ),
                                IconButton(
                                  onPressed: () {
                                    Clipboard.setData(
                                      ClipboardData(text: '${v['code']}'),
                                    );
                                    AppSnack.show(
                                      context,
                                      'Voucher code copied.',
                                    );
                                  },
                                  icon: const Icon(Icons.copy, size: 18),
                                ),
                                if (v['claimable'] == 1 ||
                                    v['claimable'] == true)
                                  FilledButton.tonal(
                                    onPressed: v['claimed'] == true
                                        ? null
                                        : () => runAction(context, () async {
                                            await context
                                                .read<AppState>()
                                                .api
                                                .post('/vouchers/claim', {
                                                  'voucher_id': v['id'],
                                                });
                                            await reload();
                                          }, success: 'Voucher saved.'),
                                    child: Text(
                                      v['claimed'] == true ? 'Saved' : 'Claim',
                                    ),
                                  ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                  )
                  .toList(),
            );
    },
  );
}

class FollowingScreen extends StatelessWidget {
  const FollowingScreen({super.key});
  @override
  Widget build(BuildContext context) => ResourcePage(
    title: 'Your local stores',
    path: '/following',
    builder: (context, data, reload) {
      final stores = List.from(data);
      return Column(
        children: [
          if (stores.isEmpty)
            const EmptyState(
              icon: Icons.storefront_outlined,
              title: 'Find your favourite stores',
              subtitle: 'Follow a seller from any product page.',
            ),
          for (final store in stores)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: SheetCard(
                child: ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(
                    Icons.storefront_outlined,
                    color: AppColors.primary,
                  ),
                  title: Text('${store['name']}'),
                  subtitle: Text('${store['category'] ?? ''}'),
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => StoreScreen(sellerId: store['user_id']),
                    ),
                  ),
                  trailing: IconButton(
                    tooltip: 'Unfollow',
                    icon: const Icon(Icons.favorite),
                    onPressed: () => runAction(context, () async {
                      await context.read<AppState>().api.post('/follow', {
                        'seller_id': store['user_id'],
                        'enabled': false,
                      });
                      await reload();
                    }),
                  ),
                ),
              ),
            ),
        ],
      );
    },
  );
}

class StoreScreen extends StatefulWidget {
  final int sellerId;
  const StoreScreen({super.key, required this.sellerId});
  @override
  State<StoreScreen> createState() => _StoreScreenState();
}

class _StoreScreenState extends State<StoreScreen> {
  int page = 1;
  int? category;
  @override
  Widget build(BuildContext context) {
    final signed = context.watch<AppState>().isLoggedIn;
    final path =
        '/${signed ? 'stores' : 'public/stores'}/${widget.sellerId}?page=$page${category == null ? '' : '&store_category=$category'}';
    return ResourcePage(
      key: ValueKey(path),
      allowGuest: true,
      title: 'Meet your local seller',
      path: path,
      builder: (context, data, reload) {
        final store = data['store'],
            products = List.from(data['products']['data']),
            collections = List.from(data['categories']);
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SheetCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  AppPhoto(store['logo_url'], width: 64, height: 64),
                  const SizedBox(height: 14),
                  Text(
                    '${store['name']}',
                    style: const TextStyle(
                      fontSize: 26,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  Text('${store['description'] ?? ''}'),
                  const SizedBox(height: 14),
                  FilledButton.tonal(
                    onPressed: () async {
                      if (!signed) {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const LoginScreen(),
                          ),
                        );
                        return;
                      }
                      await runAction(context, () async {
                        await context.read<AppState>().api.post('/follow', {
                          'seller_id': widget.sellerId,
                          'enabled': data['following'] != true,
                        });
                        await reload();
                      });
                    },
                    child: Text(
                      data['following'] == true
                          ? 'Following ✓'
                          : 'Follow store',
                    ),
                  ),
                ],
              ),
            ),
            const SectionHeading('From this store'),
            if (collections.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(bottom: 16),
                child: DropdownButtonFormField<int>(
                  initialValue: category,
                  isExpanded: true,
                  decoration: const InputDecoration(
                    labelText: 'Store collection',
                  ),
                  items: [
                    const DropdownMenuItem<int>(
                      value: null,
                      child: Text('All collections'),
                    ),
                    for (final collection in collections)
                      DropdownMenuItem<int>(
                        value: collection['id'],
                        child: Text('${collection['name']}'),
                      ),
                  ],
                  onChanged: (v) => setState(() {
                    category = v;
                    page = 1;
                  }),
                ),
              ),
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                maxCrossAxisExtent: 240,
                mainAxisExtent: 280,
                crossAxisSpacing: 12,
                mainAxisSpacing: 12,
              ),
              itemCount: products.length,
              itemBuilder: (ctx, i) => ProductCard(
                product: products[i],
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) =>
                        ProductDetailScreen(productId: products[i]['id']),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 18),
            Row(
              children: [
                if (page > 1)
                  TextButton(
                    onPressed: () => setState(() => page--),
                    child: const Text('Previous'),
                  ),
                const Spacer(),
                Text('Page $page'),
                const Spacer(),
                if (page < (data['products']['last_page'] as int))
                  TextButton(
                    onPressed: () => setState(() => page++),
                    child: const Text('Next'),
                  ),
              ],
            ),
          ],
        );
      },
    );
  }
}

class ReturnsScreen extends StatelessWidget {
  const ReturnsScreen({super.key});
  @override
  Widget build(BuildContext context) => ResourcePage(
    title: 'Returns & resolutions',
    path: '/returns',
    builder: (context, data, reload) => Column(
      children: [
        if ((data as List).isEmpty)
          const EmptyState(
            icon: Icons.assignment_return_outlined,
            title: 'No returns to manage',
            subtitle: 'You can request a return from a delivered order.',
          ),
        ...data.map(
          (r) => Padding(
            padding: const EdgeInsets.only(bottom: 14),
            child: SheetCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          'Order #${r['order_id']}',
                          style: const TextStyle(
                            fontWeight: FontWeight.w900,
                            fontSize: 18,
                          ),
                        ),
                      ),
                      StatusBadge(status: '${r['status']}'),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Text(
                    '${r['reason']}',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  Text('${r['description']}'),
                  if (r['response'] != null)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      child: Text('Seller response: ${r['response']}'),
                    ),
                  Wrap(
                    spacing: 8,
                    children: (r['photo_urls'] as List? ?? [])
                        .map(
                          (p) => AppPhoto(
                            '$p',
                            width: 90,
                            height: 90,
                            private: true,
                          ),
                        )
                        .toList(),
                  ),
                  if (r['status'] == 'rejected')
                    TextButton(
                      onPressed: () => runAction(context, () async {
                        await context.read<AppState>().api.post(
                          '/returns/escalate',
                          {'id': r['id']},
                        );
                        await reload();
                      }, success: 'Dispute sent for review.'),
                      child: const Text('Ask LokaShop to review'),
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

class ReturnEditor extends StatefulWidget {
  final int orderId;
  const ReturnEditor({super.key, required this.orderId});
  @override
  State<ReturnEditor> createState() => _ReturnEditorState();
}

class _ReturnEditorState extends State<ReturnEditor> {
  String reason = 'Damaged';
  final description = TextEditingController();
  List<File> photos = [];
  bool busy = false;
  final key = requestKey();
  @override
  void dispose() {
    description.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (description.text.trim().length < 10 || photos.isEmpty) {
      AppSnack.show(
        context,
        'Describe the issue in at least 10 characters and add a photo.',
        error: true,
      );
      return;
    }
    setState(() => busy = true);
    try {
      await context.read<AppState>().api.postMultipart('/returns', {
        'order_id': '${widget.orderId}',
        'reason': reason,
        'description': description.text.trim(),
        '_request_key': key,
      }, files: photos);
      if (mounted) {
        AppSnack.show(context, 'Return requested.');
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) AppSnack.show(context, '$e', error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Let’s put it right')),
    body: PageBody(
      child: ListView(
        padding: const EdgeInsets.all(22),
        children: [
          SheetCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Returns are available within 7 days of delivery, before confirming receipt.',
                ),
                const SizedBox(height: 20),
                DropdownButtonFormField<String>(
                  initialValue: reason,
                  decoration: const InputDecoration(
                    labelText: 'What went wrong?',
                  ),
                  items: ['Damaged', 'Wrong item', 'Not as described']
                      .map((s) => DropdownMenuItem(value: s, child: Text(s)))
                      .toList(),
                  onChanged: (s) => reason = s!,
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: description,
                  maxLines: 4,
                  maxLength: 500,
                  decoration: const InputDecoration(
                    labelText: 'Tell us what happened',
                  ),
                ),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: () async {
                    final picks = await ImagePicker().pickMultiImage(
                      imageQuality: 80,
                    );
                    if (mounted) {
                      setState(
                        () => photos = picks
                            .take(5)
                            .map((p) => File(p.path))
                            .toList(),
                      );
                    }
                  },
                  icon: const Icon(Icons.add_photo_alternate_outlined),
                  label: Text('Add evidence photos (${photos.length}/5)'),
                ),
                Wrap(
                  spacing: 8,
                  children: photos
                      .map(
                        (p) => ClipRRect(
                          borderRadius: BorderRadius.circular(12),
                          child: Image.file(
                            p,
                            width: 85,
                            height: 85,
                            fit: BoxFit.cover,
                          ),
                        ),
                      )
                      .toList(),
                ),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: busy ? null : save,
                  child: Text(busy ? 'Sending…' : 'Request return'),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class ReviewsScreen extends StatelessWidget {
  const ReviewsScreen({super.key});
  @override
  Widget build(BuildContext context) => ResourcePage(
    title: 'Your product stories',
    path: '/reviews',
    builder: (context, data, reload) => Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SectionHeading('Your reviews'),
        if ((data['reviews'] as List).isEmpty)
          const Text('Your reviews help the next local shopper.'),
        ...(data['reviews'] as List).map(
          (r) => Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: SheetCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${r['product_name']}',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  Text(
                    '★' * int.parse('${r['stars']}'),
                    style: const TextStyle(
                      color: Color(0xFFAD802B),
                      fontSize: 20,
                    ),
                  ),
                  Text('${r['comment'] ?? ''}'),
                  if (r['seller_response'] != null)
                    Text('Seller: ${r['seller_response']}'),
                  if ((int.tryParse('${r['edit_count']}') ?? 0) < 1)
                    TextButton(
                      onPressed: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => ReviewEditor(
                            item: {
                              'id': r['order_item_id'],
                              'name': r['product_name'],
                            },
                            review: r,
                          ),
                        ),
                      ).then((_) => reload()),
                      child: const Text('Edit review'),
                    ),
                ],
              ),
            ),
          ),
        ),
        const SectionHeading('Ready to review'),
        ...(data['eligible_items'] as List).map(
          (i) => ListTile(
            title: Text('${i['name']}'),
            subtitle: Text('Order #${i['order_id']}'),
            trailing: const Icon(Icons.rate_review_outlined),
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => ReviewEditor(item: i)),
            ).then((_) => reload()),
          ),
        ),
      ],
    ),
  );
}

class ReviewEditor extends StatefulWidget {
  final Map item;
  final Map? review;
  const ReviewEditor({super.key, required this.item, this.review});
  @override
  State<ReviewEditor> createState() => _ReviewEditorState();
}

class _ReviewEditorState extends State<ReviewEditor> {
  late int stars = int.tryParse('${widget.review?['stars']}') ?? 5;
  late final comment = TextEditingController(text: widget.review?['comment']);
  List<File> photos = [];
  bool busy = false;
  @override
  void dispose() {
    comment.dispose();
    super.dispose();
  }

  Future<void> save() async {
    setState(() => busy = true);
    try {
      await context.read<AppState>().api.postMultipart('/reviews', {
        'order_item_id': '${widget.item['id']}',
        'stars': '$stars',
        'comment': comment.text.trim(),
      }, files: photos);
      if (mounted) {
        AppSnack.show(context, 'Your review is saved.');
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) AppSnack.show(context, '$e', error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Tell your product story')),
    body: PageBody(
      child: ListView(
        padding: const EdgeInsets.all(22),
        children: [
          SheetCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${widget.item['name']}',
                  style: const TextStyle(
                    fontWeight: FontWeight.w900,
                    fontSize: 21,
                  ),
                ),
                const SizedBox(height: 18),
                Wrap(
                  children: List.generate(
                    5,
                    (i) => IconButton(
                      onPressed: () => setState(() => stars = i + 1),
                      icon: Icon(
                        i < stars
                            ? Icons.star_rounded
                            : Icons.star_outline_rounded,
                        size: 32,
                        color: const Color(0xFFAD802B),
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: comment,
                  maxLines: 4,
                  maxLength: 500,
                  decoration: const InputDecoration(
                    labelText: 'How was your experience?',
                  ),
                ),
                OutlinedButton.icon(
                  onPressed: () async {
                    final p = await ImagePicker().pickMultiImage(
                      imageQuality: 80,
                    );
                    if (mounted) {
                      setState(
                        () => photos = p
                            .take(5)
                            .map((p) => File(p.path))
                            .toList(),
                      );
                    }
                  },
                  icon: const Icon(Icons.photo_outlined),
                  label: Text('Photos (${photos.length}/5)'),
                ),
                Wrap(
                  spacing: 8,
                  children: photos
                      .map(
                        (p) => Image.file(
                          p,
                          width: 80,
                          height: 80,
                          fit: BoxFit.cover,
                        ),
                      )
                      .toList(),
                ),
                const SizedBox(height: 16),
                const Text(
                  'Reviews are available for 30 days after completion. Each review may be edited once.',
                  style: TextStyle(fontSize: 12, color: AppColors.muted),
                ),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: busy ? null : save,
                  child: Text(busy ? 'Saving…' : 'Save review'),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class SecurityScreen extends StatefulWidget {
  const SecurityScreen({super.key});
  @override
  State<SecurityScreen> createState() => _SecurityScreenState();
}

class _SecurityScreenState extends State<SecurityScreen> {
  final current = TextEditingController(),
      password = TextEditingController(),
      confirmation = TextEditingController(),
      email = TextEditingController(),
      phone = TextEditingController(),
      otp = TextEditingController();
  String? challenge;
  bool busy = false;
  @override
  void dispose() {
    for (final c in [current, password, confirmation, email, phone, otp]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> submit(String mode) async {
    setState(() => busy = true);
    try {
      final app = context.read<AppState>();
      if (mode == 'password') {
        await app.api.post('/account/password', {
          'current_password': current.text,
          'password': password.text,
          'password_confirmation': confirmation.text,
        });
      } else if (mode == 'request') {
        final r = await app.api.post('/account/contact/request', {
          'password': current.text,
          if (email.text.trim().isNotEmpty) 'email': email.text.trim(),
          if (phone.text.trim().isNotEmpty) 'phone': phone.text.trim(),
        });
        if (!mounted) return;
        setState(() => challenge = r['challenge']);
        AppSnack.show(context, 'Verification code sent to your email.');
        return;
      } else {
        await app.api.post('/account/contact/verify', {
          'challenge': challenge,
          'otp': otp.text.trim(),
        });
      }
      await app.logout();
      if (mounted) {
        Navigator.pushAndRemoveUntil(
          context,
          MaterialPageRoute(builder: (_) => const LoginScreen()),
          (_) => false,
        );
      }
    } catch (e) {
      if (mounted) AppSnack.show(context, '$e', error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Keep your account secure')),
    body: PageBody(
      child: ListView(
        padding: const EdgeInsets.all(22),
        children: [
          const Text(
            'Account changes require your current password. You will sign in again after a successful change.',
          ),
          const SizedBox(height: 16),
          TextField(
            controller: current,
            obscureText: true,
            decoration: const InputDecoration(labelText: 'Current password'),
          ),
          const SectionHeading('A fresh password'),
          SheetCard(
            child: Column(
              children: [
                TextField(
                  controller: password,
                  obscureText: true,
                  decoration: const InputDecoration(labelText: 'New password'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: confirmation,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'Confirm new password',
                  ),
                ),
                const SizedBox(height: 14),
                FilledButton(
                  onPressed: busy ? null : () => submit('password'),
                  child: const Text('Change password'),
                ),
              ],
            ),
          ),
          const SectionHeading('Email & phone verification'),
          SheetCard(
            child: Column(
              children: [
                TextField(
                  controller: email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(
                    labelText: 'New email (optional)',
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: phone,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(
                    labelText: 'New phone (optional)',
                  ),
                ),
                const SizedBox(height: 14),
                OutlinedButton(
                  onPressed: busy ? null : () => submit('request'),
                  child: const Text('Send verification code'),
                ),
                if (challenge != null) ...[
                  const SizedBox(height: 14),
                  TextField(
                    controller: otp,
                    keyboardType: TextInputType.number,
                    maxLength: 6,
                    decoration: const InputDecoration(
                      labelText: 'Email verification code',
                    ),
                  ),
                  FilledButton(
                    onPressed: busy ? null : () => submit('verify'),
                    child: const Text('Verify contact change'),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class AppealScreen extends StatelessWidget {
  const AppealScreen({super.key});
  @override
  Widget build(BuildContext context) => ResourcePage(
    title: 'Account review',
    path: '/appeal',
    builder: (context, data, reload) => Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SheetCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Your account is suspended',
                style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900),
              ),
              Text(
                '${data['reason'] ?? 'Please contact LokaShop for details.'}',
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () async {
                  final reason = await askText(
                    context,
                    'Request an account review',
                    hint: 'Explain your appeal in at least 20 characters.',
                    minLength: 20,
                  );
                  if (reason != null && context.mounted) {
                    await runAction(context, () async {
                      await context.read<AppState>().api.post('/appeal', {
                        'message': reason,
                      });
                      await reload();
                    });
                  }
                },
                child: const Text('Submit appeal'),
              ),
              TextButton(
                onPressed: () async {
                  await context.read<AppState>().logout();
                  if (context.mounted) {
                    Navigator.pushAndRemoveUntil(
                      context,
                      MaterialPageRoute(builder: (_) => const LoginScreen()),
                      (_) => false,
                    );
                  }
                },
                child: const Text('Sign out'),
              ),
            ],
          ),
        ),
        ...(data['appeals'] as List? ?? []).map(
          (a) => ListTile(
            title: Text('${a['message']}'),
            subtitle: Text('${a['status']} · ${a['resolution'] ?? ''}'),
          ),
        ),
      ],
    ),
  );
}
