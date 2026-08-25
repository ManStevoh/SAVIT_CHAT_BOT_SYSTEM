import 'package:dio/dio.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';
import 'order_models.dart';

class OrderRepository {
  OrderRepository(this._api);

  final ApiClient _api;

  Future<OrderListResult> listOrders({int page = 1, int limit = 20}) async {
    try {
      final response = await _api.dio.get(
        '/company/orders',
        queryParameters: {
          'page': page,
          'limit': limit,
        },
      );
      final data = response.data;
      if (data is! Map) {
        return const OrderListResult(orders: [], total: 0, page: 1, totalPages: 1);
      }
      return OrderListResult.fromJson(Map<String, dynamic>.from(data));
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<Order> getOrder(String orderId) async {
    try {
      final response = await _api.dio.get('/company/orders/$orderId');
      final orderJson = response.data is Map ? response.data['order'] : null;
      if (orderJson is! Map) {
        throw ApiException('Order not found.');
      }
      return Order.fromJson(Map<String, dynamic>.from(orderJson));
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> updateOrder(
    String orderId, {
    String? status,
    String? paymentStatus,
  }) async {
    final body = <String, dynamic>{
      if (status != null) 'status': status,
      if (paymentStatus != null) 'paymentStatus': paymentStatus,
    };

    if (body.isEmpty) return;

    try {
      await _api.dio.patch('/company/orders/$orderId', data: body);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<OrderTotalsPreview> previewTotals({
    required List<CreateOrderLineItem> items,
  }) async {
    if (items.isEmpty) {
      return const OrderTotalsPreview(
        subtotal: 0,
        taxTotal: 0,
        total: 0,
        taxBreakdown: [],
      );
    }
    try {
      final response = await _api.dio.post(
        '/company/orders/preview-totals',
        data: {
          'items': items.map((e) => e.toJson()).toList(),
        },
      );
      final data = response.data;
      if (data is! Map) {
        throw ApiException('Could not preview order totals.');
      }
      return OrderTotalsPreview.fromJson(Map<String, dynamic>.from(data));
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<CreateOrderResult> createOrder({
    required String chatId,
    required List<CreateOrderLineItem> items,
    bool sendWhatsApp = true,
  }) async {
    if (items.isEmpty) {
      throw ApiException('Add at least one product.');
    }
    try {
      final response = await _api.dio.post(
        '/company/orders',
        data: {
          'chatId': int.tryParse(chatId) ?? chatId,
          'items': items.map((e) => e.toJson()).toList(),
          'sendWhatsApp': sendWhatsApp,
        },
      );
      final data = response.data;
      if (data is! Map || data['success'] != true) {
        throw ApiException(
          data is Map && data['message'] is String
              ? data['message'] as String
              : 'Could not create order.',
        );
      }
      final order = data['order'];
      return CreateOrderResult(
        orderId: order is Map ? '${order['id']}' : '',
        orderNumber: order is Map ? '${order['orderNumber'] ?? ''}' : '',
        message: data['message']?.toString() ?? 'Order created.',
        whatsappSent: data['whatsappSent'] == true,
        whatsappError: data['whatsappError']?.toString(),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
