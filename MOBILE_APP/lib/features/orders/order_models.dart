class OrderProduct {
  const OrderProduct({
    required this.id,
    required this.name,
    required this.quantity,
    required this.price,
  });

  final String id;
  final String name;
  final int quantity;
  final double price;

  double get lineTotal => price * quantity;

  factory OrderProduct.fromJson(Map<String, dynamic> json) {
    return OrderProduct(
      id: '${json['id']}',
      name: json['name']?.toString() ?? '',
      quantity: (json['quantity'] as num?)?.toInt() ?? 0,
      price: (json['price'] as num?)?.toDouble() ?? 0,
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

    return Order(
      id: '${json['id']}',
      orderNumber: json['orderNumber']?.toString() ?? '',
      customerName: json['customerName']?.toString() ?? 'Customer',
      customerPhone: json['customerPhone']?.toString() ?? '',
      chatId: json['chatId'] != null ? '${json['chatId']}' : null,
      products: products,
      total: (json['total'] as num?)?.toDouble() ?? 0,
      status: json['status']?.toString() ?? 'pending',
      paymentStatus: json['paymentStatus']?.toString() ?? 'pending',
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

const List<String> kOrderStatuses = [
  'pending',
  'confirmed',
  'shipped',
  'delivered',
  'cancelled',
];

/// Values the mobile app may send via PATCH (never `paid`).
const List<String> kPatchablePaymentStatuses = [
  'pending',
  'refunded',
];

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
