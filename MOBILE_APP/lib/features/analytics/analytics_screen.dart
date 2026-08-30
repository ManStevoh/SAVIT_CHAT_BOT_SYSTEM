import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';
import '../companion/companion_models.dart';
import '../companion/companion_repository.dart';
import '../settings/company_settings_controller.dart';

class AnalyticsScreen extends StatefulWidget {
  const AnalyticsScreen({super.key});

  @override
  State<AnalyticsScreen> createState() => _AnalyticsScreenState();
}

class _AnalyticsScreenState extends State<AnalyticsScreen> {
  String _period = '7d';
  late Future<AnalyticsSnapshot> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<CompanionRepository>().analytics(period: _period);
    context.read<CompanySettingsController>().load();
  }

  void _setPeriod(String period) {
    setState(() {
      _period = period;
      _future = context.read<CompanionRepository>().analytics(period: period);
    });
  }

  @override
  Widget build(BuildContext context) {
    final money = context.watch<CompanySettingsController>().settings.moneyFormatter;
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: Text('Analytics', style: GoogleFonts.manrope(fontWeight: FontWeight.w800)),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
            child: Row(
              children: [
                for (final item in const [
                  ('7d', '7 days'),
                  ('30d', '30 days'),
                  ('90d', '90 days'),
                ])
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(item.$2),
                      selected: _period == item.$1,
                      onSelected: (_) => _setPeriod(item.$1),
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: FutureBuilder<AnalyticsSnapshot>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) {
                  return const Center(child: CircularProgressIndicator());
                }
                if (snapshot.hasError) {
                  final message = snapshot.error is ApiException
                      ? (snapshot.error! as ApiException).message
                      : 'Analytics is not available on this plan.';
                  return Padding(
                    padding: const EdgeInsets.all(16),
                    child: AppSurface(
                      padding: const EdgeInsets.all(20),
                      child: Text(message, style: GoogleFonts.manrope(color: AppColors.textMuted)),
                    ),
                  );
                }
                final data = snapshot.data!;
                return ListView(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
                  children: [
                    _MetricGrid(data: data, revenue: money.format(data.totalRevenue)),
                    const SizedBox(height: 20),
                    Text('Top products', style: GoogleFonts.manrope(fontWeight: FontWeight.w800, fontSize: 16)),
                    const SizedBox(height: 10),
                    if (data.topProducts.isEmpty)
                      AppSurface(
                        padding: const EdgeInsets.all(18),
                        child: Text('No product sales in this period.', style: GoogleFonts.manrope(color: AppColors.textMuted)),
                      )
                    else
                      ...data.topProducts.map(
                        (p) => Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: AppSurface(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                            child: ListTile(
                              title: Text(p.name, style: GoogleFonts.manrope(fontWeight: FontWeight.w800)),
                              subtitle: Text('${p.sales} sold', style: GoogleFonts.manrope(color: AppColors.textMuted)),
                              trailing: Text(
                                money.format(p.revenue),
                                style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
                              ),
                            ),
                          ),
                        ),
                      ),
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _MetricGrid extends StatelessWidget {
  const _MetricGrid({required this.data, required this.revenue});

  final AnalyticsSnapshot data;
  final String revenue;

  @override
  Widget build(BuildContext context) {
    final tiles = [
      ('Messages', '${data.totalMessages}', data.messagesChange, AppColors.accentBlue),
      ('Orders', '${data.totalOrders}', data.ordersChange, AppColors.primary),
      ('Customers', '${data.totalCustomers}', data.customersChange, AppColors.accentTeal),
      ('Revenue', revenue, data.revenueChange, AppColors.accentAmber),
    ];
    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 10,
      crossAxisSpacing: 10,
      childAspectRatio: 1.35,
      children: tiles
          .map(
            (t) => AppSurface(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(t.$1, style: GoogleFonts.manrope(color: AppColors.textMuted, fontWeight: FontWeight.w600)),
                  const Spacer(),
                  Text(t.$2, style: GoogleFonts.manrope(fontWeight: FontWeight.w800, fontSize: 22, color: t.$4)),
                  Text(
                    '${t.$3 >= 0 ? '+' : ''}${t.$3.toStringAsFixed(1)}% vs prior',
                    style: GoogleFonts.manrope(
                      fontSize: 11,
                      color: t.$3 >= 0 ? AppColors.success : AppColors.accentRose,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
          )
          .toList(),
    );
  }
}
