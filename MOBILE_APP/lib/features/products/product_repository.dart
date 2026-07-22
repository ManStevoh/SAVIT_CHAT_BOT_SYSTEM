import 'package:dio/dio.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';
import 'product_models.dart';

class ProductRepository {
  ProductRepository(this._api);

  final ApiClient _api;

  Future<List<Product>> listProducts({
    String? search,
    String? category,
    String? status,
  }) async {
    try {
      final response = await _api.dio.get(
        '/company/products',
        queryParameters: {
          if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
          if (category != null && category.isNotEmpty && category != 'all') 'category': category,
          if (status != null && status.isNotEmpty && status != 'all') 'status': status,
        },
      );
      final data = response.data;
      if (data is! List) return [];
      return data
          .whereType<Map>()
          .map((e) => Product.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<Product> createProduct(ProductInput input, {String? imagePath}) async {
    try {
      final response = await _api.dio.post(
        '/company/products',
        data: await _toFormData(input, isUpdate: false, imagePath: imagePath),
      );
      return _parseProductResponse(response.data);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<Product> updateProduct(
    String id,
    ProductInput input, {
    String? imagePath,
  }) async {
    try {
      final response = await _api.dio.post(
        '/company/products/$id',
        data: await _toFormData(input, isUpdate: true, imagePath: imagePath),
      );
      return _parseProductResponse(response.data);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> deleteProduct(String id) async {
    try {
      await _api.dio.delete('/company/products/$id');
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<ProductVariant> createVariant(
    String productId,
    ProductVariantInput input,
  ) async {
    try {
      final response = await _api.dio.post(
        '/company/products/$productId/variants',
        data: input.toJson(),
      );
      return _parseVariantResponse(response.data);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<ProductVariant> updateVariant(
    String id,
    ProductVariantInput input,
  ) async {
    try {
      final response = await _api.dio.put(
        '/company/product-variants/$id',
        data: input.toJson(),
      );
      return _parseVariantResponse(response.data);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> deleteVariant(String id) async {
    try {
      await _api.dio.delete('/company/product-variants/$id');
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<FormData> _toFormData(
    ProductInput input, {
    required bool isUpdate,
    String? imagePath,
  }) async {
    final map = <String, dynamic>{
      ...input.toJson(isUpdate: isUpdate),
    };
    if (imagePath != null && imagePath.isNotEmpty) {
      map['image'] = await MultipartFile.fromFile(
        imagePath,
        filename: imagePath.split(RegExp(r'[\\/]')).last,
      );
    }
    return FormData.fromMap(map);
  }

  Product _parseProductResponse(dynamic data) {
    if (data is Map && data['product'] is Map) {
      return Product.fromJson(Map<String, dynamic>.from(data['product'] as Map));
    }
    throw ApiException('Unexpected product response.');
  }

  ProductVariant _parseVariantResponse(dynamic data) {
    if (data is Map && data['variant'] is Map) {
      return ProductVariant.fromJson(Map<String, dynamic>.from(data['variant'] as Map));
    }
    throw ApiException('Unexpected variant response.');
  }
}
