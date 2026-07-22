import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import 'admin_models.dart';
import 'admin_repository.dart';

class AdminHomeScreen extends StatefulWidget {
  const AdminHomeScreen({super.key});

  @override
  State<AdminHomeScreen> createState() => _AdminHomeScreenState();
}

class _AdminHomeScreenState extends State<AdminHomeScreen> {
  late final AdminRepository _repository;
  late Future<AdminDashboard> _future;

  @override
  void initState() {
    super.initState();
    _repository = context.read<AdminRepository>();
    _future = _repository.loadDashboard();
  }

  Future<void> _reload() async {
    setState(() {
      _future = _repository.loadDashboard();
    });
    await _future;
  }

  String _formatChange(double value) {
    final prefix = value > 0 ? '+' : '';
    return '$prefix${value.toStringAsFixed(1)}%';
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'active':
        return Colors.green.shade700;
      case 'suspended':
        return Colors.red.shade700;
      default:
        return AppColors.textMuted;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Platform Admin')),
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<AdminDashboard>(
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
            final overview = data.overview;
            final health = data.health;

            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              children: [
                const Text(
                  'Platform overview',
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 4),
                Text(
                  '${overview.activeCompanies} active of ${overview.totalCompanies} companies',
                  style: const TextStyle(color: AppColors.textMuted),
                ),
                const SizedBox(height: 16),
                Wrap(
                  spacing: 12,
                  runSpacing: 12,
                  children: [
                    _MetricCard(
                      label: 'Companies',
                      value: '${overview.totalCompanies}',
                      subtitle: _formatChange(overview.companiesChange),
                    ),
                    _MetricCard(
                      label: 'Users',
                      value: '${overview.totalUsers}',
                      subtitle: _formatChange(overview.usersChange),
                    ),
                    _MetricCard(
                      label: 'Revenue',
                      value: overview.totalRevenue.toStringAsFixed(0),
                      subtitle: _formatChange(overview.revenueChange),
                    ),
                    _MetricCard(
                      label: 'Messages',
                      value: '${overview.totalMessages}',
                      subtitle: _formatChange(overview.messagesChange),
                    ),
                  ],
                ),
                const SizedBox(height: 24),
                const Text(
                  'System health',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 8),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(
                              health.queue.healthy ? Icons.check_circle : Icons.warning_amber_rounded,
                              color: health.queue.healthy ? Colors.green.shade700 : Colors.orange.shade700,
                            ),
                            const SizedBox(width: 8),
                            Text(
                              health.queue.healthy ? 'Queue healthy' : 'Queue needs attention',
                              style: const TextStyle(fontWeight: FontWeight.w600),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Text(
                          'Pending jobs: ${health.queue.pending}',
                          style: const TextStyle(color: AppColors.textMuted),
                        ),
                        Text(
                          'Failed jobs: ${health.queue.failed}',
                          style: const TextStyle(color: AppColors.textMuted),
                        ),
                        Text(
                          'Meta OAuth: ${health.integrations.metaOAuthConfigured ? 'configured' : 'not configured'}',
                          style: const TextStyle(color: AppColors.textMuted),
                        ),
                        if (health.integrations.expiringTokens > 0)
                          Text(
                            'Expiring tokens: ${health.integrations.expiringTokens}',
                            style: const TextStyle(color: AppColors.textMuted),
                          ),
                        if (health.alerts.isNotEmpty) ...[
                          const SizedBox(height: 12),
                          const Divider(height: 1),
                          const SizedBox(height: 12),
                          ...health.alerts.map(
                            (alert) => Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Icon(Icons.info_outline, size: 16, color: AppColors.primary),
                                  const SizedBox(width: 8),
                                  Expanded(child: Text(alert)),
                                ],
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 24),
                Row(
                  children: [
                    const Text(
                      'Recent companies',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                    ),
                    const Spacer(),
                    Text(
                      '${data.companies.length} shown',
                      style: const TextStyle(color: AppColors.textMuted, fontSize: 12),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                if (data.companies.isEmpty)
                  const Card(
                    child: Padding(
                      padding: EdgeInsets.all(20),
                      child: Text(
                        'No companies found.',
                        style: TextStyle(color: AppColors.textMuted),
                      ),
                    ),
                  )
                else
                  ...data.companies.map((company) {
                    return Card(
                      child: ListTile(
                        leading: CircleAvatar(
                          backgroundColor: AppColors.primary.withOpacity(0.12),
                          child: Text(
                            company.name.isNotEmpty ? company.name[0].toUpperCase() : '?',
                            style: const TextStyle(
                              color: AppColors.primaryDark,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                        title: Text(company.name),
                        subtitle: Text(
                          '${company.plan} · ${company.totalOrders} orders · ${company.totalChats} chats',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        trailing: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text(
                              company.status,
                              style: TextStyle(
                                color: _statusColor(company.status),
                                fontWeight: FontWeight.w600,
                                fontSize: 12,
                              ),
                            ),
                            if (company.whatsappConnected)
                              const Icon(Icons.chat, size: 14, color: AppColors.primary),
                          ],
                        ),
                      ),
                    );
                  }),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.label,
    required this.value,
    required this.subtitle,
  });

  final String label;
  final String value;
  final String subtitle;

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
                  fontSize: 28,
                  fontWeight: FontWeight.w700,
                  color: AppColors.primaryDark,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                subtitle,
                style: TextStyle(
                  fontSize: 12,
                  color: subtitle.startsWith('-') ? Colors.red.shade700 : Colors.green.shade700,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
