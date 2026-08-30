import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/config/app_config.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/safe_url.dart';
import '../../shared/widgets/app_surface.dart';
import '../companion/companion_models.dart';
import '../companion/companion_repository.dart';

class SubscriptionScreen extends StatefulWidget {
  const SubscriptionScreen({super.key});

  @override
  State<SubscriptionScreen> createState() => _SubscriptionScreenState();
}

class _SubscriptionScreenState extends State<SubscriptionScreen> {
  late Future<_BillingPage> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<_BillingPage> _load() async {
    final repo = context.read<CompanionRepository>();
    final results = await Future.wait([
      repo.subscription(),
      repo.usage(),
      repo.invoices(),
    ]);
    return _BillingPage(
      subscription: results[0] as SubscriptionOverview,
      usage: results[1] as List<UsageMeter>,
      invoices: results[2] as List<BillingInvoice>,
    );
  }

  Future<void> _reload() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _cancel() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cancel subscription?'),
        content: const Text('You keep access until the current period ends. You can resubscribe anytime.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Keep plan')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Cancel plan')),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    try {
      final message = await context.read<CompanionRepository>().cancelSubscription();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
      await _reload();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: Text('Subscription', style: GoogleFonts.manrope(fontWeight: FontWeight.w800)),
        actions: [
          IconButton(onPressed: _reload, icon: const Icon(Icons.refresh)),
        ],
      ),
      body: FutureBuilder<_BillingPage>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            final message = snapshot.error is ApiException
                ? (snapshot.error! as ApiException).message
                : 'Could not load billing.';
            return Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(message)));
          }
          final page = snapshot.data!;
          final sub = page.subscription;
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
            children: [
              AppSurface(
                padding: const EdgeInsets.all(18),
                color: AppColors.primarySoft,
                elevation: false,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(sub.planName, style: GoogleFonts.manrope(fontWeight: FontWeight.w800, fontSize: 22)),
                    const SizedBox(height: 4),
                    Text(
                      '${sub.status.toUpperCase()} · ${sub.accessEndsLabel} ${sub.endDate}',
                      style: GoogleFonts.manrope(color: AppColors.textMuted, fontWeight: FontWeight.w600),
                    ),
                    if (sub.isExpiringSoon)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Text(
                          '${sub.daysRemaining} days remaining',
                          style: GoogleFonts.manrope(color: AppColors.accentAmber, fontWeight: FontWeight.w800),
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: () => openHttpsUrl(
                  context.read<AppConfig>().dashboardUrl('/dashboard/subscription'),
                ),
                icon: const Icon(Icons.open_in_new),
                label: const Text('Manage billing on web'),
              ),
              if (sub.status == 'active' || sub.status == 'trial')
                TextButton(onPressed: _cancel, child: const Text('Cancel subscription')),
              const SizedBox(height: 18),
              Text('Usage this period', style: GoogleFonts.manrope(fontWeight: FontWeight.w800, fontSize: 16)),
              const SizedBox(height: 10),
              ...page.usage.map(
                (m) => Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: AppSurface(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(child: Text(m.name, style: GoogleFonts.manrope(fontWeight: FontWeight.w700))),
                            Text(
                              m.unlimited ? '${m.used} · unlimited' : '${m.used} / ${m.limit}',
                              style: GoogleFonts.manrope(color: AppColors.textMuted, fontSize: 13),
                            ),
                          ],
                        ),
                        if (!m.unlimited) ...[
                          const SizedBox(height: 8),
                          ClipRRect(
                            borderRadius: BorderRadius.circular(99),
                            child: LinearProgressIndicator(
                              value: m.progress,
                              minHeight: 7,
                              backgroundColor: AppColors.canvasDeep,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              Text('Invoices', style: GoogleFonts.manrope(fontWeight: FontWeight.w800, fontSize: 16)),
              const SizedBox(height: 10),
              if (page.invoices.isEmpty)
                AppSurface(
                  padding: const EdgeInsets.all(18),
                  child: Text('No paid invoices yet.', style: GoogleFonts.manrope(color: AppColors.textMuted)),
                )
              else
                ...page.invoices.map(
                  (inv) => Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: AppSurface(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      child: ListTile(
                        title: Text(inv.amount, style: GoogleFonts.manrope(fontWeight: FontWeight.w800)),
                        subtitle: Text('${inv.date} · ${inv.gateway ?? inv.status}'),
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

class _BillingPage {
  const _BillingPage({
    required this.subscription,
    required this.usage,
    required this.invoices,
  });

  final SubscriptionOverview subscription;
  final List<UsageMeter> usage;
  final List<BillingInvoice> invoices;
}
