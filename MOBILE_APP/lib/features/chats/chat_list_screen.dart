import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../shell/active_shell_branch.dart';
import 'chat_models.dart';
import 'chat_repository.dart';

enum _ChatFilter { all, unread }

class ChatListScreen extends StatefulWidget {
  const ChatListScreen({super.key});

  @override
  State<ChatListScreen> createState() => _ChatListScreenState();
}

class _ChatListScreenState extends State<ChatListScreen> {
  Future<List<ChatSummary>>? _future;
  _ChatFilter _filter = _ChatFilter.all;
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      setState(() => _future = _load());
      _poll = Timer.periodic(const Duration(seconds: 12), (_) => _silentReload());
    });
  }

  @override
  void dispose() {
    _poll?.cancel();
    super.dispose();
  }

  Future<List<ChatSummary>> _load() {
    return context.read<ChatRepository>().listChats();
  }

  Future<void> _reload() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _silentReload() async {
    if (!mounted || _future == null) return;
    if (ActiveShellBranch.maybeOf(context) != 1) return;
    try {
      final chats = await _load();
      if (mounted) setState(() => _future = Future.value(chats));
    } catch (_) {
      // Keep last good snapshot during background poll failures.
    }
  }

  List<ChatSummary> _applyFilter(List<ChatSummary> chats) {
    if (_filter == _ChatFilter.unread) {
      return chats.where((c) => c.unreadCount > 0).toList();
    }
    return chats;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Chats')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: SegmentedButton<_ChatFilter>(
              segments: const [
                ButtonSegment(value: _ChatFilter.all, label: Text('All'), icon: Icon(Icons.forum_outlined)),
                ButtonSegment(
                  value: _ChatFilter.unread,
                  label: Text('Unread'),
                  icon: Icon(Icons.mark_email_unread_outlined),
                ),
              ],
              selected: {_filter},
              onSelectionChanged: (value) {
                setState(() => _filter = value.first);
              },
            ),
          ),
          Expanded(
            child: _future == null
                ? const Center(child: CircularProgressIndicator())
                : RefreshIndicator(
                    onRefresh: _reload,
                    child: FutureBuilder<List<ChatSummary>>(
                      future: _future,
                      builder: (context, snapshot) {
                        if (snapshot.connectionState == ConnectionState.waiting &&
                            !snapshot.hasData) {
                          return const Center(child: CircularProgressIndicator());
                        }
                        if (snapshot.hasError && !snapshot.hasData) {
                          final message = snapshot.error is ApiException
                              ? (snapshot.error as ApiException).message
                              : snapshot.error.toString();
                          return ListView(
                            physics: const AlwaysScrollableScrollPhysics(),
                            children: [
                              const SizedBox(height: 120),
                              Icon(Icons.error_outline, color: Colors.red.shade300, size: 40),
                              const SizedBox(height: 12),
                              Padding(
                                padding: const EdgeInsets.symmetric(horizontal: 24),
                                child: Text(message, textAlign: TextAlign.center),
                              ),
                            ],
                          );
                        }

                        final chats = _applyFilter(snapshot.data ?? []);
                        if (chats.isEmpty) {
                          return ListView(
                            physics: const AlwaysScrollableScrollPhysics(),
                            children: [
                              const SizedBox(height: 120),
                              const Icon(Icons.chat_bubble_outline, color: AppColors.primary, size: 40),
                              const SizedBox(height: 12),
                              Text(
                                _filter == _ChatFilter.unread ? 'No unread chats' : 'No chats yet',
                                textAlign: TextAlign.center,
                              ),
                              const SizedBox(height: 4),
                              Text(
                                _filter == _ChatFilter.unread
                                    ? 'You\'re all caught up.'
                                    : 'Add a contact to start a conversation.',
                                textAlign: TextAlign.center,
                                style: const TextStyle(color: AppColors.textMuted),
                              ),
                            ],
                          );
                        }

                        return ListView.separated(
                          itemCount: chats.length,
                          separatorBuilder: (_, __) => const Divider(height: 1),
                          itemBuilder: (context, index) {
                            final item = chats[index];
                            final initial = item.customerName.isNotEmpty
                                ? item.customerName[0].toUpperCase()
                                : '?';
                            return ListTile(
                              leading: CircleAvatar(
                                backgroundColor: AppColors.bubbleIncoming,
                                child: Text(initial),
                              ),
                              title: Text(
                                item.customerName,
                                style: const TextStyle(fontWeight: FontWeight.w600),
                              ),
                              subtitle: Text(
                                item.lastMessage.isEmpty ? item.customerPhone : item.lastMessage,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                              trailing: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Text(
                                    item.lastMessageTime,
                                    style: const TextStyle(
                                      color: AppColors.textMuted,
                                      fontSize: 12,
                                    ),
                                  ),
                                  if (item.unreadCount > 0) ...[
                                    const SizedBox(height: 4),
                                    CircleAvatar(
                                      radius: 10,
                                      backgroundColor: AppColors.primary,
                                      child: Text(
                                        '${item.unreadCount}',
                                        style: const TextStyle(fontSize: 11, color: Colors.white),
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                              onTap: () => context.go('/chats/${item.id}'),
                            );
                          },
                        );
                      },
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}
