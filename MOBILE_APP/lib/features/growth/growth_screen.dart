import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import 'growth_models.dart';
import 'growth_post_form_screen.dart';
import 'growth_repository.dart';

class GrowthScreen extends StatefulWidget {
  const GrowthScreen({super.key});

  @override
  State<GrowthScreen> createState() => _GrowthScreenState();
}

class _GrowthScreenState extends State<GrowthScreen> {
  static const _periods = ['7d', '30d', '90d'];

  late final GrowthRepository _repository;
  late Future<GrowthOverview> _future;
  String _period = '30d';

  @override
  void initState() {
    super.initState();
    _repository = context.read<GrowthRepository>();
    _future = _repository.loadOverview(period: _period);
  }

  Future<void> _reload() async {
    setState(() {
      _future = _repository.loadOverview(period: _period);
    });
    await _future;
  }

  void _selectPeriod(String period) {
    if (_period == period) return;
    setState(() {
      _period = period;
      _future = _repository.loadOverview(period: period);
    });
  }

  Future<void> _openNewPostForm() async {
    final created = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => const GrowthPostFormScreen()),
    );
    if (created == true) await _reload();
  }

  Future<void> _approvePost(String id) async {
    try {
      await _repository.approvePost(id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Post approved')),
      );
      await _reload();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _publishPost(String id) async {
    GrowthPost? post;
    try {
      final overview = await _future;
      post = _postById(overview.recentPosts, id);
    } catch (_) {}
    if (post != null && post.needsMediaForPublish) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Instagram posts need at least one image URL before publish.',
          ),
        ),
      );
      return;
    }
    try {
      await _repository.publishPost(id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Post published')),
      );
      await _reload();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  GrowthPost? _postById(List<GrowthPost> posts, String id) {
    for (final post in posts) {
      if (post.id == id) return post;
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Growth')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _openNewPostForm,
        icon: const Icon(Icons.add),
        label: const Text('New post'),
      ),
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<GrowthOverview>(
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
                padding: const EdgeInsets.all(24),
                children: [
                  const SizedBox(height: 80),
                  Icon(Icons.error_outline, color: Colors.red.shade300, size: 40),
                  const SizedBox(height: 12),
                  Text(message, textAlign: TextAlign.center),
                ],
              );
            }

            final data = snapshot.data!;
            final analytics = data.analytics;
            final summary = analytics.summary;

            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              children: [
                Row(
                  children: [
                    const Text(
                      'Growth overview',
                      style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
                    ),
                    if (analytics.isDemo) ...[
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(
                          color: AppColors.bubbleIncoming,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: const Text(
                          'Demo',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: AppColors.primaryDark,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  children: _periods.map((period) {
                    final selected = _period == period;
                    return ChoiceChip(
                      label: Text(period),
                      selected: selected,
                      onSelected: (_) => _selectPeriod(period),
                      selectedColor: AppColors.primary.withOpacity(0.18),
                      labelStyle: TextStyle(
                        color: selected ? AppColors.primaryDark : AppColors.textMuted,
                        fontWeight: selected ? FontWeight.w600 : FontWeight.normal,
                      ),
                      side: BorderSide(
                        color: selected ? AppColors.primary : Colors.grey.shade300,
                      ),
                    );
                  }).toList(),
                ),
                if (analytics.celebration?.showHighlight == true) ...[
                  const SizedBox(height: 16),
                  Card(
                    color: AppColors.bubbleIncoming,
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Row(
                        children: [
                          const Icon(Icons.celebration, color: AppColors.primary),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              analytics.celebration!.message,
                              style: const TextStyle(
                                fontWeight: FontWeight.w600,
                                color: AppColors.primaryDark,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 16),
                Wrap(
                  spacing: 12,
                  runSpacing: 12,
                  children: [
                    _MetricCard(label: 'Leads', value: '${summary.leads}'),
                    _MetricCard(label: 'WhatsApp starts', value: '${summary.whatsappStarts}'),
                    _MetricCard(label: 'Clicks', value: '${summary.clicks}'),
                    _MetricCard(label: 'Orders', value: '${summary.orders}'),
                    _MetricCard(label: 'Revenue', value: formatGrowthMoney(summary.revenue)),
                    _MetricCard(label: 'Ad spend', value: formatGrowthMoney(summary.adSpend)),
                    _MetricCard(
                      label: 'Conversion',
                      value: '${summary.conversionRate.toStringAsFixed(1)}%',
                    ),
                    if (summary.roi != null)
                      _MetricCard(
                        label: 'ROI',
                        value: '${summary.roi!.toStringAsFixed(0)}%',
                      ),
                  ],
                ),
                const SizedBox(height: 24),
                const _SectionHeader(title: 'Platform breakdown'),
                const SizedBox(height: 8),
                if (analytics.platformBreakdown.isEmpty)
                  const _EmptyCard(message: 'No platform attribution yet.')
                else
                  ...analytics.platformBreakdown.map(
                    (item) => _PlatformBreakdownTile(item: item),
                  ),
                const SizedBox(height: 24),
                const _SectionHeader(title: 'Top posts'),
                const SizedBox(height: 8),
                if (analytics.topPosts.isEmpty)
                  const _EmptyCard(message: 'No top posts for this period.')
                else
                  ...analytics.topPosts.take(5).map(
                        (post) => _TopPostTile(
                          post: post,
                          workflow: _postById(data.recentPosts, post.id),
                          onApprove: _approvePost,
                          onPublish: _publishPost,
                        ),
                      ),
                const SizedBox(height: 24),
                const _SectionHeader(title: 'Recent posts'),
                const SizedBox(height: 8),
                if (data.recentPosts.isEmpty)
                  const _EmptyCard(message: 'No posts yet. Tap New post to create content.')
                else
                  ...data.recentPosts.take(8).map(
                        (post) => _RecentPostTile(
                          post: post,
                          onApprove: _approvePost,
                          onPublish: _publishPost,
                        ),
                      ),
                const SizedBox(height: 16),
              ],
            );
          },
        ),
      ),
    );
  }

}

String formatGrowthMoney(double value) {
  if (value >= 1000) {
    return value.toStringAsFixed(0);
  }
  return value.toStringAsFixed(value == value.roundToDouble() ? 0 : 2);
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Text(
      title,
      style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: (MediaQuery.sizeOf(context).width - 44) / 2,
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: const TextStyle(color: AppColors.textMuted)),
              const SizedBox(height: 8),
              Text(
                value,
                style: const TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.w700,
                  color: AppColors.primaryDark,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _EmptyCard extends StatelessWidget {
  const _EmptyCard({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Text(
          message,
          style: const TextStyle(color: AppColors.textMuted),
        ),
      ),
    );
  }
}

class _PlatformBreakdownTile extends StatelessWidget {
  const _PlatformBreakdownTile({required this.item});

  final PlatformBreakdown item;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: AppColors.bubbleIncoming,
          child: Text(
            _platformInitial(item.platform),
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              color: AppColors.primaryDark,
            ),
          ),
        ),
        title: Text(
          _formatPlatform(item.platform),
          style: const TextStyle(fontWeight: FontWeight.w600),
        ),
        subtitle: Text('${item.leads} leads · ${item.orders} orders'),
        trailing: Text(
          formatGrowthMoney(item.revenue),
          style: const TextStyle(
            fontWeight: FontWeight.w700,
            color: AppColors.primaryDark,
          ),
        ),
      ),
    );
  }
}

