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
          if (search != null && search.trim().isNotEmpty)
            'search': search.trim(),
          if (category != null && category.isNotEmpty && category != 'all')
            'category': category,
          if (status != null && status.isNotEmpty && status != 'all')
            'status': status,
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

  Future<Product> createProduct(ProductInput input,
      {String? imagePath, String? digitalFilePath}) async {
    try {
      final hasFiles = (imagePath != null && imagePath.isNotEmpty) ||
          (digitalFilePath != null && digitalFilePath.isNotEmpty);
      final response = await _api.dio.post(
        '/company/products',
        data: hasFiles
            ? await _toFormData(input,
                isUpdate: false,
                imagePath: imagePath,
                digitalFilePath: digitalFilePath)
            : input.toJson(isUpdate: false),
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
    String? digitalFilePath,
  }) async {
    try {
      final hasFiles = (imagePath != null && imagePath.isNotEmpty) ||
          (digitalFilePath != null && digitalFilePath.isNotEmpty);
      // No files: JSON PUT keeps real booleans (Laravel rejects multipart "true"/"false").
      // With files: multipart POST + 0/1 encoding (PHP ignores files on PUT).
      final response = hasFiles
          ? await _api.dio.post(
              '/company/products/$id',
              data: await _toFormData(input,
                  isUpdate: true,
                  imagePath: imagePath,
                  digitalFilePath: digitalFilePath),
            )
          : await _api.dio.put(
              '/company/products/$id',
              data: input.toJson(isUpdate: true),
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

  /// Lightweight partial update used for the quick active/inactive toggle.
  Future<Product> updateProductStatus(String id, String status) async {
    try {
      final response = await _api.dio.put(
        '/company/products/$id',
        data: {'status': status},
      );
      return _parseProductResponse(response.data);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  /// Creates a new product by copying the fields of an existing one.
  /// Images and digital files are not copied — the merchant can attach
  /// new ones on the duplicate.
  Future<Product> duplicateProduct(Product product) async {
    final input = ProductInput(
      name: '${product.name} (Copy)',
      description: product.description.isEmpty ? null : product.description,
      price: product.price,
      taxRateId: product.taxRateId,
      category: product.category.isEmpty ? null : product.category,
      productType: product.productType,
      fulfillmentType: product.fulfillmentType,
      trackInventory: product.trackInventory,
      requiresDeliveryAddress: product.requiresDeliveryAddress,
      accessUrl: product.accessUrl,
      serviceBookingUrl: product.serviceBookingUrl,
      fulfillmentInstructions: product.fulfillmentInstructions,
      licenseKeyMode: product.licenseKeyMode,
      licenseKeyPrefix: product.licenseKeyPrefix,
      accessExpiresDays: product.accessExpiresDays,
      maxDownloads: product.maxDownloads,
      bookable: product.bookable,
      bookingDurationMinutes: product.bookingDurationMinutes,
      stock: product.trackInventory ? product.stock : 0,
    );
    return createProduct(input);
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
    String? digitalFilePath,
  }) async {
    final map = <String, dynamic>{
      ...input.toJson(isUpdate: isUpdate),
    };
    // Multipart cannot send JSON null — empty string clears nullable ints/strings.
    for (final key in [
      'accessExpiresDays',
      'maxDownloads',
      'bookingDurationMinutes',
      'accessUrl',
      'serviceBookingUrl',
      'fulfillmentInstructions',
      'licenseKeyPrefix',
      'taxRateId',
    ]) {
      if (map.containsKey(key) && map[key] == null) {
        map[key] = '';
      }
    }
    // Laravel boolean rule accepts 0/1, not Dio's default "true"/"false" strings.
    for (final entry in map.entries.toList()) {
      final value = entry.value;
      if (value is bool) {
        map[entry.key] = value ? '1' : '0';
      }
    }
    if (imagePath != null && imagePath.isNotEmpty) {
      map['image'] = await MultipartFile.fromFile(
        imagePath,
        filename: imagePath.split(RegExp(r'[\\/]')).last,
      );
    }
    if (digitalFilePath != null && digitalFilePath.isNotEmpty) {
      map['digitalFile'] = await MultipartFile.fromFile(
        digitalFilePath,
        filename: digitalFilePath.split(RegExp(r'[\\/]')).last,
      );
    }
    return FormData.fromMap(map);
  }

  Product _parseProductResponse(dynamic data) {
    if (data is Map && data['product'] is Map) {
      return Product.fromJson(
          Map<String, dynamic>.from(data['product'] as Map));
    }
    throw ApiException('Unexpected product response.');
  }

  ProductVariant _parseVariantResponse(dynamic data) {
    if (data is Map && data['variant'] is Map) {
      return ProductVariant.fromJson(
          Map<String, dynamic>.from(data['variant'] as Map));
    }
    throw ApiException('Unexpected variant response.');
  }
}
