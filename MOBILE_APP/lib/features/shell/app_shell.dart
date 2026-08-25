import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/auth/auth_controller.dart';
import '../../core/shell/shell_badges.dart';
import '../../core/theme/app_theme.dart';
import 'active_shell_branch.dart';

class AppShell extends StatelessWidget {
  const AppShell({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  void _onTap(int index) {
    navigationShell.goBranch(
      index,
      initialLocation: index == navigationShell.currentIndex,
    );
  }

  @override
  Widget build(BuildContext context) {
    final adminOnly =
        context.watch<AuthController>().user?.isPlatformAdminOnly ?? false;
    final loc = GoRouterState.of(context).uri.path;
    final badges = context.watch<ShellBadges>();

    if (adminOnly) {
      final onSettings = loc.startsWith('/more/settings');
      return Scaffold(
        backgroundColor: AppColors.canvas,
        body: navigationShell,
        bottomNavigationBar: _FloatingNav(
          child: NavigationBar(
            selectedIndex: onSettings ? 1 : 0,
            onDestinationSelected: (index) {
              context.go(index == 0 ? '/more/admin' : '/more/settings');
            },
            destinations: const [
              NavigationDestination(
                icon: Icon(Icons.admin_panel_settings_outlined),
                selectedIcon: Icon(Icons.admin_panel_settings),
                label: 'Admin',
              ),
              NavigationDestination(
                icon: Icon(Icons.settings_outlined),
                selectedIcon: Icon(Icons.settings),
                label: 'Settings',
              ),
            ],
          ),
        ),
      );
    }

    return ActiveShellBranch(
      index: navigationShell.currentIndex,
      child: Scaffold(
        backgroundColor: AppColors.canvas,
        body: navigationShell,
        bottomNavigationBar: _FloatingNav(
          child: NavigationBar(
            selectedIndex: navigationShell.currentIndex,
            onDestinationSelected: _onTap,
            destinations: [
              NavigationDestination(
                icon: _BadgeIcon(
                  icon: Icons.home_outlined,
                  count: badges.unreadNotifications,
                ),
                selectedIcon: _BadgeIcon(
                  icon: Icons.home,
                  count: badges.unreadNotifications,
                  selected: true,
                ),
                label: 'Home',
              ),
              NavigationDestination(
                icon: _BadgeIcon(
                  icon: Icons.chat_bubble_outline,
                  count: badges.unreadChats,
                ),
                selectedIcon: _BadgeIcon(
                  icon: Icons.chat_bubble,
                  count: badges.unreadChats,
                  selected: true,
                ),
                label: 'Chats',
              ),
              const NavigationDestination(
                icon: Icon(Icons.people_outline),
                selectedIcon: Icon(Icons.people),
                label: 'Contacts',
              ),
              const NavigationDestination(
                icon: Icon(Icons.receipt_long_outlined),
                selectedIcon: Icon(Icons.receipt_long),
                label: 'Orders',
              ),
              const NavigationDestination(
                icon: Icon(Icons.menu),
                selectedIcon: Icon(Icons.menu_open),
                label: 'More',
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _FloatingNav extends StatelessWidget {
  const _FloatingNav({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 10),
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(AppRadii.xl),
          boxShadow: AppShadows.floated,
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(AppRadii.xl),
          child: SafeArea(
            top: false,
            child: child,
          ),
        ),
      ),
    );
  }
}

class _BadgeIcon extends StatelessWidget {
  const _BadgeIcon({
    required this.icon,
    required this.count,
    this.selected = false,
  });

  final IconData icon;
  final int count;
  final bool selected;

  @override
  Widget build(BuildContext context) {
    final child = Icon(
      icon,
      color: selected ? AppColors.primary : AppColors.textMuted,
    );
    if (count <= 0) return child;

    final label = count > 99 ? '99+' : '$count';
    return Badge(
      backgroundColor: AppColors.accentRose,
      label: Text(label, style: const TextStyle(fontSize: 10)),
      child: child,
    );
  }
}
