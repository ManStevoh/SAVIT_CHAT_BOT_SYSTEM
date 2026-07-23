import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

import 'package:essem_mobile/core/auth/auth_controller.dart';
import 'package:essem_mobile/core/branding/app_branding.dart';
import 'package:essem_mobile/core/branding/branding_repository.dart';
import 'package:essem_mobile/core/onboarding/onboarding_controller.dart';
import 'package:essem_mobile/core/theme/app_theme.dart';
import 'package:essem_mobile/features/onboarding/onboarding_screen.dart';
import 'package:essem_mobile/features/splash/splash_screen.dart';

void main() {
  testWidgets('shows splash branding', (tester) async {
    final auth = AuthController.memory();
    await auth.bootstrap();

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider.value(value: auth),
          ChangeNotifierProvider.value(
            value: OnboardingController.memory(completed: true),
          ),
          Provider.value(
            value: BrandingRepository.seeded(
              const AppBranding(applicationName: 'RelayIQ'),
            ),
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const SplashScreen(),
        ),
      ),
    );

    await tester.pump(); // post-frame bootstrap
    expect(find.text('RelayIQ'), findsOneWidget);
    expect(find.text('Every Conversation. Smarter.'), findsOneWidget);

    // Advance past splash delay; no GoRouter so navigation no-ops.
    await tester.pump(const Duration(milliseconds: 800));
  });

  testWidgets('onboarding shows first page and can skip', (tester) async {
    final onboarding = OnboardingController.memory();

    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: onboarding,
        child: MaterialApp(
          theme: AppTheme.light,
          home: const OnboardingScreen(),
        ),
      ),
    );

    expect(find.textContaining('Sell on WhatsApp'), findsOneWidget);
    expect(find.text('Next'), findsOneWidget);

    await tester.tap(find.text('Skip'));
    await tester.pump();
    expect(onboarding.hasCompleted, isTrue);
  });
}
