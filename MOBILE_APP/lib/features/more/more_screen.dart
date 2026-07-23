import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/auth/auth_controller.dart';
import '../../core/theme/app_theme.dart';

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
          const SizedBox(height: 24),
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
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 8),
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
    return ListTile(
      leading: CircleAvatar(
        backgroundColor: AppColors.bubbleIncoming,
        foregroundColor: AppColors.primaryDark,
        child: Icon(icon, size: 22),
      ),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
      subtitle: Text(subtitle),
      trailing: const Icon(Icons.chevron_right, color: AppColors.textMuted),
      onTap: onTap,
    );
  }
}
