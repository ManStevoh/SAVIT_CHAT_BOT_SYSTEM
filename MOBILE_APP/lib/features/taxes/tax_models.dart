class TaxRate {
  const TaxRate({
    required this.id,
    required this.name,
    this.code,
    required this.rate,
    required this.isInclusive,
    required this.isDefault,
    required this.isActive,
  });

  final String id;
  final String name;
  final String? code;
  final double rate;
  final bool isInclusive;
  final bool isDefault;
  final bool isActive;

  factory TaxRate.fromJson(Map<String, dynamic> json) {
    return TaxRate(
      id: '${json['id']}',
      name: json['name']?.toString() ?? '',
      code: json['code']?.toString(),
      rate: (json['rate'] as num?)?.toDouble() ?? 0,
      isInclusive: json['isInclusive'] == true,
      isDefault: json['isDefault'] == true,
      isActive: json['isActive'] != false,
    );
  }
}

class TaxRateInput {
  const TaxRateInput({
    required this.name,
    this.code,
    required this.rate,
    this.isInclusive = false,
    this.isDefault = false,
    this.isActive = true,
  });

  final String name;
  final String? code;
  final double rate;
  final bool isInclusive;
  final bool isDefault;
  final bool isActive;

  Map<String, dynamic> toJson() => {
        'name': name,
        'code': code,
        'rate': rate,
        'isInclusive': isInclusive,
        'isDefault': isDefault,
        'isActive': isActive,
      };
}
