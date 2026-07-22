import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import 'faq_models.dart';
import 'faq_repository.dart';

class FaqFormScreen extends StatefulWidget {
  const FaqFormScreen({super.key, this.faq});

  final Faq? faq;

  bool get isEditing => faq != null;

  @override
  State<FaqFormScreen> createState() => _FaqFormScreenState();
}

class _FaqFormScreenState extends State<FaqFormScreen> {
  late final FaqRepository _repo;
  late final TextEditingController _question;
  late final TextEditingController _answer;
  late final TextEditingController _category;
  late final TextEditingController _keywords;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _repo = context.read<FaqRepository>();
    final faq = widget.faq;
    _question = TextEditingController(text: faq?.question ?? '');
    _answer = TextEditingController(text: faq?.answer ?? '');
    _category = TextEditingController(text: faq?.category ?? '');
    _keywords = TextEditingController(text: faq?.keywords.join(', ') ?? '');
  }

  @override
  void dispose() {
    _question.dispose();
    _answer.dispose();
    _category.dispose();
    _keywords.dispose();
    super.dispose();
  }

  List<String> _parseKeywords(String raw) {
    return raw
        .split(',')
        .map((k) => k.trim())
        .where((k) => k.isNotEmpty)
        .toList();
  }

  Future<void> _submit() async {
    final question = _question.text.trim();
    final answer = _answer.text.trim();
    if (question.isEmpty || answer.isEmpty) {
      setState(() => _error = 'Question and answer are required.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    final category = _category.text.trim();
    final keywords = _parseKeywords(_keywords.text);

    try {
      if (widget.isEditing) {
        await _repo.updateFaq(
          widget.faq!.id,
          question: question,
          answer: answer,
          category: category,
          keywords: keywords,
        );
      } else {
        await _repo.createFaq(
          question: question,
          answer: answer,
          category: category.isEmpty ? null : category,
          keywords: keywords.isEmpty ? null : keywords,
        );
      }
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
      appBar: AppBar(
        title: Text(widget.isEditing ? 'Edit FAQ' : 'Add FAQ'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'FAQ DETAILS',
            style: TextStyle(
              fontWeight: FontWeight.w700,
              color: AppColors.textMuted,
              letterSpacing: 0.6,
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _question,
            textInputAction: TextInputAction.next,
            maxLines: 2,
            decoration: const InputDecoration(
              labelText: 'Question',
              prefixIcon: Icon(Icons.help_outline),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _answer,
            textInputAction: TextInputAction.next,
            minLines: 3,
            maxLines: 6,
            decoration: const InputDecoration(
              labelText: 'Answer',
              prefixIcon: Icon(Icons.chat_bubble_outline),
              alignLabelWithHint: true,
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _category,
            textInputAction: TextInputAction.next,
            decoration: const InputDecoration(
              labelText: 'Category (optional)',
              prefixIcon: Icon(Icons.category_outlined),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _keywords,
            textInputAction: TextInputAction.done,
            onSubmitted: (_) => _saving ? null : _submit(),
            decoration: const InputDecoration(
              labelText: 'Keywords (comma-separated)',
              prefixIcon: Icon(Icons.label_outline),
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
                : Icon(widget.isEditing ? Icons.save_outlined : Icons.add),
            label: Text(_saving
                ? 'Saving…'
                : widget.isEditing
                    ? 'Save changes'
                    : 'Create FAQ'),
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
          const Text(
            'Clear questions and concise answers help the AI match customer messages. '
            'Keywords improve matching when wording differs.',
            style: TextStyle(color: AppColors.textMuted),
          ),
        ],
      ),
    );
  }
}
