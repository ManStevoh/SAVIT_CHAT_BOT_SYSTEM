import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/auth/auth_controller.dart';
import '../../core/branding/branding_copy.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';

class MoreScreen extends StatelessWidget {
  const MoreScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthController>().user;
    final isAdmin = user?.isPlatformAdmin ?? false;
    final adminOnly = user?.isPlatformAdminOnly ?? false;

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
            _MoreTile(
              icon: Icons.event_available_outlined,
              title: 'Bookings',
              subtitle: 'Meetings & calendar feed',
              color: AppColors.accentTeal,
              onTap: () => context.go('/more/bookings'),
            ),
            _MoreTile(
              icon: Icons.help_outline,
              title: 'FAQs',
              subtitle: 'Bot answers & keywords',
              color: AppColors.accentAmber,
              onTap: () => context.go('/more/faqs'),
            ),
            const _SectionLabel('Growth'),
            _MoreTile(
              icon: Icons.trending_up,
              title: 'Growth',
              subtitle: 'Posts, approve & publish',
              color: AppColors.primary,
              onTap: () => context.go('/more/growth'),
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
