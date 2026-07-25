import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

class CustomerAvatar extends StatelessWidget {
  const CustomerAvatar({
    super.key,
    required this.name,
    this.size = 48,
  });

  final String name;
  final double size;

  String get _initials {
    final parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((p) => p.isNotEmpty)
        .toList();
    if (parts.isEmpty) return '?';
    if (parts.length == 1) {
      final s = parts.first;
      return s.substring(0, s.length >= 2 ? 2 : 1).toUpperCase();
    }
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            AppColors.primary.withOpacity(0.18),
            AppColors.primarySoft,
          ],
        ),
        border: Border.all(color: AppColors.primary.withOpacity(0.12)),
      ),
      alignment: Alignment.center,
      child: Text(
        _initials,
        style: TextStyle(
          fontWeight: FontWeight.w800,
          fontSize: size * 0.32,
          color: AppColors.primaryDark,
        ),
      ),
    );
  }
}
