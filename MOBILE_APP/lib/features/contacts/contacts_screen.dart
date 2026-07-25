import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/auth/auth_controller.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/phone_utils.dart';
import '../../shared/widgets/app_state_views.dart';
import '../../shared/widgets/app_surface.dart';
import '../../shared/widgets/customer_avatar.dart';
import '../chats/chat_models.dart';
import '../chats/chat_repository.dart';
import 'customer_repository.dart';

class ContactsScreen extends StatefulWidget {
  const ContactsScreen({super.key});

  @override
  State<ContactsScreen> createState() => _ContactsScreenState();
}

class _ContactsScreenState extends State<ContactsScreen> {
  Future<List<ContactDirectoryItem>>? _future;
  final _search = TextEditingController();
  Timer? _searchDebounce;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final adminOnly =
          context.read<AuthController>().user?.isPlatformAdminOnly ?? false;
      if (adminOnly) return;
      setState(() => _future = _load());
    });
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  Future<List<ContactDirectoryItem>> _load({String? search}) async {
    final chatRepo = context.read<ChatRepository>();
    final customerRepo = context.read<CustomerRepository>();

    List<ChatSummary> chats = const [];
    List<CustomerContact> customers = const [];
    String? partialError;
    try {
      chats = await chatRepo.listChats(search: search);
    } catch (e) {
      partialError = e is ApiException ? e.message : e.toString();
    }
    try {
      customers = await customerRepo.listCustomers(
        search: search,
        limit: 100,
      );
    } catch (e) {
      partialError ??= e is ApiException ? e.message : e.toString();
    }

    if (chats.isEmpty && customers.isEmpty && partialError != null) {
      throw ApiException(partialError);
    }

    final byPhone = <String, ContactDirectoryItem>{};

    for (final chat in chats) {
      final phone = phoneMergeKey(chat.customerPhone);
      if (phone.isEmpty) continue;
      byPhone[phone] = ContactDirectoryItem(
        name: chat.customerName,
        phone: phone,
        chatId: chat.id,
        subtitle: chat.lastMessage.isEmpty ? null : chat.lastMessage,
      );
    }

    for (final customer in customers) {
      final phone = phoneMergeKey(customer.phone);
      if (phone.isEmpty) continue;
      final existing = byPhone[phone];
      if (existing != null) {
        byPhone[phone] = ContactDirectoryItem(
          name: existing.name.isNotEmpty ? existing.name : customer.name,
          phone: phone,
          chatId: existing.chatId,
          totalOrders: customer.totalOrders,
          subtitle: existing.subtitle ??
              (customer.totalOrders > 0
                  ? '${customer.totalOrders} orders · ${customer.totalSpent.toStringAsFixed(0)} spent'
                  : null),
        );
      } else {
        byPhone[phone] = ContactDirectoryItem(
          name: customer.name,
          phone: phone,
          totalOrders: customer.totalOrders,
          subtitle: customer.totalOrders > 0
              ? '${customer.totalOrders} orders · ${customer.totalSpent.toStringAsFixed(0)} spent'
              : 'No open chat yet',
        );
      }
    }

    final items = byPhone.values.toList()
      ..sort((a, b) => a.name.toLowerCase().compareTo(b.name.toLowerCase()));
    return items;
  }

  Future<void> _reload() async {
    setState(() {
      _future = _load(search: _search.text.trim());
    });
    await _future;
  }

  void _onSearchChanged(String _) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 350), () {
      if (!mounted) return;
      _reload();
    });
  }

  Future<void> _openOrStart(ContactDirectoryItem item) async {
    if (item.hasOpenChat) {
      context.go(
        '/chats/${item.chatId}',
        extra: {'name': item.name, 'phone': item.phone},
      );
      return;
    }
    try {
      final chat = await context.read<ChatRepository>().startChat(
            phone: item.phone,
            name: item.name,
          );
      if (!mounted) return;
      context.go(
        '/chats/${chat.id}',
        extra: {'name': item.name, 'phone': item.phone},
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: Text(
          'Contacts',
          style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.go('/contacts/add'),
        icon: const Icon(Icons.person_add_alt_1),
        label: const Text('Add'),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onChanged: _onSearchChanged,
              onSubmitted: (_) => _reload(),
              decoration: InputDecoration(
                hintText: 'Search name or phone',
                prefixIcon:
                    const Icon(Icons.search, color: AppColors.textMuted),
                filled: true,
                fillColor: AppColors.surface,
                suffixIcon: _search.text.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.clear),
                        tooltip: 'Clear search',
                        onPressed: () {
                          _search.clear();
                          _reload();
                          setState(() {});
                        },
                      ),
              ),
            ),
          ),
          Expanded(
            child: _future == null
                ? const Center(child: CircularProgressIndicator())
                : RefreshIndicator(
                    onRefresh: _reload,
                    child: FutureBuilder<List<ContactDirectoryItem>>(
                      future: _future,
                      builder: (context, snapshot) {
                        if (snapshot.connectionState ==
                            ConnectionState.waiting) {
                          return const Center(
                            child: CircularProgressIndicator(),
                          );
                        }
                        if (snapshot.hasError) {
                          final message = snapshot.error is ApiException
                              ? (snapshot.error as ApiException).message
                              : snapshot.error.toString();
                          return AppErrorState(
                            message: message,
                            onRetry: _reload,
                          );
                        }

                        final contacts = snapshot.data ?? [];
                        if (contacts.isEmpty) {
                          final searching = _search.text.trim().isNotEmpty;
                          return AppEmptyState(
                            icon: searching
                                ? Icons.search_off
                                : Icons.people_outline,
                            title: searching
                                ? 'No matches'
                                : 'No contacts yet',
                            subtitle: searching
                                ? 'Try another name or phone number.'
                                : 'Add a phone number or wait for orders/chats.',
                            actionLabel: searching ? null : 'Add contact',
                            onAction: searching
                                ? null
                                : () => context.go('/contacts/add'),
                          );
                        }

                        return ListView.separated(
                          padding: const EdgeInsets.fromLTRB(16, 14, 16, 100),
                          itemCount: contacts.length,
                          separatorBuilder: (_, __) =>
                              const SizedBox(height: 10),
                          itemBuilder: (context, index) {
                            final c = contacts[index];
                            return AppSurface(
                              onTap: () => _openOrStart(c),
                              padding: const EdgeInsets.symmetric(
                                horizontal: 8,
                                vertical: 6,
                              ),
                              child: ListTile(
                                contentPadding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 4,
                                ),
                                leading: CustomerAvatar(name: c.name),
                                title: Text(
                                  c.name,
                                  style: GoogleFonts.manrope(
                                    fontWeight: FontWeight.w800,
                                    fontSize: 15,
                                  ),
                                ),
                                subtitle: Text(
                                  [
                                    c.phone,
                                    if (c.subtitle != null &&
                                        c.subtitle!.isNotEmpty)
                                      c.subtitle!,
                                  ].join(' · '),
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: GoogleFonts.manrope(
                                    color: AppColors.textMuted,
                                    fontSize: 13,
                                  ),
                                ),
                                trailing: Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 12,
                                    vertical: 8,
                                  ),
                                  decoration: BoxDecoration(
                                    color: c.hasOpenChat
                                        ? AppColors.primarySoft
                                        : AppColors.primary,
                                    borderRadius: BorderRadius.circular(
                                      AppRadii.pill,
                                    ),
                                  ),
                                  child: Text(
                                    c.hasOpenChat ? 'Open' : '+ Add',
                                    style: GoogleFonts.manrope(
                                      fontWeight: FontWeight.w800,
                                      fontSize: 12,
                                      color: c.hasOpenChat
                                          ? AppColors.primary
                                          : Colors.white,
                                    ),
                                  ),
                                ),
                              ),
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
