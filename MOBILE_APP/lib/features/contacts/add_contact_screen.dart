import 'package:flutter/material.dart';
import 'package:flutter_contacts/flutter_contacts.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/phone_utils.dart';
import '../chats/chat_repository.dart';

class _PhoneContactEntry {
  const _PhoneContactEntry({required this.name, required this.phone});

  final String name;
  final String phone;
}

class AddContactScreen extends StatefulWidget {
  const AddContactScreen({super.key});

  @override
  State<AddContactScreen> createState() => _AddContactScreenState();
}

class _AddContactScreenState extends State<AddContactScreen> {
  final _phone = TextEditingController();
  final _name = TextEditingController();
  bool _saving = false;
  String? _error;

  bool _loadingContacts = true;
  bool _permissionDenied = false;
  List<_PhoneContactEntry> _phoneContacts = const [];
  String? _addingPhone;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      _loadPhoneContacts();
    });
  }

  @override
  void dispose() {
    _phone.dispose();
    _name.dispose();
    super.dispose();
  }

  Future<void> _loadPhoneContacts() async {
    setState(() {
      _loadingContacts = true;
      _permissionDenied = false;
    });

    final granted = await FlutterContacts.requestPermission(readonly: true);
    if (!granted) {
      if (mounted) {
        setState(() {
          _loadingContacts = false;
          _permissionDenied = true;
        });
      }
      return;
    }

    try {
      final contacts = await FlutterContacts.getContacts(withProperties: true);
      final entries = <_PhoneContactEntry>[];

      for (final contact in contacts) {
        final name = contact.displayName.trim();
        for (final phone in contact.phones) {
          final number = phone.number.trim();
          if (number.isEmpty) continue;
          entries.add(_PhoneContactEntry(
            name: name.isNotEmpty ? name : number,
            phone: number,
          ));
        }
      }

      entries.sort((a, b) => a.name.toLowerCase().compareTo(b.name.toLowerCase()));

      if (mounted) {
        setState(() {
          _phoneContacts = entries;
          _loadingContacts = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _phoneContacts = const [];
          _loadingContacts = false;
        });
      }
    }
  }

  Future<void> _submit() async {
    final phone = phoneMergeKey(_phone.text);
    if (phone.isEmpty) {
      setState(() => _error = 'Enter a valid phone number.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final chat = await context.read<ChatRepository>().startChat(
            phone: phone,
            name: _name.text.trim(),
          );
      if (!mounted) return;
      context.go(
        '/chats/${chat.id}',
        extra: {
          'name': _name.text.trim().isEmpty ? chat.customerName : _name.text.trim(),
          'phone': phone,
        },
      );
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _addFromPhone(_PhoneContactEntry entry) async {
    final phone = phoneMergeKey(entry.phone);
    if (phone.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('This contact has no valid phone number.')),
      );
      return;
    }
    setState(() => _addingPhone = entry.phone);
    try {
      final chat = await context.read<ChatRepository>().startChat(
            phone: phone,
            name: entry.name,
          );
      if (!mounted) return;
      context.go(
        '/chats/${chat.id}',
        extra: {
          'name': entry.name.isEmpty ? chat.customerName : entry.name,
          'phone': phone,
        },
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _addingPhone = null);
    }
  }

  Widget _buildPhoneContactsSection() {
    if (_loadingContacts) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 24),
        child: Center(child: CircularProgressIndicator()),
      );
    }

    if (_permissionDenied) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Icon(Icons.contacts_outlined, color: AppColors.textMuted, size: 36),
          const SizedBox(height: 12),
          const Text(
            'Contacts access was denied. You can still add a number manually above.',
            textAlign: TextAlign.center,
            style: TextStyle(color: AppColors.textMuted),
          ),
          const SizedBox(height: 12),
          OutlinedButton(
            onPressed: _loadPhoneContacts,
            style: OutlinedButton.styleFrom(
              foregroundColor: AppColors.primary,
              side: const BorderSide(color: AppColors.primary),
            ),
            child: const Text('Try again'),
          ),
        ],
      );
    }

    if (_phoneContacts.isEmpty) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 16),
        child: Text(
          'No phone contacts found on this device.',
          textAlign: TextAlign.center,
          style: TextStyle(color: AppColors.textMuted),
        ),
      );
    }

    return ListView.separated(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: _phoneContacts.length,
      separatorBuilder: (_, __) => const Divider(height: 1),
      itemBuilder: (context, index) {
        final entry = _phoneContacts[index];
        final initial = entry.name.isNotEmpty ? entry.name[0].toUpperCase() : '?';
        final isAdding = _addingPhone == entry.phone;

        return ListTile(
          contentPadding: EdgeInsets.zero,
          leading: CircleAvatar(
            backgroundColor: AppColors.bubbleIncoming,
            child: Text(initial),
          ),
          title: Text(entry.name),
          subtitle: Text(entry.phone),
          trailing: TextButton(
            onPressed: isAdding ? null : () => _addFromPhone(entry),
            child: isAdding
                ? const SizedBox(
                    width: 16,
                    height: 16,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Text('+ Add'),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Add Contacts')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'ADD NEW CONTACT',
            style: TextStyle(
              fontWeight: FontWeight.w700,
              color: AppColors.textMuted,
              letterSpacing: 0.6,
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _name,
            textInputAction: TextInputAction.next,
            decoration: const InputDecoration(
              labelText: 'Name',
              prefixIcon: Icon(Icons.person_outline),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _phone,
            keyboardType: TextInputType.phone,
            textInputAction: TextInputAction.done,
            onSubmitted: (_) => _saving ? null : _submit(),
            decoration: const InputDecoration(
              labelText: 'Phone Number',
              prefixIcon: Icon(Icons.phone_outlined),
            ),
          ),
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(_error!, style: const TextStyle(color: Colors.redAccent)),
          ],
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: _saving ? null : _submit,
            icon: _saving
                ? const SizedBox(
                    width: 16,
                    height: 16,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.add),
            label: Text(_saving ? 'Adding…' : 'Add Contacts'),
            style: OutlinedButton.styleFrom(
              foregroundColor: AppColors.primary,
              minimumSize: const Size.fromHeight(48),
              side: const BorderSide(color: AppColors.primary),
            ),
          ),
          const SizedBox(height: 28),
          const Text(
            'EXISTING CONTACT IN PHONE',
            style: TextStyle(
              fontWeight: FontWeight.w700,
              color: AppColors.textMuted,
              letterSpacing: 0.6,
            ),
          ),
          const SizedBox(height: 12),
          _buildPhoneContactsSection(),
          const SizedBox(height: 28),
          const Text(
            'Tip',
            style: TextStyle(fontWeight: FontWeight.w700, color: AppColors.textMuted),
          ),
          const SizedBox(height: 8),
          const Text(
            'Use the customer WhatsApp number in international format. '
            'If a chat already exists for that phone, we open it.',
            style: TextStyle(color: AppColors.textMuted),
          ),
        ],
      ),
    );
  }
}
