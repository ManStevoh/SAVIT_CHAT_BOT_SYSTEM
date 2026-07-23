import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import 'core/auth/auth_controller.dart';
import 'core/onboarding/onboarding_controller.dart';
import 'core/router/app_router.dart';
import 'core/theme/app_theme.dart';

class RelayApp extends StatefulWidget {
  const RelayApp({super.key});

  @override
  State<RelayApp> createState() => _RelayAppState();
}

class _RelayAppState extends State<RelayApp> {
  GoRouter? _router;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _router ??= createAppRouter(
      context.read<AuthController>(),
      context.read<OnboardingController>(),
    );
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'RelayIQ',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      routerConfig: _router!,
    );
  }
}
