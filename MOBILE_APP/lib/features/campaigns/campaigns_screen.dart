import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';
import '../companion/companion_models.dart';
import '../companion/companion_repository.dart';

class CampaignsScreen extends StatefulWidget {
  const CampaignsScreen({super.key});

  @override
  State<CampaignsScreen> createState() => _CampaignsScreenState();
}

class _CampaignsScreenState extends State<CampaignsScreen> {
  late Future<List<CampaignSummary>> _future;
  Map<String, dynamic> _limits = const {};

  @override
  void initState() {
    super.initState();
    _future = context.read<CompanionRepository>().campaigns();
    context.read<CompanionRepository>().campaignLimits().then((value) {
      if (mounted) setState(() => _limits = value);
    }).catchError((_) {});
  }

  Future<void> _reload() async {
    setState(() => _future = context.read<CompanionRepository>().campaigns());
    await _future;
  }

  Future<void> _create() async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _CampaignSheet(),
    );
    if (saved == true) await _reload();
  }

  Future<void> _send(CampaignSummary campaign) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Send campaign?'),
        content: Text('This messages the ${campaign.segment} audience now.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Send')),
        ],
      ),
    );
    if (ok != true) return;
    final repo = context.read<CompanionRepository>();
    try {
      await repo.sendCampaign(campaign.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Campaign sending')));
      await _reload();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final used = _limits['campaignsUsed'];
    final limit = _limits['campaignsLimit'];
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: Text('Campaigns', style: GoogleFonts.manrope(fontWeight: FontWeight.w800)),
        actions: [IconButton(onPressed: _create, icon: const Icon(Icons.add))],
      ),
      body: FutureBuilder<List<CampaignSummary>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Padding(
              padding: const EdgeInsets.all(16),
              child: AppSurface(
                padding: const EdgeInsets.all(20),
                child: Text(
                  snapshot.error is ApiException ? (snapshot.error! as ApiException).message : 'Campaigns unavailable.',
                  style: GoogleFonts.manrope(color: AppColors.textMuted),
                ),
              ),
            );
          }
          final items = snapshot.data ?? [];
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
            children: [
              if (used != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Text(
                    'This month: $used${limit == null ? '' : ' / $limit'} campaigns',
                    style: GoogleFonts.manrope(color: AppColors.textMuted, fontWeight: FontWeight.w600),
                  ),
                ),
              if (items.isEmpty)
                AppSurface(
                  padding: const EdgeInsets.all(20),
                  child: Text('No WhatsApp campaigns yet.', style: GoogleFonts.manrope(color: AppColors.textMuted)),
                )
              else
                ...items.map(
                  (c) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: AppSurface(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                      child: ListTile(
                        title: Text(c.name, style: GoogleFonts.manrope(fontWeight: FontWeight.w800)),
                        subtitle: Text('${c.status} · ${c.segment} · ${c.sentCount}/${c.totalRecipients} sent'),
                        trailing: c.status == 'draft'
                            ? TextButton(onPressed: () => _send(c), child: const Text('Send'))
                            : null,
                      ),
                    ),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

class _CampaignSheet extends StatefulWidget {
  const _CampaignSheet();

  @override
  State<_CampaignSheet> createState() => _CampaignSheetState();
}

class _CampaignSheetState extends State<_CampaignSheet> {
  final _name = TextEditingController();
  final _caption = TextEditingController();
  String _segment = 'all';
  bool _saving = false;

  @override
  void dispose() {
    _name.dispose();
    _caption.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_name.text.trim().isEmpty || _caption.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Name and message are required.')));
      return;
    }
    setState(() => _saving = true);
    try {
      await context.read<CompanionRepository>().createCampaign(
            name: _name.text,
            segment: _segment,
            caption: _caption.text,
          );
      if (mounted) Navigator.pop(context, true);
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
          Text('New campaign', style: GoogleFonts.manrope(fontWeight: FontWeight.w800, fontSize: 18)),
          TextField(controller: _name, decoration: const InputDecoration(labelText: 'Name')),
          DropdownButtonFormField<String>(
            value: _segment,
            items: const [
              DropdownMenuItem(value: 'all', child: Text('All contacts')),
              DropdownMenuItem(value: 'recent', child: Text('Recent')),
              DropdownMenuItem(value: 'inactive', child: Text('Inactive')),
              DropdownMenuItem(value: 'ordered', child: Text('Has ordered')),
            ],
            onChanged: (v) => setState(() => _segment = v ?? 'all'),
            decoration: const InputDecoration(labelText: 'Audience'),
          ),
          TextField(controller: _caption, maxLines: 4, decoration: const InputDecoration(labelText: 'Message')),
          const SizedBox(height: 12),
          FilledButton(onPressed: _saving ? null : _save, child: Text(_saving ? 'Creating…' : 'Create draft')),
        ],
      ),
    );
  }
}