class _TopPostTile extends StatelessWidget {
  const _TopPostTile({
    required this.post,
    this.workflow,
    required this.onApprove,
    required this.onPublish,
  });

  final TopPost post;
  final GrowthPost? workflow;
  final Future<void> Function(String id) onApprove;
  final Future<void> Function(String id) onPublish;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                _PlatformChip(platform: post.platform),
                const Spacer(),
                if (post.revenue > 0)
                  Text(
                    formatGrowthMoney(post.revenue),
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      color: AppColors.primary,
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              post.title,
              style: const TextStyle(fontWeight: FontWeight.w600),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: 8),
            Text(
              '${post.clicks} clicks · ${post.leads} leads · ${post.orders} orders',
              style: const TextStyle(color: AppColors.textMuted, fontSize: 13),
            ),
            if (workflow != null &&
                (workflow!.canApprove || workflow!.canPublish)) ...[
              const SizedBox(height: 12),
              _PostActionRow(
                post: workflow!,
                onApprove: onApprove,
                onPublish: onPublish,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _RecentPostTile extends StatelessWidget {
  const _RecentPostTile({
    required this.post,
    required this.onApprove,
    required this.onPublish,
  });

  final GrowthPost post;
  final Future<void> Function(String id) onApprove;
  final Future<void> Function(String id) onPublish;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CircleAvatar(
                  backgroundColor: AppColors.bubbleOutgoing,
                  child: Icon(
                    _statusIcon(post.status),
                    color: AppColors.primaryDark,
                    size: 20,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        post.displayTitle,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        _recentPostSubtitle(post),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: AppColors.textMuted,
                          fontSize: 13,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                _StatusBadge(status: post.status),
              ],
            ),
            if (post.canApprove || post.canPublish) ...[
              const SizedBox(height: 12),
              _PostActionRow(
                post: post,
                onApprove: onApprove,
                onPublish: onPublish,
              ),
            ] else if (post.needsMediaForPublish && post.approvedAt != null) ...[
              const SizedBox(height: 12),
              const Text(
                'Add an image URL before publishing to Instagram.',
                style: TextStyle(color: AppColors.textMuted, fontSize: 13),
              ),
            ],
          ],
        ),
      ),
    );
  }

  static String _recentPostSubtitle(GrowthPost post) {
    final parts = <String>[_formatPlatform(post.platform)];
    final metrics = post.metrics;
    if (metrics != null && metrics.clicks > 0) {
      parts.add('${metrics.clicks} clicks');
    }
    if (post.createdAt != null && post.createdAt!.isNotEmpty) {
      parts.add(_formatDate(post.createdAt!));
    }
    return parts.join(' · ');
  }

  static String _formatDate(String iso) {
    final parsed = DateTime.tryParse(iso);
    if (parsed == null) return iso;
    return '${parsed.year}-${parsed.month.toString().padLeft(2, '0')}-${parsed.day.toString().padLeft(2, '0')}';
  }
}

