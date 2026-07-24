import '../../core/utils/json_utils.dart';

class Product {
  const Product({
    required this.id,
    required this.name,
    required this.description,
    required this.price,
    required this.category,
    required this.productType,
    required this.fulfillmentType,
    this.image,
    required this.trackInventory,
    required this.requiresDeliveryAddress,
    this.accessUrl,
    this.serviceBookingUrl,
    this.fulfillmentInstructions,
    this.hasDigitalFile = false,
    this.digitalFileUrl,
    this.digitalFileName,
    this.licenseKeyMode = 'none',
    this.licenseKeyPrefix,
    this.accessExpiresDays,
    this.maxDownloads,
    this.bookable = false,
    this.bookingDurationMinutes,
    this.licenseKeysAvailable = 0,
    required this.stock,
    required this.status,
    required this.createdAt,
    this.images = const [],
    this.variants = const [],
  });

  final String id;
  final String name;
  final String description;
  final double price;
  final String category;
  final String productType;
  final String fulfillmentType;
  final String? image;
  final bool trackInventory;
  final bool requiresDeliveryAddress;
  final String? accessUrl;
  final String? serviceBookingUrl;
  final String? fulfillmentInstructions;
  final bool hasDigitalFile;
  final String? digitalFileUrl;
  final String? digitalFileName;
  final String licenseKeyMode;
  final String? licenseKeyPrefix;
  final int? accessExpiresDays;
  final int? maxDownloads;
  final bool bookable;
  final int? bookingDurationMinutes;
  final int licenseKeysAvailable;
  final int stock;
  final String status;
  final String createdAt;
  final List<ProductImage> images;
  final List<ProductVariant> variants;

  bool get isActive => status == 'active';

  factory Product.fromJson(Map<String, dynamic> json) {
    final imagesRaw = json['images'];
    final variantsRaw = json['variants'];

    return Product(
      id: '${json['id']}',
      name: jsonString(json['name']),
      description: jsonString(json['description']),
      price: (json['price'] as num?)?.toDouble() ?? 0,
      category: jsonString(json['category']),
      productType: jsonString(json['productType'], 'physical'),
      fulfillmentType: jsonString(json['fulfillmentType'], 'shipping'),
      image: jsonStringOrNull(json['image']),
      trackInventory: json['trackInventory'] != false,
      requiresDeliveryAddress: json['requiresDeliveryAddress'] != false,
      accessUrl: jsonStringOrNull(json['accessUrl']),
      serviceBookingUrl: jsonStringOrNull(json['serviceBookingUrl']),
      fulfillmentInstructions:
          jsonStringOrNull(json['fulfillmentInstructions']),
      hasDigitalFile: json['hasDigitalFile'] == true ||
          (jsonStringOrNull(json['digitalFileName'])?.isNotEmpty ?? false),
      digitalFileUrl: jsonStringOrNull(json['digitalFileUrl']),
      digitalFileName: jsonStringOrNull(json['digitalFileName']),
      licenseKeyMode: jsonString(json['licenseKeyMode'], 'none'),
      licenseKeyPrefix: jsonStringOrNull(json['licenseKeyPrefix']),
      accessExpiresDays: (json['accessExpiresDays'] as num?)?.toInt(),
      maxDownloads: (json['maxDownloads'] as num?)?.toInt(),
      bookable: json['bookable'] == true,
      bookingDurationMinutes: (json['bookingDurationMinutes'] as num?)?.toInt(),
      licenseKeysAvailable:
          (json['licenseKeysAvailable'] as num?)?.toInt() ?? 0,
      stock: (json['stock'] as num?)?.toInt() ?? 0,
      status: jsonString(json['status'], 'active'),
      createdAt: jsonString(json['createdAt']),
      images: imagesRaw is List
          ? imagesRaw
              .whereType<Map>()
              .map((e) => ProductImage.fromJson(Map<String, dynamic>.from(e)))
              .toList()
          : const [],
      variants: variantsRaw is List
          ? variantsRaw
              .whereType<Map>()
              .map((e) => ProductVariant.fromJson(Map<String, dynamic>.from(e)))
              .toList()
          : const [],
    );
  }

  static String? resolveImageUrl(String? path, String apiBaseUrl) {
    if (path == null || path.isEmpty) return null;
    if (path.startsWith('http')) return path;
    final origin = apiBaseUrl.replaceAll(RegExp(r'/api/?$'), '');
    return '$origin$path';
  }
}

class ProductImage {
  const ProductImage({
    required this.id,
    required this.url,
    this.altText,
    this.isPrimary = false,
  });

