import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import 'faq_form_screen.dart';
import 'faq_models.dart';
import 'faq_repository.dart';

class FaqsScreen extends StatefulWidget {
  const FaqsScreen({super.key});

  @override
  State<FaqsScreen> createState() => _FaqsScreenState();
}

class _FaqsScreenState extends State<FaqsScreen> {
  late FaqRepository _repo;
  late Future<List<Faq>> _future;
  final _searchController = TextEditingController();
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    _repo = context.read<FaqRepository>();
    _future = _repo.listFaqs();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _reload() async {
    setState(() {
      _future = _repo.listFaqs(search: _searchQuery);
    });
    await _future;
  }

  Future<void> _openForm({Faq? faq}) async {
    final saved = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => FaqFormScreen(faq: faq)),
    );
    if (saved == true) await _reload();
  }

  Future<void> _toggleActive(Faq faq) async {
    final next = !faq.isActive;
    try {
      await _repo.updateFaq(faq.id, isActive: next);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(next ? 'FAQ activated' : 'FAQ deactivated')),
      );
      await _reload();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _confirmDelete(Faq faq) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete FAQ?'),
        content: Text(
          faq.question,
          maxLines: 3,
          overflow: TextOverflow.ellipsis,
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            style: TextButton.styleFrom(foregroundColor: Colors.redAccent),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await _repo.deleteFaq(faq.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('FAQ deleted')),
      );
      await _reload();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  void _applySearch(String value) {
    setState(() => _searchQuery = value.trim());
    _reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('FAQs')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openForm(),
        icon: const Icon(Icons.add),
        label: const Text('Add FAQ'),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
            child: TextField(
              controller: _searchController,
              textInputAction: TextInputAction.search,
              onSubmitted: _applySearch,
              onChanged: (value) {
                setState(() {});
                if (value.isEmpty && _searchQuery.isNotEmpty) _applySearch('');
              },
              decoration: InputDecoration(
                hintText: 'Search questions or answers',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _searchQuery.isNotEmpty || _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _searchController.clear();
                          _applySearch('');
                        },
                      )
                    : null,
              ),
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _reload,
              child: FutureBuilder<List<Faq>>(
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
                        Icon(Icons.error_outline, color: Colors.red.shade300, size: 40),
                        const SizedBox(height: 12),
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 24),
                          child: Text(message, textAlign: TextAlign.center),
                        ),
                      ],
                    );
                  }

                  final faqs = snapshot.data ?? [];
                  if (faqs.isEmpty) {
                    return ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.only(bottom: 88),
                      children: const [
                        SizedBox(height: 120),
                        Icon(Icons.help_outline, color: AppColors.primary, size: 40),
                        SizedBox(height: 12),
                        Text('No FAQs yet', textAlign: TextAlign.center),
                        SizedBox(height: 4),
                        Text(
                          'Add answers your AI can use in conversations.',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: AppColors.textMuted),
                        ),
                      ],
                    );
                  }

                  return ListView.builder(
                    padding: const EdgeInsets.only(bottom: 88),
                    itemCount: faqs.length,
                    itemBuilder: (context, index) {
                      final faq = faqs[index];
                      return Card(
                        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                        child: Theme(
                          data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
                          child: ExpansionTile(
                            tilePadding: const EdgeInsets.symmetric(horizontal: 16),
                            childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                            leading: CircleAvatar(
                              backgroundColor: faq.isActive
                                  ? AppColors.bubbleIncoming
                                  : Colors.grey.shade200,
                              child: Icon(
                                faq.isActive ? Icons.check : Icons.pause,
                                color: faq.isActive ? AppColors.primary : AppColors.textMuted,
                                size: 20,
                              ),
                            ),
                            title: Text(
                              faq.question,
                              style: TextStyle(
                                fontWeight: FontWeight.w600,
                                color: faq.isActive ? null : AppColors.textMuted,
                              ),
                            ),
                            subtitle: faq.category.isNotEmpty
                                ? Text(faq.category, style: const TextStyle(color: AppColors.textMuted))
                                : null,
                            trailing: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                if (faq.usageCount > 0)
                                  Padding(
                                    padding: const EdgeInsets.only(right: 4),
                                    child: Text(
                                      '${faq.usageCount} uses',
                                      style: const TextStyle(
                                        color: AppColors.textMuted,
                                        fontSize: 11,
                                      ),
                                    ),
                                  ),
                                Switch(
                                  value: faq.isActive,
                                  onChanged: (_) => _toggleActive(faq),
                                ),
                              ],
                            ),
                            children: [
                              Align(
                                alignment: Alignment.centerLeft,
                                child: Text(
                                  faq.answer,
                                  style: const TextStyle(height: 1.4),
                                ),
                              ),
                              if (faq.keywords.isNotEmpty) ...[
                                const SizedBox(height: 12),
                                Wrap(
                                  spacing: 6,
                                  runSpacing: 6,
                                  children: faq.keywords
                                      .map(
                                        (k) => Chip(
                                          label: Text(k, style: const TextStyle(fontSize: 12)),
                                          backgroundColor: AppColors.bubbleOutgoing,
                                          side: BorderSide.none,
                                          visualDensity: VisualDensity.compact,
                                        ),
                                      )
                                      .toList(),
                                ),
                              ],
                              const SizedBox(height: 8),
                              Row(
                                children: [
                                  Text(
                                    faq.createdAt.isNotEmpty ? 'Added ${faq.createdAt}' : '',
                                    style: const TextStyle(
                                      color: AppColors.textMuted,
                                      fontSize: 12,
                                    ),
                                  ),
                                  const Spacer(),
                                  IconButton(
                                    tooltip: 'Edit',
                                    onPressed: () => _openForm(faq: faq),
                                    icon: const Icon(Icons.edit_outlined, color: AppColors.primary),
                                  ),
                                  IconButton(
                                    tooltip: 'Delete',
                                    onPressed: () => _confirmDelete(faq),
                                    icon: Icon(Icons.delete_outline, color: Colors.red.shade400),
                                  ),
                                ],
                              ),
                            ],
                          ),
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
