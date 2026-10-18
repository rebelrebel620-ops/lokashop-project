import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../config.dart';
import '../services/app_state.dart';
import '../widgets/design.dart';
import '../widgets/common.dart';
import '../theme.dart';

class MessageThreadScreen extends StatefulWidget {
  final int userId;
  final String userName;
  const MessageThreadScreen({
    super.key,
    required this.userId,
    required this.userName,
  });
  @override
  State<MessageThreadScreen> createState() => _MessageThreadScreenState();
}

class _MessageThreadScreenState extends State<MessageThreadScreen>
    with WidgetsBindingObserver {
  List messages = [];
  final body = TextEditingController(), scroll = ScrollController();
  File? photo;
  bool loading = true, sending = false, fetching = false, foreground = true;
  Timer? timer;
  String? error;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    load();
    timer = Timer.periodic(const Duration(seconds: 12), (_) {
      if (foreground) load(quiet: true);
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    foreground = state == AppLifecycleState.resumed;
  }

  Future<void> load({bool quiet = false}) async {
    if (fetching) return;
    fetching = true;
    try {
      final data = await context.read<AppState>().api.get(
        '/messages/${widget.userId}',
      );
      if (mounted) {
        setState(() {
          messages = List.from(data);
          error = null;
        });
      }
      if (!quiet) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (scroll.hasClients) scroll.jumpTo(scroll.position.maxScrollExtent);
        });
      }
    } catch (e) {
      if (mounted && !quiet) setState(() => error = '$e');
    } finally {
      fetching = false;
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> send() async {
    if (body.text.trim().isEmpty) return;
    setState(() => sending = true);
    try {
      await context.read<AppState>().api.postMultipart(
        '/messages',
        {'recipient_id': '${widget.userId}', 'body': body.text.trim()},
        file: photo,
        fileField: 'attachment',
      );
      body.clear();
      if (mounted) setState(() => photo = null);
      await load();
    } catch (e) {
      if (mounted) AppSnack.show(context, '$e', error: true);
    } finally {
      if (mounted) setState(() => sending = false);
    }
  }

  @override
  void dispose() {
    timer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    body.dispose();
    scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(
        widget.userName,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
      ),
    ),
    body: PageBody(
      child: Column(
        children: [
          Expanded(
            child: loading
                ? const Center(child: CircularProgressIndicator())
                : RefreshIndicator(
                    onRefresh: () => load(),
                    child: ListView.builder(
                      controller: scroll,
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.all(20),
                      itemCount: messages.isEmpty ? 1 : messages.length,
                      itemBuilder: (ctx, i) {
                        if (messages.isEmpty) {
                          return Text(
                            error ?? 'Say hello to ${widget.userName}.',
                          );
                        }
                        final m = messages[i],
                            mine = m['recipient_id'] == widget.userId;
                        return Align(
                          alignment: mine
                              ? Alignment.centerRight
                              : Alignment.centerLeft,
                          child: Container(
                            constraints: BoxConstraints(
                              maxWidth: MediaQuery.sizeOf(context).width * .8,
                            ),
                            margin: const EdgeInsets.only(bottom: 14),
                            padding: const EdgeInsets.all(15),
                            decoration: BoxDecoration(
                              color: mine
                                  ? const Color(0xFFE6F0DC)
                                  : Colors.white,
                              borderRadius: BorderRadius.circular(18),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  mine ? 'You' : widget.userName,
                                  style: const TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.muted,
                                  ),
                                ),
                                const SizedBox(height: 5),
                                Text('${m['body']}'),
                                if (m['attachment'] != null)
                                  Padding(
                                    padding: const EdgeInsets.symmetric(
                                      vertical: 10,
                                    ),
                                    child: AppPhoto(
                                      '$apiBaseUrl/messages/${m['id']}/attachment',
                                      width: 200,
                                      height: 170,
                                      fit: BoxFit.contain,
                                      private: true,
                                    ),
                                  ),
                                const SizedBox(height: 7),
                                Text(
                                  '${m['created_at']}',
                                  style: const TextStyle(
                                    fontSize: 9,
                                    color: AppColors.muted,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                  ),
          ),
          SafeArea(
            top: false,
            child: Container(
              padding: const EdgeInsets.all(16),
              color: Colors.white,
              child: Column(
                children: [
                  if (photo != null)
                    Row(
                      children: [
                        Image.file(
                          photo!,
                          width: 45,
                          height: 45,
                          fit: BoxFit.cover,
                        ),
                        const SizedBox(width: 10),
                        const Expanded(child: Text('Photo attached')),
                        IconButton(
                          onPressed: () => setState(() => photo = null),
                          icon: const Icon(Icons.close),
                        ),
                      ],
                    ),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      IconButton(
                        tooltip: 'Attach photo',
                        onPressed: () async {
                          final p = await ImagePicker().pickImage(
                            source: ImageSource.gallery,
                            imageQuality: 80,
                            maxWidth: 1800,
                          );
                          if (p != null && mounted) {
                            setState(() => photo = File(p.path));
                          }
                        },
                        icon: const Icon(Icons.add_photo_alternate_outlined),
                      ),
                      Expanded(
                        child: TextField(
                          controller: body,
                          maxLines: 4,
                          minLines: 1,
                          maxLength: 1000,
                          decoration: const InputDecoration(
                            hintText: 'Write a message…',
                            counterText: '',
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      IconButton.filled(
                        onPressed: sending ? null : send,
                        icon: const Icon(Icons.send_rounded),
                      ),
                    ],
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