class _PostActionRow extends StatelessWidget {
  const _PostActionRow({
    required this.post,
    required this.onApprove,
    required this.onPublish,
  });

  final GrowthPost post;
  final Future<void> Function(String id) onApprove;
  final Future<void> Function(String id) onPublish;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        if (post.canApprove)
          OutlinedButton.icon(
            onPressed: () => onApprove(post.id),
            icon: const Icon(Icons.check, size: 18),
            label: const Text('Approve'),
            style: OutlinedButton.styleFrom(
              foregroundColor: AppColors.primary,
              side: const BorderSide(color: AppColors.primary),
            ),
          ),
        if (post.canPublish)
          FilledButton.icon(
            onPressed: () => onPublish(post.id),
            icon: const Icon(Icons.publish, size: 18),
            label: const Text('Publish'),
            style: FilledButton.styleFrom(
              backgroundColor: AppColors.primary,
              foregroundColor: Colors.white,
            ),
          ),
      ],
    );
  }
}

class _PlatformChip extends StatelessWidget {
  const _PlatformChip({required this.platform});

  final String platform;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: AppColors.bubbleIncoming,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        _formatPlatform(platform),
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: AppColors.primaryDark,
        ),
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: _statusColor(status).withOpacity(0.15),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        status,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: _statusColor(status),
        ),
      ),
    );
  }
}

String _formatPlatform(String platform) {
  if (platform.isEmpty) return 'Unknown';
  return platform[0].toUpperCase() + platform.substring(1);
}

String _platformInitial(String platform) {
  final formatted = _formatPlatform(platform);
  return formatted.isNotEmpty ? formatted[0].toUpperCase() : '?';
}

IconData _statusIcon(String status) {
  switch (status) {
    case 'published':
      return Icons.check_circle_outline;
    case 'scheduled':
      return Icons.schedule;
    case 'draft':
      return Icons.edit_outlined;
    default:
      return Icons.article_outlined;
  }
}

Color _statusColor(String status) {
  switch (status) {
    case 'published':
      return AppColors.primary;
    case 'scheduled':
      return AppColors.primaryDark;
    case 'draft':
      return AppColors.textMuted;
    default:
      return AppColors.textMuted;
  }
}
