import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';
import 'app_surface.dart';

/// Pulsing placeholder bars for list / dashboard loading.
class AppSkeleton extends StatefulWidget {
  const AppSkeleton({super.key, required this.child});

  final Widget child;

  @override
  State<AppSkeleton> createState() => _AppSkeletonState();
}

class _AppSkeletonState extends State<AppSkeleton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _pulse;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1100),
    )..repeat(reverse: true);
    _pulse = Tween<double>(begin: 0.45, end: 0.9).animate(
      CurvedAnimation(parent: _controller, curve: Curves.easeInOut),
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _pulse,
      builder: (context, child) => Opacity(opacity: _pulse.value, child: child),
      child: widget.child,
    );
  }
}

class SkeletonBox extends StatelessWidget {
  const SkeletonBox({
    super.key,
    required this.height,
    this.width,
    this.radius = AppRadii.md,
  });

  final double height;
  final double? width;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: height,
      width: width,
      decoration: BoxDecoration(
        color: AppColors.canvasDeep,
        borderRadius: BorderRadius.circular(radius),
      ),
    );
  }
}

class HomeSkeleton extends StatelessWidget {
  const HomeSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    final tileW = (MediaQuery.sizeOf(context).width - 44) / 2;
    final topPad = MediaQuery.paddingOf(context).top;
    return AppSkeleton(
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.zero,
        children: [
          Container(
            width: double.infinity,
            padding: EdgeInsets.fromLTRB(20, topPad + 16, 20, 28),
            decoration: const BoxDecoration(
              color: AppColors.primarySoft,
              borderRadius: BorderRadius.only(
                bottomLeft: Radius.circular(AppRadii.xl),
                bottomRight: Radius.circular(AppRadii.xl),
              ),
            ),
            child: const Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SkeletonBox(height: 14, width: 100, radius: 8),
                SizedBox(height: 10),
                SkeletonBox(height: 24, width: 180, radius: 10),
                SizedBox(height: 18),
                SkeletonBox(height: 14, width: 80, radius: 8),
                SizedBox(height: 8),
                SkeletonBox(height: 36, width: 140, radius: 10),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 20, 16, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SkeletonBox(height: 16, width: 120),
                const SizedBox(height: 14),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: List.generate(
                    4,
                    (_) => const SkeletonBox(height: 52, width: 52, radius: 16),
                  ),
                ),
                const SizedBox(height: 22),
                const SkeletonBox(height: 16, width: 100),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 12,
                  runSpacing: 12,
                  children: List.generate(
                    4,
                    (_) => SkeletonBox(
                      height: 110,
                      width: tileW,
                      radius: AppRadii.lg,
                    ),
                  ),
                ),
                const SizedBox(height: 22),
                const SkeletonBox(height: 16, width: 130),
                const SizedBox(height: 12),
                const AppSurface(
                  elevation: false,
                  padding: EdgeInsets.all(16),
                  child: SkeletonBox(height: 48, radius: 12),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class ChatListSkeleton extends StatelessWidget {
  const ChatListSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return AppSkeleton(
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
        itemCount: 8,
        separatorBuilder: (_, __) => const SizedBox(height: 10),
        itemBuilder: (_, __) => const AppSurface(
          elevation: false,
          padding: EdgeInsets.symmetric(horizontal: 12, vertical: 12),
          child: Row(
            children: [
              SkeletonBox(height: 48, width: 48, radius: 24),
              SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SkeletonBox(height: 14, width: 140),
                    SizedBox(height: 8),
                    SkeletonBox(height: 12),
                  ],
                ),
              ),
              SizedBox(width: 12),
              SkeletonBox(height: 12, width: 36),
            ],
          ),
        ),
      ),
    );
  }
}
