import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/auth/auth_controller.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';
import '../settings/company_settings_controller.dart';
import 'order_detail_screen.dart' show OrderDetailScreen, OrderStatusChip;
import 'create_order_screen.dart';
import 'order_models.dart';
import 'order_repository.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  final _orders = <Order>[];
  int _page = 1;
  int _totalPages = 1;
  int _total = 0;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final adminOnly =
          context.read<AuthController>().user?.isPlatformAdminOnly ?? false;
      if (adminOnly) return;
      _reload();
    });
  }

  Future<void> _reload() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final result = await context.read<OrderRepository>().listOrders(page: 1);
      if (!mounted) return;
      setState(() {
        _orders
          ..clear()
          ..addAll(result.orders);
        _page = result.page;
        _totalPages = result.totalPages;
        _total = result.total;
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _loadMore() async {
    if (_loadingMore || _page >= _totalPages) return;
    setState(() => _loadingMore = true);
    try {
      final next = _page + 1;
      final result =
          await context.read<OrderRepository>().listOrders(page: next);
      if (!mounted) return;
      setState(() {
        _orders.addAll(result.orders);
        _page = result.page;
        _totalPages = result.totalPages;
        _total = result.total;
        _loadingMore = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _loadingMore = false);
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    } catch (e) {
      if (!mounted) return;
      setState(() => _loadingMore = false);
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text('$e')));
    }
  }

  Future<void> _openOrder(Order order) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => OrderDetailScreen(orderId: order.id),
      ),
    );
    if (mounted) await _reload();
  }

  Future<void> _createOrder() async {
    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => const CreateOrderScreen(),
      ),
    );
    if (created == true && mounted) await _reload();
    // Detail pushReplacement also returns here without true — refresh anyway.
    if (mounted) await _reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: Text(
          'Orders',
          style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _createOrder,
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add),
        label: const Text('New order'),
      ),
      body: RefreshIndicator(
        onRefresh: _reload,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_loading && _orders.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null && _orders.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(24),
        children: [
          const SizedBox(height: 120),
          Icon(Icons.error_outline, color: Colors.red.shade300, size: 40),
          const SizedBox(height: 12),
          Text(_error!, textAlign: TextAlign.center),
          const SizedBox(height: 16),
          Center(
            child: FilledButton(
              onPressed: _reload,
              child: const Text('Try again'),
            ),
          ),
        ],
      );
    }

    if (_orders.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(24),
        children: [
          const SizedBox(height: 80),
          AppSurface(
            padding: const EdgeInsets.all(28),
            child: Column(
              children: [
                const AppIconChip(
                  icon: Icons.receipt_long_outlined,
                  color: AppColors.accentAmber,
                  size: 56,
                ),
                const SizedBox(height: 14),
                Text(
                  'No orders yet',
                  style: GoogleFonts.manrope(
                    fontWeight: FontWeight.w800,
                    fontSize: 18,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  'Raise an order for a WhatsApp customer, or wait for checkout from chat.',
                  textAlign: TextAlign.center,
                  style: GoogleFonts.manrope(color: AppColors.textMuted),
                ),
                const SizedBox(height: 16),
                FilledButton.icon(
                  onPressed: _createOrder,
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.primary,
                  ),
                  icon: const Icon(Icons.add),
                  label: const Text('New order'),
                ),
              ],
            ),
          ),
        ],
      );
    }

    final canLoadMore = _page < _totalPages;
    final itemCount = _orders.length + (canLoadMore ? 1 : 0) + 1;

    return ListView.separated(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 88),
      itemCount: itemCount,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (context, index) {
        if (index == 0) {
          return AppSurface(
            padding: const EdgeInsets.all(18),
            color: AppColors.primarySoft,
            elevation: false,
            child: Row(
              children: [
                const AppIconChip(
                  icon: Icons.receipt_long,
                  color: AppColors.primary,
                  size: 44,
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '$_total orders',
                        style: GoogleFonts.manrope(
                          fontWeight: FontWeight.w800,
                          fontSize: 20,
                        ),
                      ),
                      Text(
                        'Tap a card to update status or payment',
                        style: GoogleFonts.manrope(
                          color: AppColors.textMuted,
                          fontSize: 13,
                        ),
                      ),
                      Text(
                        'Use New order to raise an invoice for a chat',
                        style: GoogleFonts.manrope(
                          color: AppColors.textMuted,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          );
        }

        final orderIndex = index - 1;
        if (orderIndex >= _orders.length) {
          return Padding(
            padding: const EdgeInsets.symmetric(vertical: 8),
            child: Column(
              children: [
                if (_total > 0)
                  Text(
                    'Showing ${_orders.length} of $_total',
                    style: GoogleFonts.manrope(
                      color: AppColors.textMuted,
                      fontSize: 12,
                    ),
                  ),
                const SizedBox(height: 8),
                OutlinedButton(
                  onPressed: _loadingMore ? null : _loadMore,
                  child: _loadingMore
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Load more'),
                ),
              ],
            ),
          );
        }

        final order = _orders[orderIndex];
        return _OrderListCard(
          order: order,
          onTap: () => _openOrder(order),
        );
      },
    );
  }
}

class _OrderListCard extends StatelessWidget {
  const _OrderListCard({
    required this.order,
    required this.onTap,
  });

  final Order order;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final productCount = order.products.length;
    final subtitle = productCount == 1
        ? order.products.first.name
        : '$productCount items';
    final formatMoney = context.watch<CompanySettingsController>().formatMoney;

    return AppSurface(
      onTap: onTap,
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  order.orderNumber.isNotEmpty
                      ? order.orderNumber
                      : 'Order #${order.id}',
                  style: GoogleFonts.manrope(
                    fontWeight: FontWeight.w800,
                    color: AppColors.primaryDark,
                  ),
                ),
              ),
              OrderStatusChip(status: order.status),
            ],
          ),
          const SizedBox(height: 8),
          Align(
            alignment: Alignment.centerLeft,
            child: _ListPaymentChip(status: order.paymentStatus),
          ),
          const SizedBox(height: 10),
          Text(
            order.customerName,
            style: GoogleFonts.manrope(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 4),
          Text(
            subtitle,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: GoogleFonts.manrope(
              color: AppColors.textMuted,
              fontSize: 13,
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Text(
                formatMoney(order.total),
                style: GoogleFonts.manrope(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  color: AppColors.primary,
                ),
              ),
              const Spacer(),
              Text(
                formatOrderDate(order.createdAt),
                style: GoogleFonts.manrope(
                  color: AppColors.textMuted,
                  fontSize: 12,
                ),
              ),
              const SizedBox(width: 4),
              const Icon(
                Icons.chevron_right_rounded,
                color: AppColors.textMuted,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ListPaymentChip extends StatelessWidget {
  const _ListPaymentChip({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final (background, foreground, label) = switch (status) {
      'paid' => (
          const Color(0xFFE8F5E9),
          const Color(0xFF2E7D32),
          'Paid',
        ),
      'refunded' => (
          const Color(0xFFFFF3E0),
          const Color(0xFFE65100),
          'Refunded',
        ),
      _ => (AppColors.primarySoft, AppColors.primaryDark, 'Unpaid'),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: GoogleFonts.manrope(
          fontSize: 12,
          fontWeight: FontWeight.w700,
          color: foreground,
        ),
      ),
    );
  }
}
