import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../orders/order_detail_screen.dart';
import '../shell/active_shell_branch.dart';
import 'home_repository.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  late Future<HomeOverview> _future;
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    _future = context.read<HomeRepository>().load();
    _poll = Timer.periodic(const Duration(seconds: 20), (_) => _silentReload());
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
    await _future;
  }

  Future<void> _silentReload() async {
    if (!mounted) return;
    if (ActiveShellBranch.maybeOf(context) != 0) return;
    try {
      final data = await context.read<HomeRepository>().load();
      if (mounted) setState(() => _future = Future.value(data));
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Home')),
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<HomeOverview>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              final message = snapshot.error is ApiException
                  ? (snapshot.error as ApiException).message
                  : snapshot.error.toString();
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(24),
                children: [
                  const SizedBox(height: 80),
                  Text(message, textAlign: TextAlign.center),
                ],
              );
            }

            final data = snapshot.data!;
            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              children: [
                const Text(
                  'Admin overview',
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Last 7 days',
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
                      onTap: () => context.go('/more/growth'),
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
                  const Card(
                    child: Padding(
                      padding: EdgeInsets.all(20),
                      child: Text(
                        'No notifications yet.',
                        style: TextStyle(color: AppColors.textMuted),
                      ),
                    ),
                  )
                else
                  ...data.notifications.take(8).map((n) {
                    return Card(
                      child: ListTile(
                        leading: Icon(
                          n.read ? Icons.notifications_none : Icons.notifications_active,
                          color: AppColors.primary,
                        ),
                        title: Text(n.title),
                        subtitle: Text(n.body, maxLines: 2, overflow: TextOverflow.ellipsis),
                        onTap: () async {
                          if (!n.read) {
                            try {
                              await context.read<HomeRepository>().markNotificationRead(n.id);
                            } catch (_) {}
                          }
                          if (!context.mounted) return;
                          if (n.chatId != null) {
                            context.go('/chats/${n.chatId}');
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
      child: Card(
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(16),
          child: Padding(
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
                    fontWeight: FontWeight.w700,
                    color: AppColors.primaryDark,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
