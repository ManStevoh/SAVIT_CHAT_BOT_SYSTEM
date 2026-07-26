import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';
import '../settings/company_settings_controller.dart';
import 'order_models.dart';
import 'order_repository.dart';

class OrderDetailScreen extends StatefulWidget {
  const OrderDetailScreen({super.key, required this.orderId});

  final String orderId;

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  late Future<Order> _future;
  bool _updating = false;

  @override
  void initState() {
    super.initState();
    _future = context.read<OrderRepository>().getOrder(widget.orderId);
  }

  Future<void> _reload() async {
    setState(() {
      _future = context.read<OrderRepository>().getOrder(widget.orderId);
    });
    await _future;
  }

  Future<void> _updateStatus(Order order, String status) async {
    if (_updating || status == order.status) return;
    setState(() => _updating = true);
    try {
      await context.read<OrderRepository>().updateOrder(
            widget.orderId,
            status: status,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Order status updated')),
      );
      await _reload();
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _updating = false);
    }
  }

  Future<void> _updatePaymentStatus(Order order, String paymentStatus) async {
    if (_updating || paymentStatus == order.paymentStatus) return;
    setState(() => _updating = true);
    try {
      await context.read<OrderRepository>().updateOrder(
            widget.orderId,
            paymentStatus: paymentStatus,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            paymentStatus == 'paid'
                ? 'Marked as paid'
                : 'Payment status updated',
          ),
        ),
      );
      await _reload();
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _updating = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: FutureBuilder<Order>(
          future: _future,
          builder: (context, snapshot) {
            final number = snapshot.data?.orderNumber;
            if (number != null && number.isNotEmpty) return Text(number);
            return const Text('Order');
          },
        ),
      ),
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<Order>(
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
                  Icon(Icons.error_outline, color: Colors.red.shade300, size: 40),
                  const SizedBox(height: 12),
                  Text(message, textAlign: TextAlign.center),
                  const SizedBox(height: 16),
                  Center(
                    child: FilledButton(
                      onPressed: _reload,
                      style: FilledButton.styleFrom(backgroundColor: AppColors.primary),
                      child: const Text('Try again'),
                    ),
                  ),
                ],
              );
            }

            final order = snapshot.data!;
            final formatMoney =
                context.watch<CompanySettingsController>().formatMoney;
            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
              children: [
                AppSurface(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              order.customerName,
                              style: const TextStyle(
                                fontSize: 20,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          OrderStatusChip(status: order.status),
                        ],
                      ),
                      if (order.customerPhone.isNotEmpty) ...[
                        const SizedBox(height: 6),
                        Text(
                          order.customerPhone,
                          style: const TextStyle(color: AppColors.textMuted),
                        ),
                      ],
                      const SizedBox(height: 8),
                      Text(
                        'Placed ${formatOrderDate(order.createdAt)}',
                        style: const TextStyle(
                          color: AppColors.textMuted,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                AppSurface(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Products',
                        style: TextStyle(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 12),
                      if (order.products.isEmpty)
                        const Text(
                          'No line items recorded.',
                          style: TextStyle(color: AppColors.textMuted),
                        )
                      else
                        ...order.products.map((product) {
                          return Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        product.name,
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                      const SizedBox(height: 2),
                                      Text(
                                        '${product.quantity} × ${formatMoney(product.price)}',
                                        style: const TextStyle(
                                          color: AppColors.textMuted,
                                          fontSize: 13,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                Text(
                                  formatMoney(product.lineTotal),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ],
                            ),
                          );
                        }),
                      const Divider(height: 24),
                      if ((order.taxTotal) > 0) ...[
                        Row(
                          children: [
                            const Text(
                              'Subtotal',
                              style: TextStyle(color: AppColors.textMuted),
                            ),
                            const Spacer(),
                            Text(
                              formatMoney(
                                order.subtotal ??
                                    (order.total - order.taxTotal),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        if (order.taxBreakdown.isNotEmpty)
                          ...order.taxBreakdown.map((row) {
                            final label = (row['code'] ?? row['name'] ?? 'Tax')
                                .toString();
                            final rate = row['rate'];
                            final amount =
                                (row['amount'] as num?)?.toDouble() ?? 0;
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: Row(
                                children: [
                                  Text(
                                    rate != null
                                        ? '$label ($rate%)'
                                        : label,
                                    style: const TextStyle(
                                      color: AppColors.textMuted,
                                    ),
                                  ),
                                  const Spacer(),
                                  Text(formatMoney(amount)),
                                ],
                              ),
                            );
                          })
                        else
                          Row(
                            children: [
                              const Text(
                                'Tax',
                                style: TextStyle(color: AppColors.textMuted),
                              ),
                              const Spacer(),
                              Text(formatMoney(order.taxTotal)),
                            ],
                          ),
                        const SizedBox(height: 4),
                      ],
                      Row(
                        children: [
                          const Text(
                            'Total',
                            style: TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 16,
                            ),
                          ),
                          const Spacer(),
                          Text(
                            formatMoney(order.total),
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 22,
                              color: AppColors.primary,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                AppSurface(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Fulfillment status',
                        style: TextStyle(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 4),
                      const Text(
                        'Order progress (pending → confirmed → shipped → delivered).',
                        style: TextStyle(
                          color: AppColors.textMuted,
                          fontSize: 13,
                        ),
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<String>(
                        value: kOrderStatuses.contains(order.status)
                            ? order.status
                            : kOrderStatuses.first,
                        decoration: const InputDecoration(
                          labelText: 'Fulfillment',
                        ),
                        items: kOrderStatuses
                            .map(
                              (status) => DropdownMenuItem(
                                value: status,
                                child: Text(orderStatusLabel(status)),
                              ),
                            )
                            .toList(),
                        onChanged: _updating
                            ? null
                            : (value) {
                                if (value != null) {
                                  _updateStatus(order, value);
                                }
                              },
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                AppSurface(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Expanded(
                            child: Text(
                              'Payment status',
                              style: TextStyle(fontWeight: FontWeight.w800),
                            ),
                          ),
                          _PaymentChip(status: order.paymentStatus),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(
                        order.paymentStatus == 'paid'
                            ? 'Payment received. You can mark refunded if needed.'
                            : 'Set to Paid when till, bank, M-Pesa, or cash is received.',
                        style: const TextStyle(
                          color: AppColors.textMuted,
                          fontSize: 13,
                        ),
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<String>(
                        value: kPaymentStatuses.contains(order.paymentStatus)
                            ? order.paymentStatus
                            : 'pending',
                        decoration: const InputDecoration(
                          labelText: 'Payment',
                        ),
                        items: kPaymentStatuses
                            .map(
                              (status) => DropdownMenuItem(
                                value: status,
                                child: Text(paymentStatusLabel(status)),
                              ),
                            )
                            .toList(),
                        onChanged: _updating
                            ? null
                            : (value) {
                                if (value != null &&
                                    value != order.paymentStatus) {
                                  _updatePaymentStatus(order, value);
                                }
                              },
                      ),
                      if (order.paymentStatus != 'paid') ...[
                        const SizedBox(height: 14),
                        SizedBox(
                          width: double.infinity,
                          child: FilledButton.icon(
                            onPressed: _updating
                                ? null
                                : () => _updatePaymentStatus(order, 'paid'),
                            style: FilledButton.styleFrom(
                              backgroundColor: AppColors.success,
                              foregroundColor: Colors.white,
                              minimumSize: const Size.fromHeight(48),
                            ),
                            icon: const Icon(Icons.check_circle_outline),
                            label: const Text('Mark as paid'),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                if (_updating) ...[
                  const SizedBox(height: 16),
                  const Center(
                    child: SizedBox(
                      width: 24,
                      height: 24,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    ),
                  ),
                ],
              ],
            );
          },
        ),
      ),
    );
  }
}

class OrderStatusChip extends StatelessWidget {
  const OrderStatusChip({super.key, required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final (background, foreground) = _statusColors(status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        orderStatusLabel(status),
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: foreground,
        ),
      ),
    );
  }
}

(Color, Color) _statusColors(String status) {
  return switch (status) {
    'pending' => (const Color(0xFFFFF3E0), const Color(0xFFE65100)),
    'confirmed' => (AppColors.primarySoft, AppColors.primaryDark),
    'shipped' => (const Color(0xFFE0F2FE), AppColors.accentBlue),
    'delivered' => (const Color(0xFFE8F5E9), const Color(0xFF2E7D32)),
    'cancelled' => (const Color(0xFFF3F4F6), const Color(0xFF6B7280)),
    _ => (AppColors.primarySoft, AppColors.primary),
  };
}

class _PaymentChip extends StatelessWidget {
  const _PaymentChip({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final (background, foreground) = switch (status) {
      'paid' => (const Color(0xFFE8F5E9), const Color(0xFF2E7D32)),
      'refunded' => (const Color(0xFFFFF3E0), const Color(0xFFE65100)),
      _ => (AppColors.bubbleIncoming, AppColors.primaryDark),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        paymentStatusLabel(status),
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: foreground,
        ),
      ),
    );
  }
}
