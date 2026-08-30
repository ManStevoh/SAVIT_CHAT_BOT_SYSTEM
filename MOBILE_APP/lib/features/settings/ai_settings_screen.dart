import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';
import '../companion/companion_repository.dart';
import 'company_settings_controller.dart';

class AiSettingsScreen extends StatefulWidget {
  const AiSettingsScreen({super.key});

  @override
  State<AiSettingsScreen> createState() => _AiSettingsScreenState();
}

class _AiSettingsScreenState extends State<AiSettingsScreen> {
  final _greeting = TextEditingController();
  final _tone = TextEditingController();
  bool _autoReply = true;
  bool _learn = true;
  bool _learnEditable = true;
  bool _loading = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _greeting.dispose();
    _tone.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final json = await context.read<CompanionRepository>().companySettings();
      _greeting.text = json['aiGreeting']?.toString() ?? '';
      _tone.text = json['aiTone']?.toString() ?? '';
      _autoReply = json['autoReplyEnabled'] != false;
      _learn = json['learnFromConversations'] != false;
      _learnEditable = json['learnFromConversationsEditable'] != false;
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    final repo = context.read<CompanionRepository>();
    final settings = context.read<CompanySettingsController>();
    try {
      await repo.updateSettings({
        'aiGreeting': _greeting.text.trim(),
        'aiTone': _tone.text.trim(),
        'autoReplyEnabled': _autoReply,
        if (_learnEditable) 'learnFromConversations': _learn,
      });
      if (!mounted) return;
      await settings.load(force: true);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('AI replies saved')),
      );
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: Text('AI replies', style: GoogleFonts.manrope(fontWeight: FontWeight.w800)),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
              children: [
                AppSurface(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: Column(
                    children: [
                      SwitchListTile(
                        value: _autoReply,
                        onChanged: (v) => setState(() => _autoReply = v),
                        title: const Text('Auto-reply on WhatsApp'),
                        subtitle: const Text('The bot answers when no agent is handling the chat.'),
                      ),
                      SwitchListTile(
                        value: _learn,
                        onChanged: _learnEditable ? (v) => setState(() => _learn = v) : null,
                        title: const Text('Learn from conversations'),
                        subtitle: Text(
                          _learnEditable
                              ? 'Improve future replies from approved chat samples.'
                              : 'Learning is managed by the platform.',
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                AppSurface(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      TextField(
                        controller: _greeting,
                        maxLines: 3,
                        decoration: const InputDecoration(
                          labelText: 'Greeting',
                          hintText: 'How the bot introduces your business',
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _tone,
                        decoration: const InputDecoration(
                          labelText: 'Tone',
                          hintText: 'Friendly, professional, concise…',
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: _saving ? null : _save,
                  child: Text(_saving ? 'Saving…' : 'Save AI settings'),
                ),
              ],
            ),
    );
  }
}
