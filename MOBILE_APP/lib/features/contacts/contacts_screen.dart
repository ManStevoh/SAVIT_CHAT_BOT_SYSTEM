import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/phone_utils.dart';
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

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      setState(() => _future = _load());
    });
  }

  @override
  void dispose() {
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
      final phone = normalizePhoneDigits(chat.customerPhone);
      if (phone.isEmpty) continue;
      byPhone[phone] = ContactDirectoryItem(
        name: chat.customerName,
        phone: phone,
        chatId: chat.id,
        subtitle: chat.lastMessage.isEmpty ? null : chat.lastMessage,
      );
    }

    for (final customer in customers) {
      final phone = normalizePhoneDigits(customer.phone);
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

  Future<void> _openOrStart(ContactDirectoryItem item) async {
    if (item.hasOpenChat) {
      context.go('/chats/${item.chatId}');
      return;
    }
    try {
      final chat = await context.read<ChatRepository>().startChat(
            phone: item.phone,
            name: item.name,
          );
      if (!mounted) return;
      context.go('/chats/${chat.id}');
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Contacts')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.go('/contacts/add'),
        icon: const Icon(Icons.person_add_alt_1),
        label: const Text('Add'),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _reload(),
              decoration: InputDecoration(
                hintText: 'Search name or phone',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: IconButton(
                  icon: const Icon(Icons.tune),
                  tooltip: 'Search',
                  onPressed: _reload,
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
                        const SizedBox(height: 120),
                        Padding(
                          padding: const EdgeInsets.all(24),
                          child: Text(message, textAlign: TextAlign.center),
                        ),
                      ],
                    );
                  }

                  final contacts = snapshot.data ?? [];
                  if (contacts.isEmpty) {
                    return ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.only(bottom: 88),
                      children: const [
                        SizedBox(height: 120),
                        Icon(Icons.people_outline, color: AppColors.primary, size: 40),
                        SizedBox(height: 12),
                        Text('No contacts yet', textAlign: TextAlign.center),
                        SizedBox(height: 4),
                        Text(
                          'Add a phone number or wait for orders/chats.',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: AppColors.textMuted),
                        ),
                      ],
                    );
                  }

                  return ListView.separated(
                    padding: const EdgeInsets.only(bottom: 88),
                    itemCount: contacts.length,
                    separatorBuilder: (_, __) => const Divider(height: 1),
                    itemBuilder: (context, index) {
                      final c = contacts[index];
                      final initial =
                          c.name.isNotEmpty ? c.name[0].toUpperCase() : '?';
                      return ListTile(
                        leading: CircleAvatar(
                          backgroundColor: AppColors.bubbleIncoming,
                          child: Text(initial),
                        ),
                        title: Text(c.name),
                        subtitle: Text(
                          [
                            c.phone,
                            if (c.subtitle != null && c.subtitle!.isNotEmpty) c.subtitle!,
                          ].join(' · '),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                        trailing: TextButton(
                          onPressed: () => _openOrStart(c),
                          child: Text(c.hasOpenChat ? 'Open' : '+ Add'),
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
