import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:essem_mobile/core/branding/brand_color.dart';
import 'package:essem_mobile/core/theme/app_theme.dart';

void main() {
  group('parseBrandColor', () {
    test('parses #RRGGBB', () {
      expect(parseBrandColor('#6D28D9'), const Color(0xFF6D28D9));
    });

    test('parses RRGGBB without hash', () {
      expect(parseBrandColor('2563EB'), const Color(0xFF2563EB));
    });

    test('parses short #RGB', () {
      expect(parseBrandColor('#0F0'), const Color(0xFF00FF00));
    });

    test('parses rgb()', () {
      expect(parseBrandColor('rgb(109, 40, 217)'), const Color(0xFF6D28D9));
    });

    test('returns null for empty / invalid', () {
      expect(parseBrandColor(null), isNull);
      expect(parseBrandColor(''), isNull);
      expect(parseBrandColor('not-a-color'), isNull);
    });
  });

  group('brand helpers', () {
    test('darken moves toward black', () {
      final darkened = brandDarken(AppColors.primary, 0.2);
      expect(darkened.computeLuminance(),
          lessThan(AppColors.primary.computeLuminance()));
    });

    test('soft moves toward white', () {
      final soft = brandSoft(AppColors.primary, 0.88);
      expect(soft.computeLuminance(),
          greaterThan(AppColors.primary.computeLuminance()));
    });
  });
}
