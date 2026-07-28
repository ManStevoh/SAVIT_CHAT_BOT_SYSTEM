import 'package:flutter_test/flutter_test.dart';

import 'package:essem_mobile/features/products/product_models.dart';

void main() {
  group('Product.fromJson', () {
    test('parses a full physical product payload', () {
      final product = Product.fromJson({
        'id': 12,
        'name': 'Blue Mug',
        'description': 'Ceramic mug',
        'price': 15.5,
        'taxRateId': '3',
        'category': 'Kitchen',
        'productType': 'physical',
        'fulfillmentType': 'shipping',
        'image': '/storage/products/mug.jpg',
        'trackInventory': true,
        'requiresDeliveryAddress': true,
        'stock': 40,
        'status': 'active',
        'createdAt': '2026-01-01T00:00:00Z',
        'images': [
          {'id': 1, 'url': '/img1.jpg', 'isPrimary': true},
        ],
        'variants': [
          {'id': 1, 'label': 'Small', 'price': 15.5, 'stock': 20, 'status': 'active'},
        ],
      });

      expect(product.id, '12');
      expect(product.name, 'Blue Mug');
      expect(product.price, 15.5);
      expect(product.taxRateId, '3');
      expect(product.productType, 'physical');
      expect(product.isActive, isTrue);
      expect(product.trackInventory, isTrue);
      expect(product.images, hasLength(1));
      expect(product.images.first.isPrimary, isTrue);
      expect(product.variants, hasLength(1));
      expect(product.variants.first.label, 'Small');
    });

    test('applies sensible defaults for a minimal digital product', () {
      final product = Product.fromJson({
        'id': '7',
        'name': 'E-book',
        'description': null,
        'price': 9.99,
        'category': 'Books',
        'productType': 'digital',
        'image': null,
        'stock': 0,
        'status': 'inactive',
        'createdAt': '2026-01-01',
      });

      expect(product.productType, 'digital');
      expect(product.isActive, isFalse);
      expect(product.trackInventory, isTrue);
      expect(product.requiresDeliveryAddress, isTrue);
      expect(product.hasDigitalFile, isFalse);
      expect(product.images, isEmpty);
      expect(product.variants, isEmpty);
    });

    test('detects hasDigitalFile from digitalFileName when flag is missing', () {
      final product = Product.fromJson({
        'id': '9',
        'name': 'Guide',
        'description': '',
        'price': 5,
        'category': '',
        'productType': 'digital',
        'image': null,
        'stock': 0,
        'status': 'active',
        'createdAt': '2026-01-01',
        'digitalFileName': 'guide.pdf',
      });

      expect(product.hasDigitalFile, isTrue);
      expect(product.digitalFileName, 'guide.pdf');
    });

    test('tolerates non-string JSON values without throwing', () {
      final product = Product.fromJson({
        'id': 5,
        'name': 42,
        'description': null,
        'price': 99.9,
        'category': true,
        'image': null,
        'stock': 4,
        'status': 'active',
        'createdAt': 2026,
      });

      expect(product.name, '42');
      expect(product.description, '');
      expect(product.category, 'true');
    });
  });

  group('Product.resolveImageUrl', () {
    test('returns null for empty or missing paths', () {
      expect(Product.resolveImageUrl(null, 'https://api.example.com/api'), isNull);
      expect(Product.resolveImageUrl('', 'https://api.example.com/api'), isNull);
    });

    test('returns absolute URLs unchanged', () {
      const url = 'https://cdn.example.com/img.jpg';
      expect(
        Product.resolveImageUrl(url, 'https://api.example.com/api'),
        url,
      );
    });

    test('joins relative storage paths with the API origin', () {
      final resolved = Product.resolveImageUrl(
        '/storage/products/mug.jpg',
        'https://api.example.com/api',
      );
      expect(resolved, 'https://api.example.com/storage/products/mug.jpg');
    });
  });

  group('ProductVariant.fromJson', () {
    test('parses fields and defaults status to active', () {
      final variant = ProductVariant.fromJson({
        'id': 4,
        'label': 'Large',
        'price': 22,
        'stock': 5,
      });

      expect(variant.id, '4');
      expect(variant.label, 'Large');
      expect(variant.price, 22.0);
      expect(variant.isActive, isTrue);
    });
  });

  group('ProductInput.toJson', () {
    test('omits taxRateId/accessUrl style optional fields on create', () {
      const input = ProductInput(
        name: 'New Item',
        price: 10,
        stock: 3,
      );

      final json = input.toJson(isUpdate: false);
      expect(json['name'], 'New Item');
      expect(json.containsKey('taxRateId'), isFalse);
      expect(json.containsKey('accessUrl'), isFalse);
      expect(json.containsKey('status'), isFalse);
    });

    test('always sends nullable clearable fields on update', () {
      const input = ProductInput(
        name: 'Existing Item',
        price: 10,
        stock: 3,
        status: 'inactive',
      );

      final json = input.toJson(isUpdate: true);
      expect(json['taxRateId'], isNull);
      expect(json['accessUrl'], '');
      expect(json['accessExpiresDays'], isNull);
      expect(json['status'], 'inactive');
    });
  });
}
