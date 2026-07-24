import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/auth/auth_controller.dart';
import '../../core/branding/app_branding.dart';
import '../../core/branding/branding_copy.dart';
import '../../core/branding/branding_repository.dart';
import '../../core/onboarding/onboarding_controller.dart';
import '../../core/theme/app_theme.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with SingleTickerProviderStateMixin {
  AppBranding _branding = AppBranding.fallback;
  late final AnimationController _fade;

  @override
  void initState() {
    super.initState();
    _fade = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 500),
    )..forward();
    WidgetsBinding.instance.addPostFrameCallback((_) => _bootstrap());
  }

  @override
  void dispose() {
    _fade.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    final branding = await context.read<BrandingRepository>().load();
    if (mounted) setState(() => _branding = branding);
    await Future<void>.delayed(const Duration(milliseconds: 700));
    _continue();
  }

  void _continue() {
    if (!mounted) return;
    final auth = context.read<AuthController>();
    final onboarding = context.read<OnboardingController>();
    if (!auth.isReady || !onboarding.isReady) {
      Future<void>.delayed(const Duration(milliseconds: 200), _continue);
      return;
    }
    if (GoRouter.maybeOf(context) == null) return;

    if (auth.isAuthenticated) {
      final adminOnly = auth.user?.isPlatformAdminOnly ?? false;
      context.go(adminOnly ? '/more/admin' : '/home');
      return;
    }
    context.go(onboarding.hasCompleted ? '/login' : '/onboarding');
  }

  @override
  Widget build(BuildContext context) {
    final logo = _branding.appLogo;
    return Scaffold(
      backgroundColor: AppColors.canvas,
      body: FadeTransition(
        opacity: _fade,
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (logo != null && logo.isNotEmpty)
                ClipRRect(
                  borderRadius: BorderRadius.circular(24),
                  child: Image.network(
                    logo,
                    width: 96,
                    height: 96,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => const _RelayMark(),
                  ),
                )
              else
                const _RelayMark(),
              const SizedBox(height: 18),
              Text(
                _branding.applicationName,
                style: GoogleFonts.manrope(
                  fontSize: 34,
                  fontWeight: FontWeight.w800,
                  color: AppColors.ink,
                  letterSpacing: 0.2,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                AppBrandingCopy.tagline,
                style: GoogleFonts.manrope(color: AppColors.textMuted),
              ),
              const SizedBox(height: 10),
              Text(
                AppBrandingCopy.poweredBy,
                textAlign: TextAlign.center,
                style: GoogleFonts.manrope(
                  fontSize: 12,
                  color: AppColors.textMuted,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RelayMark extends StatelessWidget {
  const _RelayMark();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 96,
      height: 96,
      decoration: BoxDecoration(
        color: const Color(0xFF0B0E11),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: AppColors.ink.withOpacity(0.08),
            blurRadius: 28,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      alignment: Alignment.center,
      child: Image.asset(
        'assets/branding/relaysiq-app-icon.png',
        width: 96,
        height: 96,
        fit: BoxFit.cover,
        errorBuilder: (_, __, ___) => Text(
          'R',
          style: GoogleFonts.manrope(
            fontSize: 46,
            fontWeight: FontWeight.w800,
            color: AppColors.primary,
          ),
        ),
      ),
    );
  }
}
