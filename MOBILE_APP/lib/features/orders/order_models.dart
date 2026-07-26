class OrderProduct {
  const OrderProduct({
    required this.id,
    required this.name,
    required this.quantity,
    required this.price,
    this.taxAmount = 0,
    this.lineSubtotal,
    this.taxName,
    this.taxRate,
    this.taxInclusive = false,
  });

  final String id;
  final String name;
  final int quantity;
  final double price;
  final double taxAmount;
  final double? lineSubtotal;
  final String? taxName;
  final double? taxRate;
  final bool taxInclusive;

  /// Amount the customer pays for this line (catalog + exclusive tax when applicable).
  double get lineTotal {
    if (taxInclusive || taxAmount <= 0) {
      return price * quantity;
    }
    return (price * quantity) + taxAmount;
  }

  factory OrderProduct.fromJson(Map<String, dynamic> json) {
    return OrderProduct(
      id: '${json['id']}',
      name: json['name']?.toString() ?? '',
      quantity: (json['quantity'] as num?)?.toInt() ?? 0,
      price: (json['price'] as num?)?.toDouble() ?? 0,
      taxAmount: (json['taxAmount'] as num?)?.toDouble() ?? 0,
      lineSubtotal: (json['lineSubtotal'] as num?)?.toDouble(),
      taxName: json['taxName']?.toString(),
      taxRate: (json['taxRate'] as num?)?.toDouble(),
      taxInclusive: json['taxInclusive'] == true,
    );
  }
}

class Order {
  const Order({
    required this.id,
    required this.orderNumber,
    required this.customerName,
    required this.customerPhone,
    required this.products,
    required this.total,
    this.subtotal,
    this.taxTotal = 0,
    this.taxBreakdown = const [],
    required this.status,
    required this.paymentStatus,
    required this.createdAt,
    required this.updatedAt,
    this.chatId,
  });

  final String id;
  final String orderNumber;
  final String customerName;
  final String customerPhone;
  final String? chatId;
  final List<OrderProduct> products;
  final double total;
  final double? subtotal;
  final double taxTotal;
  final List<Map<String, dynamic>> taxBreakdown;
  final String status;
  final String paymentStatus;
  final String createdAt;
  final String updatedAt;

  factory Order.fromJson(Map<String, dynamic> json) {
    final productsJson = json['products'];
    final products = productsJson is List
        ? productsJson
            .whereType<Map>()
            .map((e) => OrderProduct.fromJson(Map<String, dynamic>.from(e)))
            .toList()
        : <OrderProduct>[];

    final breakdownRaw = json['taxBreakdown'];
    final breakdown = breakdownRaw is List
        ? breakdownRaw
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList()
        : <Map<String, dynamic>>[];

    return Order(
      id: '${json['id']}',
      orderNumber: json['orderNumber']?.toString() ?? '',
      customerName: json['customerName']?.toString() ?? 'Customer',
      customerPhone: json['customerPhone']?.toString() ?? '',
      chatId: json['chatId'] != null ? '${json['chatId']}' : null,
      products: products,
      total: (json['total'] as num?)?.toDouble() ?? 0,
      subtotal: (json['subtotal'] as num?)?.toDouble(),
      taxTotal: (json['taxTotal'] as num?)?.toDouble() ?? 0,
      taxBreakdown: breakdown,
      status: json['status']?.toString() ?? 'pending',
      paymentStatus: _normalizePaymentStatus(json['paymentStatus']),
      createdAt: json['createdAt']?.toString() ?? '',
      updatedAt: json['updatedAt']?.toString() ?? '',
    );
  }
}

class OrderListResult {
  const OrderListResult({
    required this.orders,
    required this.total,
    required this.page,
    required this.totalPages,
  });

  final List<Order> orders;
  final int total;
  final int page;
  final int totalPages;

  factory OrderListResult.fromJson(Map<String, dynamic> json) {
    final ordersJson = json['orders'];
    final orders = ordersJson is List
        ? ordersJson
            .whereType<Map>()
            .map((e) => Order.fromJson(Map<String, dynamic>.from(e)))
            .toList()
        : <Order>[];

    return OrderListResult(
      orders: orders,
      total: (json['total'] as num?)?.toInt() ?? orders.length,
      page: (json['page'] as num?)?.toInt() ?? 1,
      totalPages: (json['totalPages'] as num?)?.toInt() ?? 1,
    );
  }
}

/// Fulfillment lifecycle (not payment).
const List<String> kOrderStatuses = [
  'pending',
  'confirmed',
  'shipped',
  'delivered',
  'cancelled',
];

/// Payment lifecycle — includes paid for manual/till confirmation.
const List<String> kPaymentStatuses = [
  'pending',
  'paid',
  'refunded',
];

/// @Deprecated('Use kPaymentStatuses')
const List<String> kPatchablePaymentStatuses = kPaymentStatuses;

class CreateOrderLineItem {
  const CreateOrderLineItem({
    required this.productId,
    required this.name,
    required this.quantity,
    required this.price,
  });

  final String productId;
  final String name;
  final int quantity;
  final double price;

  double get lineTotal => price * quantity;

  Map<String, dynamic> toJson() => {
        'productId': int.tryParse(productId) ?? productId,
        'name': name,
        'quantity': quantity,
        'price': price,
      };
}

class CreateOrderResult {
  const CreateOrderResult({
    required this.orderId,
    required this.orderNumber,
    required this.message,
    required this.whatsappSent,
    this.whatsappError,
  });

  final String orderId;
  final String orderNumber;
  final String message;
  final bool whatsappSent;
  final String? whatsappError;
}

class OrderTotalsPreview {
  const OrderTotalsPreview({
    required this.subtotal,
    required this.taxTotal,
    required this.total,
    this.taxBreakdown = const [],
  });

  final double subtotal;
  final double taxTotal;
  final double total;
  final List<Map<String, dynamic>> taxBreakdown;

  factory OrderTotalsPreview.fromJson(Map<String, dynamic> json) {
    final breakdownRaw = json['taxBreakdown'];
    final breakdown = breakdownRaw is List
        ? breakdownRaw
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList()
        : <Map<String, dynamic>>[];

    return OrderTotalsPreview(
      subtotal: (json['subtotal'] as num?)?.toDouble() ?? 0,
      taxTotal: (json['taxTotal'] as num?)?.toDouble() ?? 0,
      total: (json['total'] as num?)?.toDouble() ?? 0,
      taxBreakdown: breakdown,
    );
  }
}

String orderStatusLabel(String status) {
  return switch (status) {
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
    _ => status.isEmpty ? 'Unknown' : status[0].toUpperCase() + status.substring(1),
  };
}

String paymentStatusLabel(String status) {
  return switch (status) {
    'pending' => 'Pending',
    'paid' => 'Paid',
    'refunded' => 'Refunded',
    _ => status.isEmpty ? 'Unknown' : status[0].toUpperCase() + status.substring(1),
  };
}

String _normalizePaymentStatus(dynamic raw) {
  final value = raw?.toString().trim().toLowerCase() ?? '';
  if (kPaymentStatuses.contains(value)) return value;
  return 'pending';
}

String formatOrderDate(String iso) {
  if (iso.isEmpty) return '';
  try {
    final dt = DateTime.parse(iso).toLocal();
    const months = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    final hour = dt.hour % 12 == 0 ? 12 : dt.hour % 12;
    final minute = dt.minute.toString().padLeft(2, '0');
    final period = dt.hour >= 12 ? 'PM' : 'AM';
    return '${months[dt.month - 1]} ${dt.day}, ${dt.year} · $hour:$minute $period';
  } catch (_) {
    return iso;
  }
}
