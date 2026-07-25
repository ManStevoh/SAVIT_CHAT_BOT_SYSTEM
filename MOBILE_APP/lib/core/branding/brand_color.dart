import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

/// Parse CSS / hex brand colors from the platform settings API.
Color? parseBrandColor(String? raw) {
  if (raw == null) return null;
  var value = raw.trim();
  if (value.isEmpty) return null;

  if (value.startsWith('rgb')) {
    final match = RegExp(r'rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)').firstMatch(value);
    if (match == null) return null;
    return Color.fromARGB(
      255,
      int.parse(match.group(1)!),
      int.parse(match.group(2)!),
      int.parse(match.group(3)!),
    );
  }

  if (value.startsWith('#')) value = value.substring(1);
  if (value.length == 3) {
    value = value.split('').map((c) => '$c$c').join();
  }
  if (value.length == 6) value = 'FF$value';
  if (value.length != 8) return null;
  final intColor = int.tryParse(value, radix: 16);
  if (intColor == null) return null;
  return Color(intColor);
}

Color brandDarken(Color color, [double amount = 0.18]) {
  return Color.lerp(color, const Color(0xFF000000), amount) ?? color;
}

Color brandSoft(Color color, [double amount = 0.88]) {
  return Color.lerp(color, const Color(0xFFFFFFFF), amount) ?? AppColors.primarySoft;
}
