import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../network/api_client.dart';
import 'app_branding.dart';

class BrandingRepository {
  BrandingRepository(this._api);

  /// Immediate branding for widget tests (no network).
  BrandingRepository.seeded(AppBranding value)
      : _api = null,
        _cached = value;

  final ApiClient? _api;
  AppBranding _cached = AppBranding.fallback;

  AppBranding get current => _cached;

  Future<AppBranding> load() async {
    final api = _api;
    if (api == null) return _cached;

    try {
      final response = await api.dio.get('/app-branding');
      final data = response.data;
      if (data is Map) {
        _cached = AppBranding.fromJson(Map<String, dynamic>.from(data));
      }
    } on DioException catch (e) {
      debugPrint('Branding load failed: ${e.message}');
    } catch (e) {
      debugPrint('Branding load failed: $e');
    }
    return _cached;
  }
}
