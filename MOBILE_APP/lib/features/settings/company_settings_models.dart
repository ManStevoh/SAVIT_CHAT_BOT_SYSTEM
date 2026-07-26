import '../../core/money/money_formatter.dart';

class CompanyCommerceSettings {
  const CompanyCommerceSettings({
    this.displayCurrency = 'USD',
    this.currencySymbol,
    this.thousandsSeparator = ',',
    this.decimalSeparator = '.',
    this.taxEnabled = false,
  });

  final String displayCurrency;
  final String? currencySymbol;
  final String thousandsSeparator;
  final String decimalSeparator;
  final bool taxEnabled;

  factory CompanyCommerceSettings.fromJson(Map<String, dynamic> json) {
    final thousands = MoneyFormatter.normalizeThousands(
      json['thousandsSeparator']?.toString(),
    );
    return CompanyCommerceSettings(
      displayCurrency: MoneyFormatter.normalizeCurrencyCode(
        json['displayCurrency']?.toString(),
      ),
      currencySymbol: () {
        final raw = json['currencySymbol']?.toString().trim();
        if (raw == null || raw.isEmpty) return null;
        return raw;
      }(),
      thousandsSeparator: thousands,
      decimalSeparator: MoneyFormatter.normalizeDecimal(
        json['decimalSeparator']?.toString(),
        thousands,
      ),
      taxEnabled: json['taxEnabled'] == true,
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
  }) {
    return CompanyCommerceSettings(
      displayCurrency: displayCurrency ?? this.displayCurrency,
      currencySymbol:
          clearCurrencySymbol ? null : (currencySymbol ?? this.currencySymbol),
      thousandsSeparator: thousandsSeparator ?? this.thousandsSeparator,
      decimalSeparator: decimalSeparator ?? this.decimalSeparator,
      taxEnabled: taxEnabled ?? this.taxEnabled,
    );
  }
}
