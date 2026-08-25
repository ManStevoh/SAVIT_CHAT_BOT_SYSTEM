import 'package:flutter_test/flutter_test.dart';

import 'package:essem_mobile/features/settings/company_settings_models.dart';

void main() {
  group('CompanyCommerceSettings.fromJson', () {
    test('parses storefront, delivery and dine-in fields', () {
      final settings = CompanyCommerceSettings.fromJson({
        'displayCurrency': 'KES',
        'currencySymbol': 'KSh',
        'thousandsSeparator': ',',
        'decimalSeparator': '.',
        'taxEnabled': true,
        'storeSlug': 'my-shop',
        'storefrontEnabled': true,
        'storefrontUrl': 'https://app.example.com/s/my-shop',
        'linkInBioEnabled': true,
        'ordersAcceptCod': true,
        'deliveryFeesEnabled': true,
        'defaultDeliveryFee': 4.5,
        'freeDeliveryAbove': 50,
        'dineInEnabled': true,
      });

      expect(settings.storeSlug, 'my-shop');
      expect(settings.storefrontEnabled, isTrue);
      expect(settings.storefrontUrl, 'https://app.example.com/s/my-shop');
      expect(settings.linkInBioEnabled, isTrue);
      expect(settings.ordersAcceptCod, isTrue);
      expect(settings.deliveryFeesEnabled, isTrue);
      expect(settings.defaultDeliveryFee, 4.5);
      expect(settings.freeDeliveryAbove, 50.0);
      expect(settings.dineInEnabled, isTrue);
    });

    test('defaults storefront fields when absent', () {
      final settings = CompanyCommerceSettings.fromJson({
        'displayCurrency': 'USD',
      });

      expect(settings.storeSlug, isNull);
      expect(settings.storefrontEnabled, isFalse);
      expect(settings.storefrontUrl, isNull);
      expect(settings.linkInBioEnabled, isFalse);
      expect(settings.ordersAcceptCod, isFalse);
      expect(settings.deliveryFeesEnabled, isFalse);
      expect(settings.defaultDeliveryFee, 0);
      expect(settings.freeDeliveryAbove, isNull);
      expect(settings.dineInEnabled, isFalse);
    });
  });

  group('CompanyCommerceSettings.copyWith', () {
    test('clears storeSlug and freeDeliveryAbove when requested', () {
      const settings = CompanyCommerceSettings(
        storeSlug: 'my-shop',
        freeDeliveryAbove: 50,
      );

      final cleared = settings.copyWith(
        clearStoreSlug: true,
        clearFreeDeliveryAbove: true,
      );

      expect(cleared.storeSlug, isNull);
      expect(cleared.freeDeliveryAbove, isNull);
    });
  });
}
