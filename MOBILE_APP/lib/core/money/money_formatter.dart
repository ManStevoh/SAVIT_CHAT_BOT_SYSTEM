/// Formats money amounts using company display preferences.
class MoneyFormatter {
  const MoneyFormatter({
    this.currencyCode = 'USD',
    this.symbol,
    this.thousandsSeparator = ',',
    this.decimalSeparator = '.',
  });

  final String currencyCode;
  final String? symbol;
  final String thousandsSeparator;
  final String decimalSeparator;

  static const _zeroDecimal = {'JPY', 'KRW', 'VND', 'CLP'};

  static String normalizeCurrencyCode(String? code) {
    final raw = (code ?? 'USD').replaceAll(RegExp(r'[^A-Za-z]'), '').toUpperCase();
    return raw.length >= 3 ? raw.substring(0, 3) : 'USD';
  }

  static String normalizeThousands(String? value) {
    if (value == '.' || value == ',' || value == ' ' || value == "'") {
      return value!;
    }
    return ',';
  }

  static String normalizeDecimal(String? value, String thousands) {
    var decimal = (value == '.' || value == ',')
        ? value!
        : (thousands == '.' ? ',' : '.');
    if (decimal == thousands) {
      decimal = thousands == ',' ? '.' : ',';
    }
    return decimal;
  }

  static String pairedDecimalForThousands(String thousands) {
    return thousands == '.' ? ',' : '.';
  }

  String format(num amount) {
    final code = normalizeCurrencyCode(currencyCode);
    final thousands = normalizeThousands(thousandsSeparator);
    final decimal = normalizeDecimal(decimalSeparator, thousands);
    final prefix = (symbol != null && symbol!.trim().isNotEmpty)
        ? symbol!.trim()
        : code;
    final zeroDecimal = _zeroDecimal.contains(code);
    final value = amount.toDouble();
    final abs = value.abs();
    final fixed = abs.toStringAsFixed(zeroDecimal ? 0 : 2);
    final parts = fixed.split('.');
    final intPart = parts[0];
    final fracPart = parts.length > 1 ? parts[1] : '';
    final withThousands = intPart.replaceAllMapped(
      RegExp(r'\B(?=(\d{3})+(?!\d))'),
      (_) => thousands,
    );
    final number = zeroDecimal
        ? withThousands
        : '$withThousands$decimal$fracPart';
    final sign = value < 0 ? '-' : '';
    return '$prefix $sign$number';
  }
}
