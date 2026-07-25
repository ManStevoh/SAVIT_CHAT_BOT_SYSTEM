import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/shell/shell_badges.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_state_views.dart';
import '../../shared/widgets/customer_avatar.dart';
import 'chat_models.dart';
import 'chat_repository.dart';

class ChatThreadScreen extends StatefulWidget {
  const ChatThreadScreen({
    super.key,
    required this.chatId,
    this.customerName,
    this.customerPhone,
    this.isAgentHandling = false,
  });

  final String chatId;
  final String? customerName;
  final String? customerPhone;
  final bool isAgentHandling;

  @override
  State<ChatThreadScreen> createState() => _ChatThreadScreenState();
}

class _ChatThreadScreenState extends State<ChatThreadScreen>
    with WidgetsBindingObserver {
  final _composer = TextEditingController();
  final _scrollController = ScrollController();
  late Future<List<ChatMessage>> _future;
  bool _sending = false;
  Timer? _poll;
  int _lastCount = 0;
  String? _lastFingerprint;
  bool _didInitialScroll = false;
  String? _customerName;
  String? _customerPhone;
  bool _isAgentHandling = false;
  ChatMessage? _replyingTo;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _customerName = widget.customerName;
    _customerPhone = widget.customerPhone;
    _isAgentHandling = widget.isAgentHandling;
    _future = context.read<ChatRepository>().listMessages(widget.chatId);
    _future.then((_) {
      if (mounted) _syncUnreadAfterOpen();
    });
    _poll = Timer.periodic(const Duration(seconds: 4), (_) => _silentReload());
    _resolveCustomer();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _silentReload();
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
    WidgetsBinding.instance.removeObserver(this);
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
        _isAgentHandling = match.isAgentHandling;
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
    try {
      final messages =
          await context.read<ChatRepository>().listMessages(widget.chatId);
      if (!mounted) return;
      final fingerprint = messages.isEmpty
          ? 'empty'
          : '${messages.length}:${messages.last.id}:${messages.last.status}:${messages.last.content.hashCode}';
      final changed = fingerprint != _lastFingerprint;
      final grew = messages.length > _lastCount;
      _lastCount = messages.length;
      _lastFingerprint = fingerprint;
      if (changed) {
        setState(() => _future = Future.value(messages));
      }
      if (grew) _scrollToBottom();
    } catch (_) {}
  }

  Future<void> _send() async {
    final text = _composer.text.trim();
    if (text.isEmpty || _sending) return;

    final replyId = _replyingTo?.id;
    setState(() => _sending = true);
    try {
      final result = await context.read<ChatRepository>().sendMessage(
            widget.chatId,
            text,
            replyToMessageId: replyId,
          );
      _composer.clear();
      setState(() {
        _replyingTo = null;
        _isAgentHandling = true;
      });
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
      setState(() => _isAgentHandling = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Handed back to AI. Auto-reply will resume.'),
        ),
      );
      await _reload();
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  void _setReply(ChatMessage message) {
    setState(() => _replyingTo = message);
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
                  Text(
                    _isAgentHandling
                        ? (phone.isNotEmpty
                            ? 'Agent handling · $phone'
                            : 'Agent handling')
                        : (phone.isNotEmpty
                            ? 'AI auto-reply · $phone'
                            : 'AI auto-reply'),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: GoogleFonts.manrope(
                      fontSize: 12,
                      color: _isAgentHandling
                          ? AppColors.accentAmber
                          : AppColors.textMuted,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        actions: [
          if (_isAgentHandling)
            IconButton(
              tooltip: 'Hand back to AI',
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
                          message: message,
                          showTimestamp: showTimestamp,
                          onReply: () => _setReply(message),
                        );
                      },
                    );
                  },
                ),
              ),
            ),
          ),
          if (_replyingTo != null)
            Container(
              width: double.infinity,
              color: AppColors.surface,
              padding: const EdgeInsets.fromLTRB(14, 10, 8, 0),
              child: Row(
                children: [
                  Container(
                    width: 3,
                    height: 36,
                    decoration: BoxDecoration(
                      color: AppColors.primary,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Replying to ${_replyingTo!.isIncoming ? 'customer' : _replyingTo!.sender}',
                          style: GoogleFonts.manrope(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: AppColors.primary,
                          ),
                        ),
                        Text(
                          _replyingTo!.content.isEmpty
                              ? '[Attachment]'
                              : _replyingTo!.content,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: GoogleFonts.manrope(
                            fontSize: 13,
                            color: AppColors.textMuted,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    tooltip: 'Cancel reply',
                    onPressed: () => setState(() => _replyingTo = null),
                    icon: const Icon(Icons.close, size: 18),
                  ),
                ],
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
    required this.message,
    required this.showTimestamp,
    required this.onReply,
  });

  final ChatMessage message;
  final bool showTimestamp;
  final VoidCallback onReply;

  @override
  Widget build(BuildContext context) {
    final incoming = message.isIncoming;
    final failed = message.isFailed;
    final text =
        message.content.isEmpty ? '[Attachment]' : message.content;
    final timestamp = showTimestamp ? message.timestamp : '';
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
      child: GestureDetector(
        onLongPress: onReply,
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
              if (message.replyTo != null) ...[
                Container(
                  width: double.infinity,
                  margin: const EdgeInsets.only(bottom: 8),
                  padding: const EdgeInsets.fromLTRB(10, 8, 10, 8),
                  decoration: BoxDecoration(
                    color: AppColors.ink.withOpacity(0.05),
                    borderRadius: BorderRadius.circular(10),
                    border: Border(
                      left: BorderSide(color: AppColors.primary, width: 3),
                    ),
                  ),
                  child: Text(
                    message.replyTo!.content.isEmpty
                        ? '[Attachment]'
                        : message.replyTo!.content,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: GoogleFonts.manrope(
                      fontSize: 12,
                      color: AppColors.textMuted,
                      height: 1.3,
                    ),
                  ),
                ),
              ],
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
              if (timestamp.isNotEmpty || message.isOutbound) ...[
                const SizedBox(height: 5),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (timestamp.isNotEmpty)
                      Text(
                        timestamp,
                        style: GoogleFonts.manrope(
                          fontSize: 11,
                          color: AppColors.textMuted,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    if (message.isOutbound) ...[
                      if (timestamp.isNotEmpty) const SizedBox(width: 4),
                      _DeliveryTicks(status: message.status, failed: failed),
                    ],
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _DeliveryTicks extends StatelessWidget {
  const _DeliveryTicks({required this.status, required this.failed});

  final String? status;
  final bool failed;

  @override
  Widget build(BuildContext context) {
    if (failed || status == 'failed') {
      return Icon(Icons.error_outline, size: 14, color: Colors.red.shade600);
    }

    final normalized = (status ?? 'sent').toLowerCase();
    final isRead = normalized == 'read';
    final isDelivered = normalized == 'delivered' || isRead;
    final color = isRead ? const Color(0xFF34B7F1) : AppColors.textMuted;

    return Icon(
      isDelivered ? Icons.done_all : Icons.done,
      size: 15,
      color: color,
    );
  }
}
