import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';
import '../companion/companion_models.dart';
import '../companion/companion_repository.dart';

class TeamScreen extends StatefulWidget {
  const TeamScreen({super.key});

  @override
  State<TeamScreen> createState() => _TeamScreenState();
}

class _TeamScreenState extends State<TeamScreen> {
  late Future<List<TeamMember>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<CompanionRepository>().team();
  }

  Future<void> _reload() async {
    setState(() => _future = context.read<CompanionRepository>().team());
    await _future;
  }

  Future<void> _invite() async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _InviteSheet(),
    );
    if (saved == true) await _reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: Text('Team', style: GoogleFonts.manrope(fontWeight: FontWeight.w800)),
        actions: [IconButton(onPressed: _invite, icon: const Icon(Icons.person_add_alt_1_outlined))],
      ),
      body: FutureBuilder<List<TeamMember>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text(snapshot.error is ApiException ? (snapshot.error! as ApiException).message : 'Failed'));
          }
          final members = snapshot.data ?? [];
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
            children: members
                .map(
                  (m) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: AppSurface(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      child: ListTile(
                        title: Text(m.name, style: GoogleFonts.manrope(fontWeight: FontWeight.w800)),
                        subtitle: Text('${m.email}\n${m.role} · ${m.status}'),
                        isThreeLine: true,
                      ),
                    ),
                  ),
                )
                .toList(),
          );
        },
      ),
    );
  }
}

class _InviteSheet extends StatefulWidget {
  const _InviteSheet();

  @override
  State<_InviteSheet> createState() => _InviteSheetState();
}

class _InviteSheetState extends State<_InviteSheet> {
  final _name = TextEditingController();
  final _email = TextEditingController();
  bool _saving = false;

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final name = _name.text.trim();
    final email = _email.text.trim();
    if (name.isEmpty || !email.contains('@')) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Enter a name and valid email.')));
      return;
    }
    setState(() => _saving = true);
    try {
      final password = await context.read<CompanionRepository>().inviteTeam(name: name, email: email);
      if (!mounted) return;
      Navigator.pop(context, true);
      if (password != null && password.isNotEmpty) {
        await showDialog<void>(
          context: context,
          builder: (context) => AlertDialog(
            title: const Text('Share this password once'),
            content: Text('Temporary password:\n$password\n\nAsk the teammate to sign in and change it immediately.'),
            actions: [
              TextButton(
                onPressed: () async {
                  await Clipboard.setData(ClipboardData(text: password));
                  if (context.mounted) Navigator.pop(context);
                },
                child: const Text('Copy'),
              ),
            ],
          ),
        );
      }
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 16, 16, 16 + MediaQuery.of(context).viewInsets.bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text('Invite teammate', style: GoogleFonts.manrope(fontWeight: FontWeight.w800, fontSize: 18)),
          TextField(controller: _name, decoration: const InputDecoration(labelText: 'Name')),
          TextField(controller: _email, decoration: const InputDecoration(labelText: 'Email'), keyboardType: TextInputType.emailAddress),
          const SizedBox(height: 12),
          FilledButton(onPressed: _saving ? null : _save, child: Text(_saving ? 'Inviting…' : 'Send invite')),
        ],
      ),
    );
  }
}
