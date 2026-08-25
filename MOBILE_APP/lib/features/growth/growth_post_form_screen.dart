import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';
import 'growth_models.dart';
import 'growth_repository.dart';

class GrowthPostFormScreen extends StatefulWidget {
  const GrowthPostFormScreen({super.key});

  @override
  State<GrowthPostFormScreen> createState() => _GrowthPostFormScreenState();
}

class _GrowthPostFormScreenState extends State<GrowthPostFormScreen> {
  late final GrowthRepository _repo;
  late final TextEditingController _title;
  late final TextEditingController _content;
  late final TextEditingController _mediaUrl;
  String _platform = growthPlatforms.first;
  bool _saving = false;
  String? _error;

  bool get _isInstagram => _platform == 'instagram';

  @override
  void initState() {
    super.initState();
    _repo = context.read<GrowthRepository>();
    _title = TextEditingController();
    _content = TextEditingController();
    _mediaUrl = TextEditingController();
  }

  @override
  void dispose() {
    _title.dispose();
    _content.dispose();
    _mediaUrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final content = _content.text.trim();
    if (content.isEmpty) {
      setState(() => _error = 'Content is required.');
      return;
    }

    final media = _mediaUrl.text.trim();
    if (_isInstagram && media.isEmpty) {
      setState(() => _error =
          'Instagram drafts need an image URL before you can publish.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    final title = _title.text.trim();

    try {
      await _repo.createPost(
        platform: _platform,
        content: content,
        title: title.isEmpty ? null : title,
        mediaUrls: media.isEmpty ? null : [media],
      );
      if (!mounted) return;
      Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: Text(
          'New post',
          style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          Text(
            'POST DETAILS',
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
                DropdownButtonFormField<String>(
                  value: _platform,
                  decoration: const InputDecoration(
                    labelText: 'Platform',
                    prefixIcon: Icon(Icons.share_outlined),
                  ),
                  items: growthPlatforms
                      .map(
                        (p) => DropdownMenuItem(
                          value: p,
                          child: Text(p[0].toUpperCase() + p.substring(1)),
                        ),
                      )
                      .toList(),
                  onChanged: _saving
                      ? null
                      : (value) {
                          if (value != null) setState(() => _platform = value);
                        },
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _title,
                  textInputAction: TextInputAction.next,
                  enabled: !_saving,
                  decoration: const InputDecoration(
                    labelText: 'Title (optional)',
                    prefixIcon: Icon(Icons.title),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _content,
                  textInputAction: TextInputAction.next,
                  enabled: !_saving,
                  minLines: 4,
                  maxLines: 8,
                  decoration: const InputDecoration(
                    labelText: 'Content',
                    prefixIcon: Icon(Icons.edit_outlined),
                    alignLabelWithHint: true,
                  ),
                ),
                if (_isInstagram) ...[
                  const SizedBox(height: 12),
                  TextField(
                    controller: _mediaUrl,
                    textInputAction: TextInputAction.done,
                    enabled: !_saving,
                    onSubmitted: (_) => _saving ? null : _submit(),
                    decoration: const InputDecoration(
                      labelText: 'Image URL (required for Instagram)',
                      prefixIcon: Icon(Icons.image_outlined),
                    ),
                  ),
                ],
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(_error!, style: const TextStyle(color: Colors.redAccent)),
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
                  label: Text(_saving ? 'Creating…' : 'Create post'),
                  style: FilledButton.styleFrom(
                    minimumSize: const Size.fromHeight(52),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
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
                  _isInstagram
                      ? 'Instagram publish requires a public image URL. Posts are saved as drafts until you approve and publish.'
                      : 'Posts are saved as drafts. Approve and publish them from the Growth screen once you are ready to go live.',
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
