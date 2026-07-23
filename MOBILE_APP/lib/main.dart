import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'app.dart';
import 'core/auth/auth_controller.dart';
import 'core/branding/branding_repository.dart';
import 'core/config/app_config.dart';
import 'core/network/api_client.dart';
import 'core/onboarding/onboarding_controller.dart';
import 'core/shell/shell_badges.dart';
import 'features/admin/admin_repository.dart';
import 'features/chats/chat_repository.dart';
import 'features/contacts/customer_repository.dart';
import 'features/faqs/faq_repository.dart';
import 'features/growth/growth_repository.dart';
import 'features/home/home_repository.dart';
import 'features/orders/order_repository.dart';
import 'features/products/product_repository.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  final config = AppConfig.fromEnvironment();
  final auth = AuthController();
  final onboarding = OnboardingController();
  await Future.wait([
    auth.bootstrap(),
    onboarding.bootstrap(),
  ]);

  final api = ApiClient(config: config, auth: auth);

  runApp(
    MultiProvider(
      providers: [
        Provider.value(value: config),
        ChangeNotifierProvider.value(value: auth),
        ChangeNotifierProvider.value(value: onboarding),
        ChangeNotifierProvider(create: (_) => ShellBadges()),
        Provider.value(value: api),
        Provider.value(value: BrandingRepository(api)),
        Provider.value(value: AuthRepository(api.dio, auth)),
        Provider.value(value: ChatRepository(api)),
        Provider.value(value: CustomerRepository(api)),
        Provider.value(value: HomeRepository(api)),
        Provider.value(value: OrderRepository(api)),
        Provider.value(value: ProductRepository(api)),
        Provider.value(value: FaqRepository(api)),
        Provider.value(value: GrowthRepository(api)),
        Provider.value(value: AdminRepository(api)),
      ],
      child: const RelayApp(),
    ),
  );
}
