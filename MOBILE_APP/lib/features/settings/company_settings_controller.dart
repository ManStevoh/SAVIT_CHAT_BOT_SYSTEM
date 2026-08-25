import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../../core/money/money_formatter.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';
import 'company_settings_models.dart';

class CompanySettingsController extends ChangeNotifier {
  CompanySettingsController(this._api);

  CompanySettingsController.seeded(CompanyCommerceSettings value)
      : _api = null,
        _settings = value,
        _loaded = true;

  final ApiClient? _api;
  CompanyCommerceSettings _settings = const CompanyCommerceSettings();
  bool _loaded = false;
  bool _loading = false;

  CompanyCommerceSettings get settings => _settings;
  bool get isLoaded => _loaded;
  bool get isLoading => _loading;

  MoneyFormatter get money => _settings.moneyFormatter;

  String formatMoney(num amount) => money.format(amount);

  void reset() {
    _settings = const CompanyCommerceSettings();
    _loaded = false;
    _loading = false;
    notifyListeners();
  }

  Future<CompanyCommerceSettings> load({bool force = false}) async {
    if (_loading) return _settings;
    if (_loaded && !force) return _settings;
    final api = _api;
    if (api == null) return _settings;

    _loading = true;
    try {
      final response = await api.dio.get('/company/settings');
      final data = response.data;
      if (data is Map) {
        _settings = CompanyCommerceSettings.fromJson(
          Map<String, dynamic>.from(data),
        );
        _loaded = true;
        notifyListeners();
      }
    } on DioException catch (e) {
      debugPrint('Company settings load failed: ${e.message}');
    } catch (e) {
      debugPrint('Company settings load failed: $e');
    } finally {
      _loading = false;
    }
    return _settings;
  }

  Future<void> updateCurrencyDisplay({
    required String displayCurrency,
    String? currencySymbol,
    required String thousandsSeparator,
    required String decimalSeparator,
  }) async {
    final api = _api;
    if (api == null) return;

    try {
      await api.dio.put(
        '/company/settings',
        data: {
          'displayCurrency': MoneyFormatter.normalizeCurrencyCode(displayCurrency),
          'currencySymbol':
              (currencySymbol == null || currencySymbol.trim().isEmpty)
                  ? null
                  : currencySymbol.trim(),
          'thousandsSeparator':
              MoneyFormatter.normalizeThousands(thousandsSeparator),
          'decimalSeparator': MoneyFormatter.normalizeDecimal(
            decimalSeparator,
            MoneyFormatter.normalizeThousands(thousandsSeparator),
          ),
        },
      );
      _settings = _settings.copyWith(
        displayCurrency: MoneyFormatter.normalizeCurrencyCode(displayCurrency),
        currencySymbol: currencySymbol,
        clearCurrencySymbol:
            currencySymbol == null || currencySymbol.trim().isEmpty,
        thousandsSeparator:
            MoneyFormatter.normalizeThousands(thousandsSeparator),
        decimalSeparator: MoneyFormatter.normalizeDecimal(
          decimalSeparator,
          MoneyFormatter.normalizeThousands(thousandsSeparator),
        ),
      );
      _loaded = true;
      notifyListeners();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> updateStorefrontSettings({
    String? storeSlug,
    required bool storefrontEnabled,
    required bool linkInBioEnabled,
    required bool ordersAcceptCod,
    required bool deliveryFeesEnabled,
    required double defaultDeliveryFee,
    double? freeDeliveryAbove,
    required bool dineInEnabled,
  }) async {
    final api = _api;
    if (api == null) return;

    try {
      await api.dio.put(
        '/company/settings',
        data: {
          'storeSlug': storeSlug,
          'storefrontEnabled': storefrontEnabled,
          'linkInBioEnabled': linkInBioEnabled,
          'ordersAcceptCod': ordersAcceptCod,
          'deliveryFeesEnabled': deliveryFeesEnabled,
          'defaultDeliveryFee': defaultDeliveryFee,
          'freeDeliveryAbove': freeDeliveryAbove,
          'dineInEnabled': dineInEnabled,
        },
      );
      // Reload from the server so the derived storefront URL stays accurate.
      await load(force: true);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
