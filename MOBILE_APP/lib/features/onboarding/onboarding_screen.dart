import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/onboarding/onboarding_controller.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';

class OnboardingScreen extends StatefulWidget {
  const OnboardingScreen({super.key});

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends State<OnboardingScreen> {
  final _page = PageController();
  int _index = 0;

  static const _pages = <_OnboardPage>[
    _OnboardPage(
      title: 'Sell on WhatsApp, from your pocket',
      body:
          'RelayIQ keeps your company chats, orders, and customers in one companion built for owners and agents.',
      accent: AppColors.primary,
      icon: Icons.chat_bubble_rounded,
    ),
    _OnboardPage(
      title: 'AI replies — you stay in control',
      body:
          'Watch conversations in real time, jump in when a customer needs a human, then hand back to the bot.',
      accent: AppColors.accentTeal,
      icon: Icons.smart_toy_rounded,
    ),
    _OnboardPage(
      title: 'Orders & contacts, always handy',
      body:
          'Track revenue signals, open order details from notifications, and start chats from your phone book.',
      accent: AppColors.accentAmber,
      icon: Icons.receipt_long_rounded,
    ),
    _OnboardPage(
      title: 'Ready for your workspace',
      body:
          'Sign in with your RelayIQ company account to manage chats, products, FAQs, and growth on the go.',
      accent: AppColors.primaryDark,
      icon: Icons.rocket_launch_rounded,
    ),
  ];

  bool get _isLast => _index >= _pages.length - 1;

  Future<void> _finish() async {
    await context.read<OnboardingController>().complete();
    if (!mounted) return;
    if (GoRouter.maybeOf(context) != null) {
      context.go('/login');
    }
  }

  void _next() {
    if (_isLast) {
      _finish();
      return;
    }
    _page.nextPage(
      duration: const Duration(milliseconds: 320),
      curve: Curves.easeOutCubic,
    );
  }

  @override
  void dispose() {
    _page.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final page = _pages[_index];

    return Scaffold(
      backgroundColor: AppColors.canvas,
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(8, 4, 8, 0),
              child: Row(
                children: [
                  TextButton(
                    onPressed: _finish,
                    child: const Text('Skip'),
                  ),
                  const Spacer(),
                  Text(
                    '${_index + 1} / ${_pages.length}',
                    style: GoogleFonts.manrope(
                      color: AppColors.textMuted,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(width: 12),
                ],
              ),
            ),
            Expanded(
              child: PageView.builder(
                controller: _page,
                itemCount: _pages.length,
                onPageChanged: (i) => setState(() => _index = i),
                itemBuilder: (context, i) {
                  final item = _pages[i];
                  return Padding(
                    padding: const EdgeInsets.fromLTRB(20, 8, 20, 8),
                    child: Center(
                      child: SingleChildScrollView(
                        child: AppSurface(
                          padding: const EdgeInsets.fromLTRB(24, 24, 24, 24),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              LayoutBuilder(
                                builder: (context, constraints) {
                                  final heroH =
                                      (MediaQuery.sizeOf(context).height * 0.18)
                                          .clamp(88.0, 160.0);
                                  return Container(
                                    width: double.infinity,
                                    height: heroH,
                                    decoration: BoxDecoration(
                                      gradient: LinearGradient(
                                        begin: Alignment.topLeft,
                                        end: Alignment.bottomRight,
                                        colors: [
                                          item.accent,
                                          item.accent.withOpacity(0.72),
                                        ],
                                      ),
                                      borderRadius:
                                          BorderRadius.circular(AppRadii.lg),
                                    ),
                                    child: Stack(
                                      alignment: Alignment.center,
                                      children: [
                                        Positioned(
                                          right: -20,
                                          top: -20,
                                          child: Container(
                                            width: 100,
                                            height: 100,
                                            decoration: BoxDecoration(
                                              shape: BoxShape.circle,
                                              color: Colors.white
                                                  .withOpacity(0.12),
                                            ),
                                          ),
                                        ),
                                        Icon(
                                          item.icon,
                                          size: heroH * 0.4,
                                          color: Colors.white,
                                        ),
                                      ],
                                    ),
                                  );
                                },
                              ),
                              const SizedBox(height: 22),
                              Text(
                                item.title,
                                textAlign: TextAlign.center,
                                style: GoogleFonts.manrope(
                                  fontSize: 24,
                                  fontWeight: FontWeight.w800,
                                  height: 1.2,
                                  color: AppColors.ink,
                                ),
                              ),
                              const SizedBox(height: 12),
                              Text(
                                item.body,
                                textAlign: TextAlign.center,
                                style: GoogleFonts.manrope(
                                  fontSize: 15,
                                  height: 1.45,
                                  color: AppColors.textMuted,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: List.generate(_pages.length, (i) {
                      final active = i == _index;
                      return AnimatedContainer(
                        duration: const Duration(milliseconds: 220),
                        margin: const EdgeInsets.symmetric(horizontal: 4),
                        height: 8,
                        width: active ? 24 : 8,
                        decoration: BoxDecoration(
                          color:
                              active ? page.accent : AppColors.borderStrong,
                          borderRadius: BorderRadius.circular(AppRadii.pill),
                        ),
                      );
                    }),
                  ),
                  const SizedBox(height: 22),
                  Row(
                    children: [
                      if (_index > 0)
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () => _page.previousPage(
                              duration: const Duration(milliseconds: 280),
                              curve: Curves.easeOutCubic,
                            ),
                            child: const Text('Back'),
                          ),
                        ),
                      if (_index > 0) const SizedBox(width: 12),
                      Expanded(
                        child: FilledButton(
                          onPressed: _next,
                          style: FilledButton.styleFrom(
                            backgroundColor: page.accent,
                            minimumSize: const Size.fromHeight(54),
                          ),
                          child: Text(_isLast ? 'Get started' : 'Next'),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _OnboardPage {
  const _OnboardPage({
    required this.title,
    required this.body,
    required this.accent,
    required this.icon,
  });

  final String title;
  final String body;
  final Color accent;
  final IconData icon;
}
