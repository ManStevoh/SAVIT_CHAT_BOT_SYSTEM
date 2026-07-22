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

    final items = <(IconData, String, String)>[
      if (isAdmin)
        (Icons.admin_panel_settings_outlined, 'Platform Admin', '/more/admin'),
      if (!adminOnly) ...[
        (Icons.inventory_2_outlined, 'Products', '/more/products'),
        (Icons.help_outline, 'FAQs', '/more/faqs'),
        (Icons.trending_up, 'Growth', '/more/growth'),
      ],
      (Icons.settings_outlined, 'Settings', '/more/settings'),
    ];

    return Scaffold(
      appBar: AppBar(title: const Text('More')),
      body: ListView.separated(
        itemCount: items.length,
        separatorBuilder: (_, __) => const Divider(height: 1),
        itemBuilder: (context, index) {
          final item = items[index];
          return ListTile(
            leading: Icon(item.$1, color: AppColors.primary),
            title: Text(item.$2),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => context.go(item.$3),
          );
        },
      ),
    );
  }
}
