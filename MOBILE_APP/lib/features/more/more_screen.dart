import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/auth/auth_controller.dart';
import '../../core/branding/branding_copy.dart';
import '../../core/config/app_config.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/safe_url.dart';
import '../../shared/widgets/app_surface.dart';

class MoreScreen extends StatelessWidget {
  const MoreScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthController>().user;
    final isAdmin = user?.isPlatformAdmin ?? false;
    final adminOnly = user?.isPlatformAdminOnly ?? false;
    final web = context.read<AppConfig>();

    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: Text(
          'More',
          style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        children: [
          if (isAdmin) ...[
            const _SectionLabel('Platform'),
            _MoreTile(
              icon: Icons.admin_panel_settings_outlined,
              title: 'Platform Admin',
              subtitle: 'Companies, health, overview',
              color: AppColors.primary,
              onTap: () => context.go('/more/admin'),
            ),
          ],
          if (!adminOnly) ...[
            const _SectionLabel('Insights'),
            _MoreTile(
              icon: Icons.insights_outlined,
              title: 'Analytics',
              subtitle: 'Messages, orders, revenue & products',
              color: AppColors.accentBlue,
              onTap: () => context.go('/more/analytics'),
            ),
            _MoreTile(
              icon: Icons.workspace_premium_outlined,
              title: 'Subscription',
              subtitle: 'Plan, usage and invoices',
              color: AppColors.primary,
              onTap: () => context.go('/more/subscription'),
            ),
            const _SectionLabel('Catalog'),
            _MoreTile(
              icon: Icons.inventory_2_outlined,
              title: 'Products',
              subtitle: 'Items, variants, images',
              color: AppColors.accentBlue,
              onTap: () => context.go('/more/products'),
            ),
            _MoreTile(
              icon: Icons.percent_outlined,
              title: 'Taxes',
              subtitle: 'VAT, GST & sales tax rates',
              color: AppColors.accentAmber,
              onTap: () => context.go('/more/taxes'),
            ),
            _MoreTile(
              icon: Icons.payments_outlined,
              title: 'Currency',
              subtitle: 'Symbol & number separators',
              color: AppColors.accentTeal,
              onTap: () => context.go('/more/currency'),
            ),
            _MoreTile(
              icon: Icons.storefront_outlined,
              title: 'Storefront',
              subtitle: 'Store link, COD, delivery & dine-in',
              color: AppColors.primary,
              onTap: () => context.go('/more/storefront'),
            ),
            const _SectionLabel('Commerce'),
            _MoreTile(
              icon: Icons.local_shipping_outlined,
              title: 'Delivery zones',
              subtitle: 'Fees, keywords and coverage',
              color: AppColors.accentTeal,
              onTap: () => context.go('/more/delivery'),
            ),
            _MoreTile(
              icon: Icons.table_restaurant_outlined,
              title: 'Dine-in',
              subtitle: 'Tables and QR order links',
              color: AppColors.accentAmber,
              onTap: () => context.go('/more/dine-in'),
            ),
            _MoreTile(
              icon: Icons.event_available_outlined,
              title: 'Bookings',
              subtitle: 'Meetings, slots & calendar feed',
              color: AppColors.accentTeal,
              onTap: () => context.go('/more/bookings'),
            ),
            _MoreTile(
              icon: Icons.local_offer_outlined,
              title: 'Coupons',
              subtitle: 'Storefront discount codes',
              color: AppColors.accentRose,
              onTap: () => context.go('/more/coupons'),
            ),
            _MoreTile(
              icon: Icons.account_balance_wallet_outlined,
              title: 'Payments',
              subtitle: 'M-Pesa, Paystack, Stripe & COD',
              color: AppColors.accentBlue,
              onTap: () => context.go('/more/payments'),
            ),
            const _SectionLabel('Marketing'),
            _MoreTile(
              icon: Icons.campaign_outlined,
              title: 'Campaigns',
              subtitle: 'WhatsApp broadcasts',
              color: AppColors.primary,
              onTap: () => context.go('/more/campaigns'),
            ),
            _MoreTile(
              icon: Icons.trending_up,
              title: 'Growth',
              subtitle: 'Posts, approve & publish',
              color: AppColors.primary,
              onTap: () => context.go('/more/growth'),
            ),
            _MoreTile(
              icon: Icons.help_outline,
              title: 'FAQs',
              subtitle: 'Bot answers & keywords',
              color: AppColors.accentAmber,
              onTap: () => context.go('/more/faqs'),
            ),
            const _SectionLabel('Workspace'),
            _MoreTile(
              icon: Icons.chat_outlined,
              title: 'WhatsApp',
              subtitle: 'Connection status & quality',
              color: AppColors.success,
              onTap: () => context.go('/more/whatsapp'),
            ),
            _MoreTile(
              icon: Icons.smart_toy_outlined,
              title: 'AI replies',
              subtitle: 'Greeting, tone and learning',
              color: AppColors.primary,
              onTap: () => context.go('/more/ai'),
            ),
            _MoreTile(
              icon: Icons.groups_outlined,
              title: 'Team',
              subtitle: 'Invite agents and share access',
              color: AppColors.accentBlue,
              onTap: () => context.go('/more/team'),
            ),
            _MoreTile(
              icon: Icons.business_outlined,
              title: 'Business',
              subtitle: 'Name, mode, bookings & dine-in',
              color: AppColors.accentTeal,
              onTap: () => context.go('/more/business'),
            ),
            const _SectionLabel('On the web'),
            _MoreTile(
              icon: Icons.auto_graph_outlined,
              title: 'Executive & mission control',
              subtitle: 'Advanced AI consoles stay on web',
              color: AppColors.textMuted,
              onTap: () => openHttpsUrl(web.dashboardUrl('/dashboard/executive')),
            ),
            _MoreTile(
              icon: Icons.psychology_outlined,
              title: 'Cognitive AI',
              subtitle: 'Plans, simulation and memory',
              color: AppColors.textMuted,
              onTap: () => openHttpsUrl(web.dashboardUrl('/dashboard/cognitive')),
            ),
            _MoreTile(
              icon: Icons.store_mall_directory_outlined,
              title: 'Marketplace',
              subtitle: 'Install optional modules',
              color: AppColors.textMuted,
              onTap: () => openHttpsUrl(web.dashboardUrl('/dashboard/marketplace')),
            ),
          ],
          const _SectionLabel('Account'),
          _MoreTile(
            icon: Icons.settings_outlined,
            title: 'Settings',
            subtitle: 'Profile & password',
            color: AppColors.textMuted,
            onTap: () => context.go('/more/settings'),
          ),
          const SizedBox(height: 28),
          Text(
            AppBrandingCopy.productOf,
            textAlign: TextAlign.center,
            style: GoogleFonts.manrope(
              fontSize: 12,
              color: AppColors.textMuted,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            AppBrandingCopy.poweredBy,
            textAlign: TextAlign.center,
            style: GoogleFonts.manrope(
              fontSize: 12,
              color: AppColors.textMuted,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            AppBrandingCopy.companyWebsite.replaceFirst('https://', ''),
            textAlign: TextAlign.center,
            style: GoogleFonts.manrope(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: AppColors.primary,
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 16, 4, 8),
      child: Text(
        label.toUpperCase(),
        style: GoogleFonts.manrope(
          fontSize: 12,
          letterSpacing: 0.8,
          fontWeight: FontWeight.w800,
          color: AppColors.textMuted,
        ),
      ),
    );
  }
}

class _MoreTile extends StatelessWidget {
  const _MoreTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: AppSurface(
        onTap: onTap,
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
        child: ListTile(
          leading: AppIconChip(icon: icon, color: color, size: 44),
          title: Text(
            title,
            style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
          ),
          subtitle: Text(
            subtitle,
            style: GoogleFonts.manrope(
              color: AppColors.textMuted,
              fontSize: 13,
            ),
          ),
          trailing: const Icon(
            Icons.chevron_right_rounded,
            color: AppColors.textMuted,
          ),
        ),
      ),
    );
  }
}
