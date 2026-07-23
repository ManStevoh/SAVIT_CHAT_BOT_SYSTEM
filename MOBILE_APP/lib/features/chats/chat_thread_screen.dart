import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_state_views.dart';
import '../../shared/widgets/customer_avatar.dart';
import '../shell/active_shell_branch.dart';
import 'chat_models.dart';
import 'chat_repository.dart';

class ChatThreadScreen extends StatefulWidget {
  const ChatThreadScreen({
    super.key,
    required this.chatId,
    this.customerName,
    this.customerPhone,
  });

  final String chatId;
  final String? customerName;
  final String? customerPhone;

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
  String? _customerName;
  String? _customerPhone;

  @override
  void initState() {
    super.initState();
    _customerName = widget.customerName;
    _customerPhone = widget.customerPhone;
    _future = context.read<ChatRepository>().listMessages(widget.chatId);
    _poll = Timer.periodic(const Duration(seconds: 8), (_) => _silentReload());
    if (_customerName == null || _customerName!.isEmpty) {
      _resolveCustomer();
    }
  }

  @override
  void dispose() {
    _poll?.cancel();
    _composer.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _resolveCustomer() async {
    try {
      final chats = await context.read<ChatRepository>().listChats();
      ChatSummary? match;
      for (final chat in chats) {
        if (chat.id == widget.chatId) {
          match = chat;
          break;
        }
      }
      if (!mounted || match == null) return;
      setState(() {
        _customerName = match!.customerName;
        _customerPhone = match.customerPhone;
      });
    } catch (_) {}
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
    final title = (_customerName != null && _customerName!.trim().isNotEmpty)
        ? _customerName!.trim()
        : 'Chat';
    final phone = _customerPhone?.trim() ?? '';

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: Row(
          children: [
            CustomerAvatar(name: title, size: 36),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                  ),
                  if (phone.isNotEmpty)
                    Text(
                      phone,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.white.withOpacity(0.85),
                        fontWeight: FontWeight.w400,
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
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
                    return AppErrorState(message: message, onRetry: _reload);
                  }

                  final messages = snapshot.data ?? [];
                  if (messages.isEmpty) {
                    return const AppEmptyState(
                      icon: Icons.forum_outlined,
                      title: 'No messages yet',
                      subtitle: 'Send the first reply to start the conversation.',
                    );
                  }

                  if (!_didInitialScroll) {
                    _didInitialScroll = true;
                    _lastCount = messages.length;
                    _scrollToBottom(animate: false);
                  }

                  return ListView.builder(
                    controller: _scrollController,
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                    itemCount: messages.length,
                    itemBuilder: (context, index) {
                      final message = messages[index];
                      final showTimestamp = index == messages.length - 1 ||
                          messages[index].sender != messages[index + 1].sender ||
                          messages[index].timestamp !=
                              messages[index + 1].timestamp;
                      return _Bubble(
                        text: message.content.isEmpty
                            ? '[Attachment]'
                            : message.content,
                        incoming: message.isIncoming,
                        timestamp: showTimestamp ? message.timestamp : '',
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
            child: Material(
              color: Colors.white,
              elevation: 6,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
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
                          isDense: true,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    IconButton.filled(
                      onPressed: _sending ? null : _send,
                      style: IconButton.styleFrom(
                        backgroundColor: AppColors.primary,
                      ),
                      icon: _sending
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Icon(Icons.send, color: Colors.white),
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
    final radius = BorderRadius.only(
      topLeft: const Radius.circular(16),
      topRight: const Radius.circular(16),
      bottomLeft: Radius.circular(incoming ? 4 : 16),
      bottomRight: Radius.circular(incoming ? 16 : 4),
    );

    return Align(
      alignment: align,
      child: Container(
        margin: EdgeInsets.only(
          bottom: timestamp.isEmpty ? 4 : 10,
          left: incoming ? 0 : 48,
          right: incoming ? 48 : 0,
        ),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        constraints: BoxConstraints(
          maxWidth: MediaQuery.sizeOf(context).width * 0.78,
        ),
        decoration: BoxDecoration(
          color: color,
          borderRadius: radius,
          border: failed ? Border.all(color: Colors.red.shade200) : null,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(text, style: const TextStyle(height: 1.35)),
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
