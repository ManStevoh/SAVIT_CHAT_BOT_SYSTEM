import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/auth/auth_controller.dart';
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
      appBar: AppBar(title: const Text('More')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        children: [
          if (isAdmin) ...[
            const _SectionLabel('Platform'),
            _MoreTile(
              icon: Icons.admin_panel_settings_outlined,
              title: 'Platform Admin',
              subtitle: 'Companies, health, overview',
              onTap: () => context.go('/more/admin'),
            ),
          ],
          if (!adminOnly) ...[
            const _SectionLabel('Catalog'),
            _MoreTile(
              icon: Icons.inventory_2_outlined,
              title: 'Products',
              subtitle: 'Items, variants, images',
              onTap: () => context.go('/more/products'),
            ),
            _MoreTile(
              icon: Icons.help_outline,
              title: 'FAQs',
              subtitle: 'Bot answers & keywords',
              onTap: () => context.go('/more/faqs'),
            ),
            const _SectionLabel('Growth'),
            _MoreTile(
              icon: Icons.trending_up,
              title: 'Growth',
              subtitle: 'Posts, approve & publish',
              onTap: () => context.go('/more/growth'),
            ),
          ],
          const _SectionLabel('Account'),
          _MoreTile(
            icon: Icons.settings_outlined,
            title: 'Settings',
            subtitle: 'Profile & password',
            onTap: () => context.go('/more/settings'),
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
        style: const TextStyle(
          fontSize: 12,
          letterSpacing: 0.8,
          fontWeight: FontWeight.w700,
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
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: AppSurface(
        onTap: onTap,
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
        child: ListTile(
          leading: Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: AppColors.primary.withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppColors.primary.withOpacity(0.18)),
            ),
            child: Icon(icon, size: 22, color: AppColors.primary),
          ),
          title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
          subtitle: Text(subtitle),
          trailing: const Icon(Icons.chevron_right, color: AppColors.textMuted),
        ),
      ),
    );
  }
}
