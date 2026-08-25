import 'package:dio/dio.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';
import '../../core/utils/json_utils.dart';

class HomeOverview {
  const HomeOverview({
    required this.totalMessages,
    required this.totalOrders,
    required this.totalCustomers,
    required this.totalRevenue,
    required this.notifications,
    required this.unreadNotifications,
  });

  final int totalMessages;
  final int totalOrders;
  final int totalCustomers;
  final double totalRevenue;
  final List<AppNotification> notifications;
  final int unreadNotifications;
}

class AppNotification {
  const AppNotification({
    required this.id,
    required this.title,
    required this.body,
    required this.read,
    this.chatId,
    this.orderId,
  });

  final String id;
  final String title;
  final String body;
  final bool read;
  final String? chatId;
  final String? orderId;

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      id: '${json['id']}',
      title: jsonString(json['title']),
      body: jsonString(json['body']),
      read: json['read'] == true,
      chatId: json['chatId']?.toString(),
      orderId: json['orderId']?.toString(),
    );
  }
}

class HomeRepository {
  HomeRepository(this._api);

  final ApiClient _api;

  Future<HomeOverview> load({String period = '7d'}) async {
    try {
      final results = await Future.wait([
        _api.dio.get(
          '/company/analytics',
          queryParameters: {'period': period},
        ),
        _api.dio.get('/company/notifications'),
      ]);

      final analyticsRaw = results[0].data;
      final notificationsRaw = results[1].data;
      final analytics = analyticsRaw is Map ? Map<String, dynamic>.from(analyticsRaw) : <String, dynamic>{};
      final notificationsPayload =
          notificationsRaw is Map ? Map<String, dynamic>.from(notificationsRaw) : <String, dynamic>{};
      final items = (notificationsPayload['items'] as List?) ?? [];

      return HomeOverview(
        totalMessages: (analytics['totalMessages'] as num?)?.toInt() ?? 0,
        totalOrders: (analytics['totalOrders'] as num?)?.toInt() ?? 0,
        totalCustomers: (analytics['totalCustomers'] as num?)?.toInt() ?? 0,
        totalRevenue: (analytics['totalRevenue'] as num?)?.toDouble() ?? 0,
        unreadNotifications: (notificationsPayload['unreadCount'] as num?)?.toInt() ?? 0,
        notifications: items
            .whereType<Map>()
            .map((e) => AppNotification.fromJson(Map<String, dynamic>.from(e)))
            .toList(),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> markNotificationRead(String id) async {
    try {
      await _api.dio.post('/company/notifications/$id/read');
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
