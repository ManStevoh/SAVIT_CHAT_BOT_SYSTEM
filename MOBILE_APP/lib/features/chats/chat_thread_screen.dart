import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../shell/active_shell_branch.dart';
import 'chat_models.dart';
import 'chat_repository.dart';

class ChatThreadScreen extends StatefulWidget {
  const ChatThreadScreen({super.key, required this.chatId});

  final String chatId;

  @override
  State<ChatThreadScreen> createState() => _ChatThreadScreenState();
}

class _ChatThreadScreenState extends State<ChatThreadScreen> {
  final _composer = TextEditingController();
  final _scrollController = ScrollController();
  late Future<List<ChatMessage>> _future;
  bool _sending = false;
  Timer? _poll;
  int _lastCount = 0;
  bool _didInitialScroll = false;

  @override
  void initState() {
    super.initState();
    _future = context.read<ChatRepository>().listMessages(widget.chatId);
    _poll = Timer.periodic(const Duration(seconds: 8), (_) => _silentReload());
  }

  @override
  void dispose() {
    _poll?.cancel();
    _composer.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _scrollToBottom({bool animate = true}) {
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted || !_scrollController.hasClients) return;
      final target = _scrollController.position.maxScrollExtent;
      if (animate) {
        await _scrollController.animateTo(
          target,
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
        );
      } else {
        _scrollController.jumpTo(target);
      }
    });
  }

  Future<void> _reload() async {
    setState(() {
      _future = context.read<ChatRepository>().listMessages(widget.chatId);
    });
    final messages = await _future;
    _lastCount = messages.length;
    _scrollToBottom();
  }

  Future<void> _silentReload() async {
    if (!mounted || _sending) return;
    if (ActiveShellBranch.maybeOf(context) != 1) return;
    try {
      final messages =
          await context.read<ChatRepository>().listMessages(widget.chatId);
      if (!mounted) return;
      final grew = messages.length > _lastCount;
      _lastCount = messages.length;
      setState(() => _future = Future.value(messages));
      if (grew) _scrollToBottom();
    } catch (_) {}
  }

  Future<void> _send() async {
    final text = _composer.text.trim();
    if (text.isEmpty || _sending) return;

    setState(() => _sending = true);
    try {
      final result =
          await context.read<ChatRepository>().sendMessage(widget.chatId, text);
      _composer.clear();
      await _reload();
      if (!mounted) return;
      if (!result.whatsappSent) {
        final detail = result.whatsappError?.trim();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              detail != null && detail.isNotEmpty
                  ? 'Saved, but WhatsApp delivery failed: $detail'
                  : (result.message ??
                      'Message saved but not delivered via WhatsApp.'),
            ),
          ),
        );
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _handBack() async {
    try {
      await context.read<ChatRepository>().handBack(widget.chatId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Handed back to bot. Auto-reply will resume.')),
      );
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Chat #${widget.chatId}'),
        actions: [
          IconButton(
            tooltip: 'Hand back to bot',
            onPressed: _handBack,
            icon: const Icon(Icons.smart_toy_outlined),
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            child: RefreshIndicator(
              onRefresh: _reload,
              child: FutureBuilder<List<ChatMessage>>(
                future: _future,
                builder: (context, snapshot) {
                  if (snapshot.connectionState == ConnectionState.waiting) {
                    return const Center(child: CircularProgressIndicator());
                  }
                  if (snapshot.hasError) {
                    final message = snapshot.error is ApiException
                        ? (snapshot.error as ApiException).message
                        : snapshot.error.toString();
                    return ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      children: [
                        const SizedBox(height: 80),
                        Padding(
                          padding: const EdgeInsets.all(24),
                          child: Text(message, textAlign: TextAlign.center),
                        ),
                      ],
                    );
                  }

                  final messages = snapshot.data ?? [];
                  if (messages.isEmpty) {
                    return ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      children: const [
                        SizedBox(height: 80),
                        Text(
                          'No messages yet. Send the first reply.',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: AppColors.textMuted),
                        ),
                      ],
                    );
                  }

                  if (!_didInitialScroll) {
                    _didInitialScroll = true;
                    _lastCount = messages.length;
                    _scrollToBottom(animate: false);
                  }

                  return ListView.builder(
                    controller: _scrollController,
                    padding: const EdgeInsets.all(16),
                    itemCount: messages.length,
                    itemBuilder: (context, index) {
                      final message = messages[index];
                      return _Bubble(
                        text: message.content.isEmpty ? '[Attachment]' : message.content,
                        incoming: message.isIncoming,
                        timestamp: message.timestamp,
                        failed: message.isFailed,
                      );
                    },
                  );
                },
              ),
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _composer,
                      enabled: !_sending,
                      textInputAction: TextInputAction.send,
                      onSubmitted: (_) => _send(),
                      decoration: const InputDecoration(
                        hintText: 'Type a reply…',
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    onPressed: _sending ? null : _send,
                    style: IconButton.styleFrom(backgroundColor: AppColors.primary),
                    icon: _sending
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : const Icon(Icons.send, color: Colors.white),
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

class _Bubble extends StatelessWidget {
  const _Bubble({
    required this.text,
    required this.incoming,
    required this.timestamp,
    this.failed = false,
  });

  final String text;
  final bool incoming;
  final String timestamp;
  final bool failed;

  @override
  Widget build(BuildContext context) {
    final align = incoming ? Alignment.centerLeft : Alignment.centerRight;
    final color = incoming
        ? AppColors.bubbleIncoming
        : (failed ? const Color(0xFFFFE8E6) : AppColors.bubbleOutgoing);

    return Align(
      alignment: align,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.75),
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(16),
          border: failed ? Border.all(color: Colors.red.shade200) : null,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(text),
            if (failed) ...[
              const SizedBox(height: 4),
              Text(
                'Not delivered via WhatsApp',
                style: TextStyle(fontSize: 11, color: Colors.red.shade700),
              ),
            ],
            if (timestamp.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(
                timestamp,
                style: const TextStyle(fontSize: 11, color: AppColors.textMuted),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
