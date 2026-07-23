import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/auth/auth_controller.dart';
import '../../core/branding/app_branding.dart';
import '../../core/branding/branding_repository.dart';
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
    if (!auth.isReady) {
      Future<void>.delayed(const Duration(milliseconds: 200), _continue);
      return;
    }
    if (GoRouter.maybeOf(context) == null) return;
    context.go(auth.isAuthenticated ? '/home' : '/login');
  }

  @override
  Widget build(BuildContext context) {
    final logo = _branding.appLogo;
    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [AppColors.canvasDeep, Colors.white, AppColors.canvas],
          ),
        ),
        child: FadeTransition(
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
                      errorBuilder: (_, __, ___) => const _EssemMark(),
                    ),
                  )
                else
                  const _EssemMark(),
                const SizedBox(height: 18),
                Text(
                  _branding.applicationName,
                  style: GoogleFonts.manrope(
                    fontSize: 34,
                    fontWeight: FontWeight.w800,
                    color: AppColors.primaryDark,
                    letterSpacing: 0.2,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Company companion',
                  style: GoogleFonts.manrope(color: AppColors.textMuted),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _EssemMark extends StatelessWidget {
  const _EssemMark();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 96,
      height: 96,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withOpacity(0.14),
            blurRadius: 28,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      alignment: Alignment.center,
      child: Text(
        'E',
        style: GoogleFonts.manrope(
          fontSize: 46,
          fontWeight: FontWeight.w800,
          color: AppColors.primary,
        ),
      ),
    );
  }
}
