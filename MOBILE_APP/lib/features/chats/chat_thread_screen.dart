import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/shell/shell_badges.dart';
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
    _future.then((_) {
      if (mounted) _syncUnreadAfterOpen();
    });
    _poll = Timer.periodic(const Duration(seconds: 8), (_) => _silentReload());
    if (_customerName == null || _customerName!.isEmpty) {
      _resolveCustomer();
    }
  }

  Future<void> _syncUnreadAfterOpen() async {
    try {
      final chats = await context.read<ChatRepository>().listChats();
      if (!mounted) return;
      final unread = chats.fold<int>(0, (sum, c) => sum + c.unreadCount);
      context.read<ShellBadges>().setUnreadChats(unread);
    } catch (_) {}
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
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
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
        const SnackBar(
          content: Text('Handed back to bot. Auto-reply will resume.'),
        ),
      );
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
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
      backgroundColor: AppColors.chatCanvas,
      appBar: AppBar(
        backgroundColor: AppColors.surface,
        titleSpacing: 0,
        title: Row(
          children: [
            CustomerAvatar(name: title, size: 40),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: GoogleFonts.manrope(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                      color: AppColors.ink,
                    ),
                  ),
                  if (phone.isNotEmpty)
                    Text(
                      phone,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.manrope(
                        fontSize: 12,
                        color: AppColors.textMuted,
                        fontWeight: FontWeight.w500,
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
            child: DecoratedBox(
              decoration: const BoxDecoration(
                color: AppColors.chatCanvas,
              ),
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
                        subtitle:
                            'Send the first reply to start the conversation.',
                      );
                    }

                    if (!_didInitialScroll) {
                      _didInitialScroll = true;
                      _lastCount = messages.length;
                      _scrollToBottom(animate: false);
                    }

                    return ListView.builder(
                      controller: _scrollController,
                      padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
                      itemCount: messages.length,
                      itemBuilder: (context, index) {
                        final message = messages[index];
                        final showTimestamp = index == messages.length - 1 ||
                            messages[index].sender !=
                                messages[index + 1].sender ||
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
          ),
          SafeArea(
            top: false,
            child: Container(
              decoration: BoxDecoration(
                color: AppColors.surface,
                boxShadow: [
                  BoxShadow(
                    color: AppColors.ink.withOpacity(0.06),
                    blurRadius: 16,
                    offset: const Offset(0, -4),
                  ),
                ],
              ),
              padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _composer,
                      enabled: !_sending,
                      textInputAction: TextInputAction.send,
                      onSubmitted: (_) => _send(),
                      decoration: InputDecoration(
                        hintText: 'Type a reply…',
                        isDense: true,
                        filled: true,
                        fillColor: AppColors.canvas,
                        contentPadding: const EdgeInsets.symmetric(
                          horizontal: 18,
                          vertical: 14,
                        ),
                        border: OutlineInputBorder(
                          borderRadius:
                              BorderRadius.circular(AppRadii.pill),
                          borderSide: BorderSide.none,
                        ),
                        enabledBorder: OutlineInputBorder(
                          borderRadius:
                              BorderRadius.circular(AppRadii.pill),
                          borderSide: BorderSide.none,
                        ),
                        focusedBorder: OutlineInputBorder(
                          borderRadius:
                              BorderRadius.circular(AppRadii.pill),
                          borderSide: const BorderSide(
                            color: AppColors.primary,
                            width: 1.4,
                          ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Material(
                    color: AppColors.primary,
                    shape: const CircleBorder(),
                    elevation: 2,
                    shadowColor: AppColors.primary.withOpacity(0.4),
                    child: InkWell(
                      customBorder: const CircleBorder(),
                      onTap: _sending ? null : _send,
                      child: SizedBox(
                        width: 48,
                        height: 48,
                        child: Center(
                          child: _sending
                              ? const SizedBox(
                                  width: 18,
                                  height: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: Colors.white,
                                  ),
                                )
                              : const Icon(
                                  Icons.send_rounded,
                                  color: Colors.white,
                                  size: 22,
                                ),
                        ),
                      ),
                    ),
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
    final radius = BorderRadius.only(
      topLeft: const Radius.circular(20),
      topRight: const Radius.circular(20),
      bottomLeft: Radius.circular(incoming ? 6 : 20),
      bottomRight: Radius.circular(incoming ? 20 : 6),
    );

    return Align(
      alignment: align,
      child: Container(
        margin: EdgeInsets.only(
          bottom: timestamp.isEmpty ? 5 : 12,
          left: incoming ? 0 : 48,
          right: incoming ? 48 : 0,
        ),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
        constraints: BoxConstraints(
          maxWidth: MediaQuery.sizeOf(context).width * 0.78,
        ),
        decoration: BoxDecoration(
          color: color,
          borderRadius: radius,
          border: failed
              ? Border.all(color: Colors.red.shade200)
              : (incoming
                  ? Border.all(color: AppColors.border.withOpacity(0.8))
                  : null),
          boxShadow: [
            BoxShadow(
              color: AppColors.ink.withOpacity(0.04),
              blurRadius: 10,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              text,
              style: GoogleFonts.manrope(
                height: 1.4,
                fontSize: 15,
                color: AppColors.ink,
              ),
            ),
            if (failed) ...[
              const SizedBox(height: 4),
              Text(
                'Not delivered via WhatsApp',
                style: GoogleFonts.manrope(
                  fontSize: 11,
                  color: Colors.red.shade700,
                ),
              ),
            ],
            if (timestamp.isNotEmpty) ...[
              const SizedBox(height: 5),
              Text(
                timestamp,
                style: GoogleFonts.manrope(
                  fontSize: 11,
                  color: AppColors.textMuted,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
