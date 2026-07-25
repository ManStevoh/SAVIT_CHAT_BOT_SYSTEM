import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

/// Soft elevated surface — wallet / soft-UI style cards (large radius, soft shadow).
class AppSurface extends StatelessWidget {
  const AppSurface({
    super.key,
    required this.child,
    this.onTap,
    this.padding,
    this.margin,
    this.borderRadius,
    this.borderColor,
    this.color = AppColors.surface,
    this.elevation = true,
    this.showBorder = false,
  });

  final Widget child;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry? padding;
  final EdgeInsetsGeometry? margin;
  final BorderRadius? borderRadius;
  final Color? borderColor;
  final Color color;
  final bool elevation;
  final bool showBorder;

  @override
  Widget build(BuildContext context) {
    final radius = borderRadius ?? BorderRadius.circular(AppRadii.lg);
    final content = Padding(
      padding: padding ?? EdgeInsets.zero,
      child: child,
    );

    return Container(
      margin: margin,
      decoration: BoxDecoration(
        color: color,
        borderRadius: radius,
        border: showBorder
            ? Border.all(color: borderColor ?? AppColors.border)
            : null,
        boxShadow: elevation ? AppShadows.soft : null,
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: radius,
        clipBehavior: Clip.antiAlias,
        child: onTap == null
            ? content
            : InkWell(
                onTap: onTap,
                hoverColor: AppColors.primary.withOpacity(0.04),
                splashColor: AppColors.primary.withOpacity(0.08),
                highlightColor: AppColors.primary.withOpacity(0.05),
                child: content,
              ),
      ),
    );
  }
}

/// Colored hero band with rounded bottom corners (SkyPath / FinWise style).
class AppHeroBand extends StatelessWidget {
  const AppHeroBand({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.fromLTRB(20, 12, 20, 28),
    this.colors,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final List<Color>? colors;

  @override
  Widget build(BuildContext context) {
    final brand = Theme.of(context).colorScheme.primary;
    final dark = Color.lerp(brand, const Color(0xFF000000), 0.18) ?? brand;
    return Container(
      width: double.infinity,
      padding: padding,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: colors ?? [brand, dark],
        ),
        borderRadius: const BorderRadius.only(
          bottomLeft: Radius.circular(AppRadii.xl),
          bottomRight: Radius.circular(AppRadii.xl),
        ),
        boxShadow: [
          BoxShadow(
            color: brand.withOpacity(0.28),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: child,
    );
  }
}

/// Circular / rounded-square icon chip used in quick actions & metric cards.
class AppIconChip extends StatelessWidget {
  const AppIconChip({
    super.key,
    required this.icon,
    this.color = AppColors.primary,
    this.size = 44,
  });

  final IconData icon;
  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        borderRadius: BorderRadius.circular(size * 0.32),
      ),
      child: Icon(icon, color: color, size: size * 0.48),
    );
  }
}
