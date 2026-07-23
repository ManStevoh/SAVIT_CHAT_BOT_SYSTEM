import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/auth/auth_controller.dart';
import '../../core/network/api_exception.dart';
import '../../core/shell/shell_badges.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_skeleton.dart';
import '../../shared/widgets/app_state_views.dart';
import '../../shared/widgets/app_surface.dart';
import '../orders/order_detail_screen.dart';
import '../shell/active_shell_branch.dart';
import 'home_repository.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  Future<HomeOverview>? _future;
  Timer? _poll;

  bool get _isAdminOnly =>
      context.read<AuthController>().user?.isPlatformAdminOnly ?? false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted || _isAdminOnly) return;
      await _reload();
      if (!mounted) return;
      _poll = Timer.periodic(const Duration(seconds: 20), (_) => _silentReload());
    });
  }

  @override
  void dispose() {
    _poll?.cancel();
    super.dispose();
  }

  Future<void> _reload() async {
    setState(() {
      _future = context.read<HomeRepository>().load();
    });
    final data = await _future;
    if (mounted && data != null) {
      context.read<ShellBadges>().setUnreadNotifications(data.unreadNotifications);
    }
  }

  Future<void> _silentReload() async {
    if (!mounted || _isAdminOnly) return;
    final onHome = ActiveShellBranch.maybeOf(context) == 0;
    try {
      final data = await context.read<HomeRepository>().load();
      if (!mounted) return;
      context.read<ShellBadges>().setUnreadNotifications(data.unreadNotifications);
      if (onHome) {
        setState(() => _future = Future.value(data));
      }
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final company =
        context.watch<AuthController>().user?.companyName?.trim();
    final greeting = (company != null && company.isNotEmpty)
        ? company
        : 'Your workspace';

    return Scaffold(
      appBar: AppBar(title: const Text('Home')),
      body: RefreshIndicator(
        onRefresh: _reload,
        child: _future == null
            ? const HomeSkeleton()
            : FutureBuilder<HomeOverview>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting &&
                !snapshot.hasData) {
              return const HomeSkeleton();
            }
            if (snapshot.hasError) {
              final message = snapshot.error is ApiException
                  ? (snapshot.error as ApiException).message
                  : snapshot.error.toString();
              return AppErrorState(message: message, onRetry: _reload);
            }

            final data = snapshot.data!;

            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              children: [
                Text(
                  greeting,
                  style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.w800,
                    color: AppColors.ink,
                  ),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Last 7 days · tap a metric to open',
                  style: TextStyle(color: AppColors.textMuted),
                ),
                const SizedBox(height: 16),
                Wrap(
                  spacing: 12,
                  runSpacing: 12,
                  children: [
                    _MetricCard(
                      label: 'Messages',
                      value: '${data.totalMessages}',
                      onTap: () => context.go('/chats'),
                    ),
                    _MetricCard(
                      label: 'Orders',
                      value: '${data.totalOrders}',
                      onTap: () => context.go('/orders'),
                    ),
                    _MetricCard(
                      label: 'Customers',
                      value: '${data.totalCustomers}',
                      onTap: () => context.go('/contacts'),
                    ),
                    _MetricCard(
                      label: 'Revenue',
                      value: data.totalRevenue.toStringAsFixed(0),
                      onTap: () => context.go('/orders'),
                    ),
                  ],
                ),
                const SizedBox(height: 24),
                Row(
                  children: [
                    const Text(
                      'Notifications',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                    ),
                    const Spacer(),
                    if (data.unreadNotifications > 0)
                      Text(
                        '${data.unreadNotifications} unread',
                        style: const TextStyle(color: AppColors.primary),
                      ),
                  ],
                ),
                const SizedBox(height: 8),
                if (data.notifications.isEmpty)
                  const AppSurface(
                    padding: EdgeInsets.all(20),
                    child: Column(
                      children: [
                        Icon(
                          Icons.notifications_none,
                          color: AppColors.primary,
                          size: 32,
                        ),
                        SizedBox(height: 10),
                        Text(
                          'No notifications yet',
                          style: TextStyle(fontWeight: FontWeight.w700),
                        ),
                        SizedBox(height: 4),
                        Text(
                          'Order and chat updates will show up here.',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: AppColors.textMuted),
                        ),
                      ],
                    ),
                  )
                else
                  ...data.notifications.take(8).map((n) {
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: AppSurface(
                        onTap: () async {
                          if (!n.read) {
                            context
                                .read<ShellBadges>()
                                .decrementUnreadNotifications();
                            try {
                              await context
                                  .read<HomeRepository>()
                                  .markNotificationRead(n.id);
                            } catch (_) {}
                          }
                          if (!context.mounted) return;
                          if (n.chatId != null) {
                            context.go(
                              '/chats/${n.chatId}',
                              extra: {'name': n.title},
                            );
                          } else if (n.orderId != null) {
                            await Navigator.of(context).push(
                              MaterialPageRoute<void>(
                                builder: (_) =>
                                    OrderDetailScreen(orderId: n.orderId!),
                              ),
                            );
                            if (context.mounted) await _reload();
                          } else {
                            await _reload();
                          }
                        },
                        padding: const EdgeInsets.symmetric(
                          horizontal: 4,
                          vertical: 2,
                        ),
                        child: ListTile(
                          leading: Icon(
                            n.read
                                ? Icons.notifications_none
                                : Icons.notifications_active,
                            color: AppColors.primary,
                          ),
                          title: Text(
                            n.title,
                            style: TextStyle(
                              fontWeight:
                                  n.read ? FontWeight.w500 : FontWeight.w700,
                            ),
                          ),
                          subtitle: Text(
                            n.body,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
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
    required this.onTap,
  });

  final String label;
  final String value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: (MediaQuery.sizeOf(context).width - 44) / 2,
      child: AppSurface(
        onTap: onTap,
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
                fontWeight: FontWeight.w800,
                color: AppColors.ink,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
