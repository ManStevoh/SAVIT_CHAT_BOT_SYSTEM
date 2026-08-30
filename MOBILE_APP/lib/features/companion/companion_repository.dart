import 'package:dio/dio.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';
import 'companion_models.dart';

class CompanionRepository {
  CompanionRepository(this._api);

  final ApiClient _api;

  Future<AnalyticsSnapshot> analytics({String period = '7d'}) async {
    try {
      final res = await _api.dio.get(
        '/company/analytics',
        queryParameters: {'period': period},
      );
      final data = res.data is Map ? Map<String, dynamic>.from(res.data as Map) : <String, dynamic>{};
      return AnalyticsSnapshot.fromJson(data, period);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<SubscriptionOverview> subscription() async {
    try {
      final res = await _api.dio.get('/company/subscription');
      return SubscriptionOverview.fromJson(Map<String, dynamic>.from(res.data as Map));
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<UsageMeter>> usage() async {
    try {
      final res = await _api.dio.get('/company/subscription/usage');
      final items = (res.data is Map ? (res.data as Map)['items'] : null) as List? ?? [];
      return items
          .whereType<Map>()
          .map((e) => UsageMeter.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<BillingInvoice>> invoices() async {
    try {
      final res = await _api.dio.get('/company/subscription/invoices');
      final data = res.data;
      if (data is! List) return [];
      return data
          .whereType<Map>()
          .map((e) => BillingInvoice.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<String> cancelSubscription() async {
    try {
      final res = await _api.dio.post('/company/subscription/cancel');
      final data = res.data is Map ? Map<String, dynamic>.from(res.data as Map) : {};
      return data['message']?.toString() ?? 'Subscription cancelled.';
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<DeliveryZone>> deliveryZones() async {
    try {
      final res = await _api.dio.get('/company/delivery-zones');
      final data = res.data;
      if (data is! List) return [];
      return data
          .whereType<Map>()
          .map((e) => DeliveryZone.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<DeliveryZone> saveDeliveryZone({
    String? id,
    required String name,
    required double fee,
    double? minOrderAmount,
    List<String>? keywords,
    bool isActive = true,
  }) async {
    try {
      final payload = {
        'name': name.trim(),
        'fee': fee,
        'minOrderAmount': minOrderAmount,
        'keywords': keywords,
        'isActive': isActive,
      };
      final res = id == null
          ? await _api.dio.post('/company/delivery-zones', data: payload)
          : await _api.dio.put('/company/delivery-zones/$id', data: payload);
      final body = res.data is Map ? Map<String, dynamic>.from(res.data as Map) : {};
      final zone = body['zone'] is Map ? body['zone'] : body;
      return DeliveryZone.fromJson(Map<String, dynamic>.from(zone as Map));
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> deleteDeliveryZone(String id) async {
    try {
      await _api.dio.delete('/company/delivery-zones/$id');
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<({bool allowed, String? message, List<DineInTable> tables})> dineInTables() async {
    try {
      final res = await _api.dio.get('/company/dine-in-tables');
      final data = res.data is Map ? Map<String, dynamic>.from(res.data as Map) : {};
      final raw = data['tables'];
      return (
        allowed: data['allowed'] != false,
        message: data['message']?.toString(),
        tables: raw is List
            ? raw
                .whereType<Map>()
                .map((e) => DineInTable.fromJson(Map<String, dynamic>.from(e)))
                .toList()
            : <DineInTable>[],
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<DineInTable> saveDineInTable({
    String? id,
    required String name,
    String? code,
    int? seats,
    bool isActive = true,
  }) async {
    try {
      final payload = {
        'name': name.trim(),
        'code': code?.trim().isEmpty == true ? null : code?.trim(),
        'seats': seats,
        'isActive': isActive,
      };
      final res = id == null
          ? await _api.dio.post('/company/dine-in-tables', data: payload)
          : await _api.dio.put('/company/dine-in-tables/$id', data: payload);
      final body = res.data is Map ? Map<String, dynamic>.from(res.data as Map) : {};
      return DineInTable.fromJson(Map<String, dynamic>.from(body['table'] as Map));
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> deleteDineInTable(String id) async {
    try {
      await _api.dio.delete('/company/dine-in-tables/$id');
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<StoreCoupon>> coupons() async {
    try {
      final res = await _api.dio.get('/company/storefront-coupons');
      final data = res.data;
      if (data is! List) return [];
      return data
          .whereType<Map>()
          .map((e) => StoreCoupon.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<StoreCoupon> saveCoupon({
    String? id,
    required String code,
    required String type,
    required double value,
    double? minOrder,
    int? maxRedemptions,
    bool isActive = true,
  }) async {
    try {
      final payload = {
        'code': code.trim().toUpperCase(),
        'type': type,
        'value': value,
        'minOrder': minOrder,
        'maxRedemptions': maxRedemptions,
        'isActive': isActive,
      };
      final res = id == null
          ? await _api.dio.post('/company/storefront-coupons', data: payload)
          : await _api.dio.put('/company/storefront-coupons/$id', data: payload);
      final body = res.data is Map ? Map<String, dynamic>.from(res.data as Map) : {};
      return StoreCoupon.fromJson(Map<String, dynamic>.from(body['coupon'] as Map));
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> deleteCoupon(String id) async {
    try {
      await _api.dio.delete('/company/storefront-coupons/$id');
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<CampaignSummary>> campaigns() async {
    try {
      final res = await _api.dio.get('/company/whatsapp/campaigns');
      final data = res.data;
      if (data is! List) return [];
      return data
          .whereType<Map>()
          .map((e) => CampaignSummary.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<Map<String, dynamic>> campaignLimits() async {
    try {
      final res = await _api.dio.get('/company/whatsapp/campaign/limits');
      return res.data is Map ? Map<String, dynamic>.from(res.data as Map) : {};
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<CampaignSummary> createCampaign({
    required String name,
    required String segment,
    required String caption,
  }) async {
    try {
      final res = await _api.dio.post(
        '/company/whatsapp/campaigns',
        data: {
          'name': name.trim(),
          'segment': segment,
          'caption': caption.trim(),
        },
      );
      final body = res.data is Map ? Map<String, dynamic>.from(res.data as Map) : {};
      final raw = body['campaign'] ?? body;
      return CampaignSummary.fromJson(Map<String, dynamic>.from(raw as Map));
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> sendCampaign(String id) async {
    try {
      await _api.dio.post('/company/whatsapp/campaigns/$id/send');
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> testCampaign(String id) async {
    try {
      await _api.dio.post('/company/whatsapp/campaigns/$id/test');
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<TeamMember>> team() async {
    try {
      final res = await _api.dio.get('/company/team');
      final data = res.data;
      if (data is! List) return [];
      return data
          .whereType<Map>()
          .map((e) => TeamMember.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<String?> inviteTeam({
    required String name,
    required String email,
  }) async {
    try {
      final res = await _api.dio.post(
        '/company/team',
        data: {
          'name': name.trim(),
          'email': email.trim(),
        },
      );
      final data = res.data is Map ? Map<String, dynamic>.from(res.data as Map) : {};
      return data['temporaryPassword']?.toString();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<WhatsAppStatus> whatsappStatus() async {
    try {
      final res = await _api.dio.get('/company/whatsapp/status');
      return WhatsAppStatus.fromJson(Map<String, dynamic>.from(res.data as Map));
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<Map<String, dynamic>> companySettings() async {
    try {
      final res = await _api.dio.get('/company/settings');
      return res.data is Map ? Map<String, dynamic>.from(res.data as Map) : {};
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> updateSettings(Map<String, dynamic> payload) async {
    try {
      await _api.dio.put('/company/settings', data: payload);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> updateBookingSettings(Map<String, dynamic> payload) async {
    try {
      await _api.dio.put('/company/bookings/settings', data: payload);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> updateBookingStatus(String id, String status) async {
    try {
      await _api.dio.patch('/company/bookings/$id', data: {'status': status});
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> markAllNotificationsRead() async {
    try {
      await _api.dio.post('/company/notifications/read-all');
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
