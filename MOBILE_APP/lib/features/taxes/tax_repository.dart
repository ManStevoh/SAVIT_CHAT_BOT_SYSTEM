import 'package:dio/dio.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';
import 'tax_models.dart';

class TaxRepository {
  TaxRepository(this._api);

  final ApiClient _api;

  Future<List<TaxRate>> listTaxRates() async {
    try {
      final response = await _api.dio.get('/company/tax-rates');
      final data = response.data;
      if (data is! List) return [];
      return data
          .whereType<Map>()
          .map((e) => TaxRate.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<bool> isTaxEnabled() async {
    try {
      final response = await _api.dio.get('/company/settings');
      final data = response.data;
      if (data is Map) {
        return data['taxEnabled'] == true;
      }
      return false;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> setTaxEnabled(bool enabled) async {
    try {
      await _api.dio.put('/company/settings', data: {'taxEnabled': enabled});
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<TaxRate> createTaxRate(TaxRateInput input) async {
    try {
      final response = await _api.dio.post(
        '/company/tax-rates',
        data: input.toJson(),
      );
      final body = response.data;
      if (body is Map && body['taxRate'] is Map) {
        return TaxRate.fromJson(Map<String, dynamic>.from(body['taxRate']));
      }
      throw ApiException('Unexpected create tax rate response');
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<TaxRate> updateTaxRate(String id, TaxRateInput input) async {
    try {
      final response = await _api.dio.put(
        '/company/tax-rates/$id',
        data: input.toJson(),
      );
      final body = response.data;
      if (body is Map && body['taxRate'] is Map) {
        return TaxRate.fromJson(Map<String, dynamic>.from(body['taxRate']));
      }
      // Some updates may only return success; reload list caller-side.
      return TaxRate(
        id: id,
        name: input.name,
        code: input.code,
        rate: input.rate,
        isInclusive: input.isInclusive,
        isDefault: input.isDefault,
        isActive: input.isActive,
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> deleteTaxRate(String id) async {
    try {
      await _api.dio.delete('/company/tax-rates/$id');
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
