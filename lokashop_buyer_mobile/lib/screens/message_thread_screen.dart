import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';
import '../services/app_state.dart';
import '../theme.dart';
import '../widgets/common.dart';

class MessageThreadScreen extends StatefulWidget {
  final int userId;
  final String userName;
  const MessageThreadScreen({super.key, required this.userId, required this.userName});

  @override
  State<MessageThreadScreen> createState() => _MessageThreadScreenState();
}

class _MessageThreadScreenState extends State<MessageThreadScreen> {
  bool loading = true;
  bool sending = false;
  List messages = [];
  final _controller = TextEditingController();
  final _scrollCtrl = ScrollController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    try {
      final res = await context.read<AppState>().api.get('/messages/${widget.userId}');
      setState(() => messages = List.from(res));
      WidgetsBinding.instance.addPostFrameCallback((_) => _scrollToBottom());
    } catch (_) {
      if (mounted) AppSnack.show(context, 'Could not load messages.', error: true);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  void _scrollToBottom() {
    if (_scrollCtrl.hasClients) {
      _scrollCtrl.animateTo(_scrollCtrl.position.maxScrollExtent, duration: const Duration(milliseconds: 250), curve: Curves.easeOut);
    }
  }

  Future<void> _send() async {
    final body = _controller.text.trim();
    if (body.isEmpty) return;
    setState(() => sending = true);
    try {
      await context.read<AppState>().api.post('/messages', {'recipient_id': widget.userId, 'body': body});
      _controller.clear();
      await _load();
    } on ApiException catch (e) {
      if (mounted) AppSnack.show(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.userName)),
      body: Column(
        children: [
          Expanded(
            child: loading
                ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
                : messages.isEmpty
                    ? const EmptyState(icon: Icons.chat_bubble_outline_rounded, title: 'Say hello', subtitle: 'Start the conversation below.')
                    : ListView.builder(
                        controller: _scrollCtrl,
                        padding: const EdgeInsets.all(16),
                        itemCount: messages.length,
                        itemBuilder: (context, i) {
                          final m = messages[i];
                          // A message is "mine" (sent by the logged-in buyer) when its
                          // recipient is the contact we're chatting with.
                          final mine = m['recipient_id'] == widget.userId;
                          return Align(
                            alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
                            child: Container(
                              margin: const EdgeInsets.only(bottom: 10),
                              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                              constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * .72),
                              decoration: BoxDecoration(
                                color: mine ? AppColors.primary : AppColors.surface,
                                border: mine ? null : Border.all(color: AppColors.line),
                                borderRadius: BorderRadius.only(
                                  topLeft: const Radius.circular(16),
                                  topRight: const Radius.circular(16),
                                  bottomLeft: Radius.circular(mine ? 16 : 4),
                                  bottomRight: Radius.circular(mine ? 4 : 16),
                                ),
                              ),
                              child: Text('${m['body']}', style: TextStyle(color: mine ? Colors.white : AppColors.ink)),
                            ),
                          );
                        },
                      ),
          ),
          SafeArea(
            child: Container(
              padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
              decoration: const BoxDecoration(color: AppColors.surface, border: Border(top: BorderSide(color: AppColors.line))),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _controller,
                      minLines: 1,
                      maxLines: 4,
                      decoration: const InputDecoration(hintText: 'Type a message…', contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 10)),
                      onSubmitted: (_) => _send(),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    onPressed: sending ? null : _send,
                    style: IconButton.styleFrom(backgroundColor: AppColors.primary),
                    icon: sending
                        ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.2))
                        : const Icon(Icons.send_rounded, color: Colors.white, size: 18),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
