import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../../core/theme/app_theme.dart';
import 'app_surface.dart';

class PlaceholderState extends StatelessWidget {
  const PlaceholderState({
    super.key,
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final brand = Theme.of(context).colorScheme.primary;
    return AppSurface(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          AppIconChip(icon: icon, color: brand, size: 52),
          const SizedBox(height: 14),
          Text(
            title,
            style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 6),
          Text(
            subtitle,
            textAlign: TextAlign.center,
            style: GoogleFonts.manrope(color: AppColors.textMuted),
          ),
        ],
      ),
    );
  }
}
