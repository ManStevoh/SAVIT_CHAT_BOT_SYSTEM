import 'package:flutter/material.dart';

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
    this.radius = 12,
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
        color: const Color(0xFFE4DCF0),
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
    return AppSkeleton(
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        children: [
          const SkeletonBox(height: 28, width: 180),
          const SizedBox(height: 10),
          const SkeletonBox(height: 14, width: 220),
          const SizedBox(height: 20),
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: List.generate(
              4,
              (_) => SkeletonBox(height: 96, width: tileW, radius: 16),
            ),
          ),
          const SizedBox(height: 28),
          const SkeletonBox(height: 18, width: 140),
          const SizedBox(height: 12),
          const SkeletonBox(height: 72, radius: 16),
          const SizedBox(height: 10),
          const SkeletonBox(height: 72, radius: 16),
          const SizedBox(height: 10),
          const SkeletonBox(height: 72, radius: 16),
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
        separatorBuilder: (_, __) => const SizedBox(height: 14),
        itemBuilder: (_, __) => const Row(
          children: [
            SkeletonBox(height: 44, width: 44, radius: 22),
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
    );
  }
}
