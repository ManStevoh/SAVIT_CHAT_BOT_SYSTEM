import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/shell/shell_badges.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_skeleton.dart';
import '../../shared/widgets/app_state_views.dart';
import '../../shared/widgets/customer_avatar.dart';
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
  final _search = TextEditingController();
  Timer? _poll;
  Timer? _searchDebounce;

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
    _searchDebounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  Future<List<ChatSummary>> _load() {
    return context.read<ChatRepository>().listChats(
          search: _search.text.trim().isEmpty ? null : _search.text.trim(),
        );
  }

  void _publishUnreadBadge(List<ChatSummary> chats) {
    final unread = chats.fold<int>(0, (sum, c) => sum + c.unreadCount);
    context.read<ShellBadges>().setUnreadChats(unread);
  }

  Future<void> _reload() async {
    setState(() => _future = _load());
    final chats = await _future;
    if (mounted && chats != null) _publishUnreadBadge(chats);
  }

  Future<void> _silentReload() async {
    if (!mounted || _future == null) return;
    if (ActiveShellBranch.maybeOf(context) != 1) return;
    try {
      final chats = await _load();
      if (mounted) {
        _publishUnreadBadge(chats);
        setState(() => _future = Future.value(chats));
      }
    } catch (_) {
      // Keep last good snapshot during background poll failures.
    }
  }

  void _onSearchChanged(String _) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 350), () {
      if (mounted) _reload();
    });
  }

  List<ChatSummary> _applyFilter(List<ChatSummary> chats) {
    if (_filter == _ChatFilter.unread) {
      return chats.where((c) => c.unreadCount > 0).toList();
    }
    return chats;
  }

  void _openChat(ChatSummary item) {
    context.go(
      '/chats/${item.id}',
      extra: {
        'name': item.customerName,
        'phone': item.customerPhone,
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Chats')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: TextField(
              controller: _search,
              onChanged: _onSearchChanged,
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                hintText: 'Search name or phone…',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _search.text.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _search.clear();
                          _reload();
                          setState(() {});
                        },
                      ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: SegmentedButton<_ChatFilter>(
              segments: const [
                ButtonSegment(
                  value: _ChatFilter.all,
                  label: Text('All'),
                  icon: Icon(Icons.forum_outlined),
                ),
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
                ? const ChatListSkeleton()
                : RefreshIndicator(
                    onRefresh: _reload,
                    child: FutureBuilder<List<ChatSummary>>(
                      future: _future,
                      builder: (context, snapshot) {
                        if (snapshot.connectionState == ConnectionState.waiting &&
                            !snapshot.hasData) {
                          return const ChatListSkeleton();
                        }
                        if (snapshot.hasError && !snapshot.hasData) {
                          final message = snapshot.error is ApiException
                              ? (snapshot.error as ApiException).message
                              : snapshot.error.toString();
                          return AppErrorState(message: message, onRetry: _reload);
                        }

                        final chats = _applyFilter(snapshot.data ?? []);
                        if (chats.isEmpty) {
                          final searching = _search.text.trim().isNotEmpty;
                          return AppEmptyState(
                            icon: Icons.chat_bubble_outline,
                            title: searching
                                ? 'No matches'
                                : (_filter == _ChatFilter.unread
                                    ? 'No unread chats'
                                    : 'No chats yet'),
                            subtitle: searching
                                ? 'Try another name or phone number.'
                                : (_filter == _ChatFilter.unread
                                    ? "You're all caught up."
                                    : 'Add a contact to start a conversation.'),
                            actionLabel: searching || _filter == _ChatFilter.unread
                                ? null
                                : 'Add contact',
                            onAction: searching || _filter == _ChatFilter.unread
                                ? null
                                : () => context.go('/contacts/add'),
                          );
                        }

                        return ListView.separated(
                          itemCount: chats.length,
                          separatorBuilder: (_, __) => const Divider(height: 1),
                          itemBuilder: (context, index) {
                            final item = chats[index];
                            final unread = item.unreadCount > 0;
                            return ListTile(
                              contentPadding: const EdgeInsets.symmetric(
                                horizontal: 16,
                                vertical: 4,
                              ),
                              leading: CustomerAvatar(name: item.customerName),
                              title: Text(
                                item.customerName,
                                style: TextStyle(
                                  fontWeight:
                                      unread ? FontWeight.w800 : FontWeight.w600,
                                ),
                              ),
                              subtitle: Text(
                                item.lastMessage.isEmpty
                                    ? item.customerPhone
                                    : item.lastMessage,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontWeight:
                                      unread ? FontWeight.w600 : FontWeight.w400,
                                  color: unread
                                      ? AppColors.primaryDark
                                      : AppColors.textMuted,
                                ),
                              ),
                              trailing: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Text(
                                    item.lastMessageTime,
                                    style: TextStyle(
                                      color: unread
                                          ? AppColors.primary
                                          : AppColors.textMuted,
                                      fontSize: 12,
                                      fontWeight: unread
                                          ? FontWeight.w700
                                          : FontWeight.w400,
                                    ),
                                  ),
                                  if (unread) ...[
                                    const SizedBox(height: 6),
                                    Container(
                                      constraints: const BoxConstraints(
                                        minWidth: 20,
                                        minHeight: 20,
                                      ),
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 6,
                                      ),
                                      decoration: BoxDecoration(
                                        color: AppColors.primary,
                                        borderRadius: BorderRadius.circular(999),
                                      ),
                                      alignment: Alignment.center,
                                      child: Text(
                                        item.unreadCount > 99
                                            ? '99+'
                                            : '${item.unreadCount}',
                                        style: const TextStyle(
                                          fontSize: 11,
                                          color: Colors.white,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                              onTap: () => _openChat(item),
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
