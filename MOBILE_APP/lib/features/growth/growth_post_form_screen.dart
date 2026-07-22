import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
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
      setState(() =>
          _error = 'Instagram drafts need an image URL before you can publish.');
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
      appBar: AppBar(title: const Text('New post')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'POST DETAILS',
            style: TextStyle(
              fontWeight: FontWeight.w700,
              color: AppColors.textMuted,
              letterSpacing: 0.6,
            ),
          ),
          const SizedBox(height: 12),
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
          OutlinedButton.icon(
            onPressed: _saving ? null : _submit,
            icon: _saving
                ? const SizedBox(
                    width: 16,
                    height: 16,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.add),
            label: Text(_saving ? 'Creating…' : 'Create post'),
            style: OutlinedButton.styleFrom(
              foregroundColor: AppColors.primary,
              minimumSize: const Size.fromHeight(48),
              side: const BorderSide(color: AppColors.primary),
            ),
          ),
          const SizedBox(height: 28),
          const Text(
            'Tip',
            style: TextStyle(fontWeight: FontWeight.w700, color: AppColors.textMuted),
          ),
          const SizedBox(height: 8),
          Text(
            _isInstagram
                ? 'Instagram publish requires a public image URL. Posts are saved as drafts until you approve and publish.'
                : 'Posts are saved as drafts. Approve and publish them from the Growth screen once you are ready to go live.',
            style: const TextStyle(color: AppColors.textMuted),
          ),
        ],
      ),
    );
  }
}
