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
    if (paymentStatus == 'paid') {
      throw ApiException(
        'Payment can only be marked paid through a verified payment gateway.',
      );
    }

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
}
