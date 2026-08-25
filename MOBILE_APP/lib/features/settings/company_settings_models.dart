import '../../core/money/money_formatter.dart';

class CompanyCommerceSettings {
  const CompanyCommerceSettings({
    this.displayCurrency = 'USD',
    this.currencySymbol,
    this.thousandsSeparator = ',',
    this.decimalSeparator = '.',
    this.taxEnabled = false,
    this.storeSlug,
    this.storefrontEnabled = false,
    this.storefrontUrl,
    this.linkInBioEnabled = false,
    this.ordersAcceptCod = false,
    this.deliveryFeesEnabled = false,
    this.defaultDeliveryFee = 0,
    this.freeDeliveryAbove,
    this.dineInEnabled = false,
  });

  final String displayCurrency;
  final String? currencySymbol;
  final String thousandsSeparator;
  final String decimalSeparator;
  final bool taxEnabled;
  final String? storeSlug;
  final bool storefrontEnabled;
  final String? storefrontUrl;
  final bool linkInBioEnabled;
  final bool ordersAcceptCod;
  final bool deliveryFeesEnabled;
  final double defaultDeliveryFee;
  final double? freeDeliveryAbove;
  final bool dineInEnabled;

  factory CompanyCommerceSettings.fromJson(Map<String, dynamic> json) {
    final thousands = MoneyFormatter.normalizeThousands(
      json['thousandsSeparator']?.toString(),
    );
    String? nonEmpty(dynamic value) {
      final raw = value?.toString().trim();
      if (raw == null || raw.isEmpty) return null;
      return raw;
    }

    return CompanyCommerceSettings(
      displayCurrency: MoneyFormatter.normalizeCurrencyCode(
        json['displayCurrency']?.toString(),
      ),
      currencySymbol: nonEmpty(json['currencySymbol']),
      thousandsSeparator: thousands,
      decimalSeparator: MoneyFormatter.normalizeDecimal(
        json['decimalSeparator']?.toString(),
        thousands,
      ),
      taxEnabled: json['taxEnabled'] == true,
      storeSlug: nonEmpty(json['storeSlug']),
      storefrontEnabled: json['storefrontEnabled'] == true,
      storefrontUrl: nonEmpty(json['storefrontUrl']),
      linkInBioEnabled: json['linkInBioEnabled'] == true,
      ordersAcceptCod: json['ordersAcceptCod'] == true,
      deliveryFeesEnabled: json['deliveryFeesEnabled'] == true,
      defaultDeliveryFee: (json['defaultDeliveryFee'] as num?)?.toDouble() ?? 0,
      freeDeliveryAbove: (json['freeDeliveryAbove'] as num?)?.toDouble(),
      dineInEnabled: json['dineInEnabled'] == true,
    );
  }

  MoneyFormatter get moneyFormatter => MoneyFormatter(
        currencyCode: displayCurrency,
        symbol: currencySymbol,
        thousandsSeparator: thousandsSeparator,
        decimalSeparator: decimalSeparator,
      );

  CompanyCommerceSettings copyWith({
    String? displayCurrency,
    String? currencySymbol,
    bool clearCurrencySymbol = false,
    String? thousandsSeparator,
    String? decimalSeparator,
    bool? taxEnabled,
    String? storeSlug,
    bool clearStoreSlug = false,
    bool? storefrontEnabled,
    String? storefrontUrl,
    bool clearStorefrontUrl = false,
    bool? linkInBioEnabled,
    bool? ordersAcceptCod,
    bool? deliveryFeesEnabled,
    double? defaultDeliveryFee,
    double? freeDeliveryAbove,
    bool clearFreeDeliveryAbove = false,
    bool? dineInEnabled,
  }) {
    return CompanyCommerceSettings(
      displayCurrency: displayCurrency ?? this.displayCurrency,
      currencySymbol:
          clearCurrencySymbol ? null : (currencySymbol ?? this.currencySymbol),
      thousandsSeparator: thousandsSeparator ?? this.thousandsSeparator,
      decimalSeparator: decimalSeparator ?? this.decimalSeparator,
      taxEnabled: taxEnabled ?? this.taxEnabled,
      storeSlug: clearStoreSlug ? null : (storeSlug ?? this.storeSlug),
      storefrontEnabled: storefrontEnabled ?? this.storefrontEnabled,
      storefrontUrl:
          clearStorefrontUrl ? null : (storefrontUrl ?? this.storefrontUrl),
      linkInBioEnabled: linkInBioEnabled ?? this.linkInBioEnabled,
      ordersAcceptCod: ordersAcceptCod ?? this.ordersAcceptCod,
      deliveryFeesEnabled: deliveryFeesEnabled ?? this.deliveryFeesEnabled,
      defaultDeliveryFee: defaultDeliveryFee ?? this.defaultDeliveryFee,
      freeDeliveryAbove: clearFreeDeliveryAbove
          ? null
          : (freeDeliveryAbove ?? this.freeDeliveryAbove),
      dineInEnabled: dineInEnabled ?? this.dineInEnabled,
    );
  }
}
