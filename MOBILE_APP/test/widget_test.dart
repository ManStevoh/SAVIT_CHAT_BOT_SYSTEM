import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

import 'package:essem_mobile/core/auth/auth_controller.dart';
import 'package:essem_mobile/core/branding/app_branding.dart';
import 'package:essem_mobile/core/branding/branding_repository.dart';
import 'package:essem_mobile/core/theme/app_theme.dart';
import 'package:essem_mobile/features/splash/splash_screen.dart';

void main() {
  testWidgets('shows splash branding', (tester) async {
    final auth = AuthController.memory();
    await auth.bootstrap();

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider.value(value: auth),
          Provider.value(
            value: BrandingRepository.seeded(
              const AppBranding(applicationName: 'Essem'),
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
    expect(find.text('Essem'), findsOneWidget);
    expect(find.text('Company companion'), findsOneWidget);

    // Advance past splash delay; no GoRouter so navigation no-ops.
    await tester.pump(const Duration(milliseconds: 800));
  });
}
