import 'package:flutter/material.dart';
import 'package:flutter_contacts/flutter_contacts.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/phone_utils.dart';
import '../../shared/widgets/app_surface.dart';
import '../../shared/widgets/customer_avatar.dart';
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

      entries.sort(
        (a, b) => a.name.toLowerCase().compareTo(b.name.toLowerCase()),
      );

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
          'name': _name.text.trim().isEmpty
              ? chat.customerName
              : _name.text.trim(),
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
        const SnackBar(
          content: Text('This contact has no valid phone number.'),
        ),
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
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
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
      return AppSurface(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            const AppIconChip(
              icon: Icons.contacts_outlined,
              color: AppColors.textMuted,
              size: 48,
            ),
            const SizedBox(height: 12),
            Text(
              'Contacts access was denied. You can still add a number manually above.',
              textAlign: TextAlign.center,
              style: GoogleFonts.manrope(color: AppColors.textMuted),
            ),
            const SizedBox(height: 14),
            OutlinedButton(
              onPressed: _loadPhoneContacts,
              child: const Text('Try again'),
            ),
          ],
        ),
      );
    }

    if (_phoneContacts.isEmpty) {
      return AppSurface(
        padding: const EdgeInsets.all(20),
        child: Text(
          'No phone contacts found on this device.',
          textAlign: TextAlign.center,
          style: GoogleFonts.manrope(color: AppColors.textMuted),
        ),
      );
    }

    return Column(
      children: [
        for (var i = 0; i < _phoneContacts.length; i++) ...[
          if (i > 0) const SizedBox(height: 10),
          Builder(
            builder: (context) {
              final entry = _phoneContacts[i];
              final isAdding = _addingPhone == entry.phone;
              return AppSurface(
                padding: const EdgeInsets.symmetric(
                  horizontal: 8,
                  vertical: 4,
                ),
                child: ListTile(
                  leading: CustomerAvatar(name: entry.name, size: 44),
                  title: Text(
                    entry.name,
                    style: GoogleFonts.manrope(fontWeight: FontWeight.w700),
                  ),
                  subtitle: Text(
                    entry.phone,
                    style: GoogleFonts.manrope(
                      color: AppColors.textMuted,
                      fontSize: 13,
                    ),
                  ),
                  trailing: TextButton(
                    onPressed:
                        isAdding ? null : () => _addFromPhone(entry),
                    child: isAdding
                        ? const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('+ Add'),
                  ),
                ),
              );
            },
          ),
        ],
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: Text(
          'Add Contacts',
          style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          Text(
            'NEW CONTACT',
            style: GoogleFonts.manrope(
              fontWeight: FontWeight.w800,
              color: AppColors.textMuted,
              letterSpacing: 0.7,
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 10),
          AppSurface(
            padding: const EdgeInsets.fromLTRB(16, 18, 16, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
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
                  Text(
                    _error!,
                    style: const TextStyle(color: Colors.redAccent),
                  ),
                ],
                const SizedBox(height: 16),
                FilledButton.icon(
                  onPressed: _saving ? null : _submit,
                  icon: _saving
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.add),
                  label: Text(_saving ? 'Adding…' : 'Add contact'),
                  style: FilledButton.styleFrom(
                    minimumSize: const Size.fromHeight(52),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          Text(
            'FROM YOUR PHONE',
            style: GoogleFonts.manrope(
              fontWeight: FontWeight.w800,
              color: AppColors.textMuted,
              letterSpacing: 0.7,
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 10),
          _buildPhoneContactsSection(),
          const SizedBox(height: 24),
          AppSurface(
            padding: const EdgeInsets.all(16),
            color: AppColors.primarySoft,
            elevation: false,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Tip',
                  style: GoogleFonts.manrope(
                    fontWeight: FontWeight.w800,
                    color: AppColors.primaryDark,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  'Use the customer WhatsApp number in international format. '
                  'If a chat already exists for that phone, we open it.',
                  style: GoogleFonts.manrope(
                    color: AppColors.ink.withOpacity(0.75),
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
