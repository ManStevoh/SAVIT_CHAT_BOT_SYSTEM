import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/auth/auth_controller.dart';
import '../../core/network/api_exception.dart';
import '../../core/shell/shell_badges.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_fade_slide.dart';
import '../../shared/widgets/app_skeleton.dart';
import '../../shared/widgets/app_state_views.dart';
import '../../shared/widgets/app_surface.dart';
import '../orders/order_detail_screen.dart';
import '../settings/company_settings_controller.dart';
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
    // Ensure currency display prefs are available for revenue formatting.
    // ignore: unawaited_futures
    context.read<CompanySettingsController>().load();
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
    final user = context.watch<AuthController>().user;
    final formatMoney = context.watch<CompanySettingsController>().formatMoney;
    final company = user?.companyName?.trim();
    final greeting = (company != null && company.isNotEmpty)
        ? company
        : 'Your workspace';
    final nameParts = user?.name.trim().split(RegExp(r'\s+')) ?? const <String>[];
    final firstName = nameParts.isNotEmpty ? nameParts.first : null;

    return Scaffold(
      backgroundColor: AppColors.canvas,
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
                  final topPad = MediaQuery.paddingOf(context).top;

                  return ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: EdgeInsets.zero,
                    children: [
                      AppHeroBand(
                        padding: EdgeInsets.fromLTRB(20, topPad + 12, 20, 28),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        firstName != null &&
                                                firstName.isNotEmpty
                                            ? 'Hello, $firstName'
                                            : 'Hello',
                                        style: GoogleFonts.manrope(
                                          fontSize: 14,
                                          fontWeight: FontWeight.w600,
                                          color: Colors.white.withOpacity(0.78),
                                        ),
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        greeting,
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: GoogleFonts.manrope(
                                          fontSize: 24,
                                          fontWeight: FontWeight.w800,
                                          color: Colors.white,
                                          height: 1.15,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                if (data.unreadNotifications > 0)
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 12,
                                      vertical: 8,
                                    ),
                                    decoration: BoxDecoration(
                                      color: Colors.white.withOpacity(0.18),
                                      borderRadius: BorderRadius.circular(
                                        AppRadii.pill,
                                      ),
                                    ),
                                    child: Text(
                                      '${data.unreadNotifications} new',
                                      style: GoogleFonts.manrope(
                                        color: Colors.white,
                                        fontWeight: FontWeight.w700,
                                        fontSize: 12,
                                      ),
                                    ),
                                  ),
                              ],
                            ),
                            const SizedBox(height: 18),
                            Text(
                              'Last 7 days',
                              style: GoogleFonts.manrope(
                                color: Colors.white.withOpacity(0.72),
                                fontWeight: FontWeight.w500,
                                fontSize: 13,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              formatMoney(data.totalRevenue),
                              style: GoogleFonts.manrope(
                                fontSize: 40,
                                fontWeight: FontWeight.w800,
                                color: Colors.white,
                                height: 1.05,
                              ),
                            ),
                            Text(
                              'Revenue signal',
                              style: GoogleFonts.manrope(
                                color: Colors.white.withOpacity(0.72),
                                fontSize: 13,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 20, 16, 8),
                        child: AppFadeSlide(
                          child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Quick actions',
                              style: GoogleFonts.manrope(
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 12),
                            Row(
                              children: [
                                _QuickAction(
                                  icon: Icons.chat_bubble_outline,
                                  label: 'Chats',
                                  color: AppColors.primary,
                                  onTap: () => context.go('/chats'),
                                ),
                                _QuickAction(
                                  icon: Icons.person_add_alt_1_outlined,
                                  label: 'Contact',
                                  color: AppColors.accentTeal,
                                  onTap: () => context.go('/contacts/add'),
                                ),
                                _QuickAction(
                                  icon: Icons.receipt_long_outlined,
                                  label: 'Orders',
                                  color: AppColors.accentAmber,
                                  onTap: () => context.go('/orders'),
                                ),
                                _QuickAction(
                                  icon: Icons.inventory_2_outlined,
                                  label: 'Products',
                                  color: AppColors.accentBlue,
                                  onTap: () => context.go('/more/products'),
                                ),
                              ],
                            ),
                            const SizedBox(height: 22),
                            Text(
                              'Overview',
                              style: GoogleFonts.manrope(
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 12),
                            Wrap(
                              spacing: 12,
                              runSpacing: 12,
                              children: [
                                _MetricCard(
                                  label: 'Messages',
                                  value: '${data.totalMessages}',
                                  icon: Icons.forum_outlined,
                                  color: AppColors.primary,
                                  onTap: () => context.go('/chats'),
                                ),
                                _MetricCard(
                                  label: 'Orders',
                                  value: '${data.totalOrders}',
                                  icon: Icons.shopping_bag_outlined,
                                  color: AppColors.accentAmber,
                                  onTap: () => context.go('/orders'),
                                ),
                                _MetricCard(
                                  label: 'Customers',
                                  value: '${data.totalCustomers}',
                                  icon: Icons.people_outline,
                                  color: AppColors.accentTeal,
                                  onTap: () => context.go('/contacts'),
                                ),
                                _MetricCard(
                                  label: 'Revenue',
                                  value: formatMoney(data.totalRevenue),
                                  icon: Icons.payments_outlined,
                                  color: AppColors.accentBlue,
                                  onTap: () => context.go('/orders'),
                                ),
                              ],
                            ),
                            const SizedBox(height: 24),
                            Row(
                              children: [
                                Text(
                                  'Notifications',
                                  style: GoogleFonts.manrope(
                                    fontSize: 16,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                const Spacer(),
                                if (data.unreadNotifications > 0)
                                  Text(
                                    '${data.unreadNotifications} unread',
                                    style: GoogleFonts.manrope(
                                      color: AppColors.primary,
                                      fontWeight: FontWeight.w700,
                                      fontSize: 13,
                                    ),
                                  ),
                              ],
                            ),
                            const SizedBox(height: 12),
                            if (data.notifications.isEmpty)
                              AppSurface(
                                padding: const EdgeInsets.all(24),
                                child: Column(
                                  children: [
                                    const AppIconChip(
                                      icon: Icons.notifications_none,
                                      color: AppColors.primary,
                                      size: 52,
                                    ),
                                    const SizedBox(height: 14),
                                    Text(
                                      'No notifications yet',
                                      style: GoogleFonts.manrope(
                                        fontWeight: FontWeight.w800,
                                        fontSize: 16,
                                      ),
                                    ),
                                    const SizedBox(height: 6),
                                    Text(
                                      'Order and chat updates will show up here.',
                                      textAlign: TextAlign.center,
                                      style: GoogleFonts.manrope(
                                        color: AppColors.textMuted,
                                      ),
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
                                            builder: (_) => OrderDetailScreen(
                                              orderId: n.orderId!,
                                            ),
                                          ),
                                        );
                                        if (context.mounted) await _reload();
                                      } else {
                                        await _reload();
                                      }
                                    },
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 6,
                                      vertical: 4,
                                    ),
                                    child: ListTile(
                                      leading: AppIconChip(
                                        icon: n.read
                                            ? Icons.notifications_none
                                            : Icons.notifications_active,
                                        color: n.read
                                            ? AppColors.textMuted
                                            : AppColors.primary,
                                        size: 42,
                                      ),
                                      title: Text(
                                        n.title,
                                        style: GoogleFonts.manrope(
                                          fontWeight: n.read
                                              ? FontWeight.w600
                                              : FontWeight.w800,
                                        ),
                                      ),
                                      subtitle: Text(
                                        n.body,
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                        style: GoogleFonts.manrope(
                                          color: AppColors.textMuted,
                                          fontSize: 13,
                                        ),
                                      ),
                                    ),
                                  ),
                                );
                              }),
                            const SizedBox(height: 24),
                          ],
                        ),
                        ),
                      ),
                    ],
                  );
                },
              ),
      ),
    );
  }
}

class _QuickAction extends StatelessWidget {
  const _QuickAction({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadii.md),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Column(
            children: [
              AppIconChip(icon: icon, color: color, size: 52),
              const SizedBox(height: 8),
              Text(
                label,
                style: GoogleFonts.manrope(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: AppColors.ink,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
    required this.onTap,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;
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
            AppIconChip(icon: icon, color: color, size: 40),
            const SizedBox(height: 14),
            Text(
              label,
              style: GoogleFonts.manrope(
                color: AppColors.textMuted,
                fontWeight: FontWeight.w600,
                fontSize: 13,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              value,
              style: GoogleFonts.manrope(
                fontSize: 28,
                fontWeight: FontWeight.w800,
                color: AppColors.ink,
                height: 1.1,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
