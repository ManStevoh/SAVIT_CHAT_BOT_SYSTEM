class Product {
  const Product({
    required this.id,
    required this.name,
    required this.description,
    required this.price,
    required this.category,
    this.image,
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
  final String? image;
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
      name: (json['name'] ?? '') as String,
      description: (json['description'] ?? '') as String,
      price: (json['price'] as num?)?.toDouble() ?? 0,
      category: (json['category'] ?? '') as String,
      image: json['image'] as String?,
      stock: (json['stock'] as num?)?.toInt() ?? 0,
      status: (json['status'] ?? 'active') as String,
      createdAt: (json['createdAt'] ?? '') as String,
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
      url: (json['url'] ?? '') as String,
      altText: json['altText'] as String?,
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
      label: (json['label'] ?? '') as String,
      price: (json['price'] as num?)?.toDouble() ?? 0,
      stock: (json['stock'] as num?)?.toInt() ?? 0,
      status: (json['status'] ?? 'active') as String,
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
    required this.stock,
    this.status,
  });

  final String name;
  final String? description;
  final double price;
  final String? category;
  final int stock;
  final String? status;

  Map<String, dynamic> toJson({required bool isUpdate}) {
    final data = <String, dynamic>{
      'name': name,
      if (description != null && description!.isNotEmpty) 'description': description,
      'price': price,
      if (category != null && category!.isNotEmpty) 'category': category,
      'stock': stock,
    };
    if (isUpdate && status != null) {
      data['status'] = status;
    }
    return data;
  }
}
