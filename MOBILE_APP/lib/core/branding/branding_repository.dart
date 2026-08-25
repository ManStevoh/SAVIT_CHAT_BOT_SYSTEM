import 'package:dio/dio.dart';
import 'package:flutter/material.dart';

import '../network/api_client.dart';
import '../theme/app_theme.dart';
import 'app_branding.dart';
import 'brand_color.dart';

class BrandingRepository extends ChangeNotifier {
  BrandingRepository(this._api);

  /// Immediate branding for widget tests (no network).
  BrandingRepository.seeded(AppBranding value)
      : _api = null,
        _cached = value;

  final ApiClient? _api;
  AppBranding _cached = AppBranding.fallback;

  AppBranding get current => _cached;

  Color get primary =>
      parseBrandColor(_cached.primaryColor) ?? AppColors.primary;

  Color get primaryDark {
    final parsed = parseBrandColor(_cached.primaryColor);
    if (parsed == null) return AppColors.primaryDark;
    return brandDarken(parsed);
  }

  Color get primarySoft {
    final parsed = parseBrandColor(_cached.primaryColor);
    if (parsed == null) return AppColors.primarySoft;
    return brandSoft(parsed);
  }

  Color? get secondary => parseBrandColor(_cached.secondaryColor);

  Future<AppBranding> load() async {
    final api = _api;
    if (api == null) return _cached;

    try {
      final response = await api.dio.get('/app-branding');
      final data = response.data;
      if (data is Map) {
        _cached = AppBranding.fromJson(Map<String, dynamic>.from(data));
        notifyListeners();
      }
    } on DioException catch (e) {
      debugPrint('Branding load failed: ${e.message}');
    } catch (e) {
      debugPrint('Branding load failed: $e');
    }
    return _cached;
  }
}