  final String id;
  final String url;
  final String? altText;
  final bool isPrimary;

  factory ProductImage.fromJson(Map<String, dynamic> json) {
    return ProductImage(
      id: '${json['id']}',
      url: jsonString(json['url']),
      altText: jsonStringOrNull(json['altText']),
      isPrimary: json['isPrimary'] == true,
    );
  }
}

class ProductVariant {
  const ProductVariant({
    required this.id,
    required this.label,
    required this.price,
    required this.stock,
    required this.status,
  });

  final String id;
  final String label;
  final double price;
  final int stock;
  final String status;

  bool get isActive => status == 'active';

  factory ProductVariant.fromJson(Map<String, dynamic> json) {
    return ProductVariant(
      id: '${json['id']}',
      label: jsonString(json['label']),
      price: (json['price'] as num?)?.toDouble() ?? 0,
      stock: (json['stock'] as num?)?.toInt() ?? 0,
      status: jsonString(json['status'], 'active'),
    );
  }
}

class ProductVariantInput {
  const ProductVariantInput({
    required this.label,
    required this.price,
    this.stock,
    this.status,
  });

  final String label;
  final double price;
  final int? stock;
  final String? status;

  Map<String, dynamic> toJson() {
    return {
      'label': label,
      'price': price,
      if (stock != null) 'stock': stock,
      if (status != null) 'status': status,
    };
  }
}

class ProductInput {
  const ProductInput({
    required this.name,
    this.description,
    required this.price,
    this.category,
    this.productType,
    this.fulfillmentType,
    this.trackInventory,
    this.requiresDeliveryAddress,
    this.accessUrl,
    this.serviceBookingUrl,
    this.fulfillmentInstructions,
    this.licenseKeyMode,
    this.licenseKeyPrefix,
    this.accessExpiresDays,
    this.maxDownloads,
    this.bookable,
    this.bookingDurationMinutes,
    this.licenseKeys,
    required this.stock,
    this.status,
  });

  final String name;
  final String? description;
  final double price;
  final String? category;
  final String? productType;
  final String? fulfillmentType;
  final bool? trackInventory;
  final bool? requiresDeliveryAddress;
  final String? accessUrl;
  final String? serviceBookingUrl;
  final String? fulfillmentInstructions;
  final String? licenseKeyMode;
  final String? licenseKeyPrefix;
  final int? accessExpiresDays;
  final int? maxDownloads;
  final bool? bookable;
  final int? bookingDurationMinutes;
  final String? licenseKeys;
  final int stock;
  final String? status;

  Map<String, dynamic> toJson({required bool isUpdate}) {
    final data = <String, dynamic>{
      'name': name,
      if (description != null) 'description': description,
      'price': price,
      if (category != null) 'category': category,
      if (productType != null) 'productType': productType,
      if (fulfillmentType != null) 'fulfillmentType': fulfillmentType,
      if (trackInventory != null) 'trackInventory': trackInventory,
      if (requiresDeliveryAddress != null)
        'requiresDeliveryAddress': requiresDeliveryAddress,
      if (isUpdate || (accessUrl != null && accessUrl!.isNotEmpty))
        'accessUrl': accessUrl ?? '',
      if (isUpdate ||
          (serviceBookingUrl != null && serviceBookingUrl!.isNotEmpty))
        'serviceBookingUrl': serviceBookingUrl ?? '',
      if (isUpdate ||
          (fulfillmentInstructions != null &&
              fulfillmentInstructions!.isNotEmpty))
        'fulfillmentInstructions': fulfillmentInstructions ?? '',
      if (licenseKeyMode != null) 'licenseKeyMode': licenseKeyMode,
      if (isUpdate ||
          (licenseKeyPrefix != null && licenseKeyPrefix!.isNotEmpty))
        'licenseKeyPrefix': licenseKeyPrefix ?? '',
      // Always send on update so blank clears expiry.
      if (isUpdate || accessExpiresDays != null)
        'accessExpiresDays': accessExpiresDays,
      // Always send on update so blank clears the download limit and duration.
      if (isUpdate || maxDownloads != null) 'maxDownloads': maxDownloads,
      if (bookable != null) 'bookable': bookable,
      if (isUpdate || bookingDurationMinutes != null)
        'bookingDurationMinutes': bookingDurationMinutes,
      if (licenseKeys != null && licenseKeys!.isNotEmpty)
        'licenseKeys': licenseKeys,
      'stock': stock,
    };
    if (isUpdate && status != null) {
      data['status'] = status;
    }
    return data;
  }
}
