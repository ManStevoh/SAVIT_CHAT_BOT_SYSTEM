import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';
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
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: Text(
          widget.isEditing ? 'Edit FAQ' : 'Add FAQ',
          style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          Text(
            'FAQ DETAILS',
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
                      : Icon(
                          widget.isEditing ? Icons.save_outlined : Icons.add,
                        ),
                  label: Text(
                    _saving
                        ? 'Saving…'
                        : widget.isEditing
                            ? 'Save changes'
                            : 'Create FAQ',
                  ),
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
                  'Clear questions and concise answers help the AI match customer messages. '
                  'Keywords improve matching when wording differs.',
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
