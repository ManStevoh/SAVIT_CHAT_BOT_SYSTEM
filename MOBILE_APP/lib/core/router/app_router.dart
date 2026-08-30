import 'package:flutter/foundation.dart';
import 'package:go_router/go_router.dart';

import '../../features/admin/admin_home_screen.dart';
import '../../features/analytics/analytics_screen.dart';
import '../../features/auth/forgot_password_screen.dart';
import '../../features/auth/login_screen.dart';
import '../../features/bookings/bookings_screen.dart';
import '../../features/campaigns/campaigns_screen.dart';
import '../../features/chats/chat_list_screen.dart';
import '../../features/chats/chat_thread_screen.dart';
import '../../features/contacts/add_contact_screen.dart';
import '../../features/contacts/contacts_screen.dart';
import '../../features/coupons/coupons_screen.dart';
import '../../features/delivery/delivery_screen.dart';
import '../../features/dine_in/dine_in_screen.dart';
import '../../features/faqs/faqs_screen.dart';
import '../../features/growth/growth_screen.dart';
import '../../features/home/home_screen.dart';
import '../../features/more/more_screen.dart';
import '../../features/onboarding/onboarding_screen.dart';
import '../../features/orders/orders_screen.dart';
import '../../features/products/products_screen.dart';
import '../../features/settings/ai_settings_screen.dart';
import '../../features/settings/business_settings_screen.dart';
import '../../features/settings/currency_settings_screen.dart';
import '../../features/settings/payments_settings_screen.dart';
import '../../features/settings/settings_screen.dart';
import '../../features/settings/whatsapp_status_screen.dart';
import '../../features/shell/app_shell.dart';
import '../../features/splash/splash_screen.dart';
import '../../features/storefront/storefront_settings_screen.dart';
import '../../features/subscription/subscription_screen.dart';
import '../../features/taxes/taxes_screen.dart';
import '../../features/team/team_screen.dart';
import '../auth/auth_controller.dart';
import '../onboarding/onboarding_controller.dart';

GoRouter createAppRouter(
  AuthController auth,
  OnboardingController onboarding,
) {
  return GoRouter(
    initialLocation: '/splash',
    refreshListenable: Listenable.merge([auth, onboarding]),
    redirect: (context, state) {
      final loc = state.matchedLocation;
      final onSplash = loc == '/splash';
      final onLogin = loc == '/login';
      final onForgot = loc == '/forgot-password';
      final onOnboarding = loc == '/onboarding';

      if (!auth.isReady || !onboarding.isReady) {
        return onSplash ? null : '/splash';
      }

      if (!auth.isAuthenticated) {
        if (onSplash) return null;
        if (!onboarding.hasCompleted) {
          return onOnboarding ? null : '/onboarding';
        }
        if (onOnboarding) return '/login';
        if (onForgot) return null;
        return onLogin ? null : '/login';
      }

      if (onSplash || onLogin || onForgot || onOnboarding) {
        final user = auth.user;
        if (user?.isPlatformAdminOnly ?? false) {
          return '/more/admin';
        }
        return '/home';
      }

      if (loc.startsWith('/more/admin') &&
          !(auth.user?.isPlatformAdmin ?? false)) {
        return '/more';
      }

      final adminOnly = auth.user?.isPlatformAdminOnly ?? false;
      if (adminOnly) {
        final allowed = loc.startsWith('/more/admin') ||
            loc.startsWith('/more/settings');
        if (!allowed) return '/more/admin';
      }

      return null;
    },
    routes: [
      GoRoute(
        path: '/splash',
        builder: (context, state) => const SplashScreen(),
      ),
      GoRoute(
        path: '/onboarding',
        builder: (context, state) => const OnboardingScreen(),
      ),
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: '/forgot-password',
        builder: (context, state) => const ForgotPasswordScreen(),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) {
          return AppShell(navigationShell: navigationShell);
        },
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/home',
                builder: (context, state) => const HomeScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/chats',
                builder: (context, state) => const ChatListScreen(),
                routes: [
                  GoRoute(
                    path: ':chatId',
                    builder: (context, state) {
                      final extra = state.extra;
                      String? name;
                      String? phone;
                      var isAgentHandling = false;
                      if (extra is Map) {
                        name = extra['name']?.toString();
                        phone = extra['phone']?.toString();
                        isAgentHandling = extra['isAgentHandling'] == true;
                      }
                      return ChatThreadScreen(
                        chatId: state.pathParameters['chatId']!,
                        customerName: name,
                        customerPhone: phone,
                        isAgentHandling: isAgentHandling,
                      );
                    },
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/contacts',
                builder: (context, state) => const ContactsScreen(),
                routes: [
                  GoRoute(
                    path: 'add',
                    builder: (context, state) => const AddContactScreen(),
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/orders',
                builder: (context, state) => const OrdersScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/more',
                builder: (context, state) => const MoreScreen(),
                routes: [
                  GoRoute(
                    path: 'analytics',
                    builder: (context, state) => const AnalyticsScreen(),
                  ),
                  GoRoute(
                    path: 'subscription',
                    builder: (context, state) => const SubscriptionScreen(),
                  ),
                  GoRoute(
                    path: 'products',
                    builder: (context, state) => const ProductsScreen(),
                  ),
                  GoRoute(
                    path: 'taxes',
                    builder: (context, state) => const TaxesScreen(),
                  ),
                  GoRoute(
                    path: 'currency',
                    builder: (context, state) => const CurrencySettingsScreen(),
                  ),
                  GoRoute(
                    path: 'storefront',
                    builder: (context, state) =>
                        const StorefrontSettingsScreen(),
                  ),
                  GoRoute(
                    path: 'delivery',
                    builder: (context, state) => const DeliveryScreen(),
                  ),
                  GoRoute(
                    path: 'dine-in',
                    builder: (context, state) => const DineInScreen(),
                  ),
                  GoRoute(
                    path: 'coupons',
                    builder: (context, state) => const CouponsScreen(),
                  ),
                  GoRoute(
                    path: 'bookings',
                    builder: (context, state) => const BookingsScreen(),
                  ),
                  GoRoute(
                    path: 'campaigns',
                    builder: (context, state) => const CampaignsScreen(),
                  ),
                  GoRoute(
                    path: 'faqs',
                    builder: (context, state) => const FaqsScreen(),
                  ),
                  GoRoute(
                    path: 'growth',
                    builder: (context, state) => const GrowthScreen(),
                  ),
                  GoRoute(
                    path: 'team',
                    builder: (context, state) => const TeamScreen(),
                  ),
                  GoRoute(
                    path: 'whatsapp',
                    builder: (context, state) => const WhatsAppStatusScreen(),
                  ),
                  GoRoute(
                    path: 'ai',
                    builder: (context, state) => const AiSettingsScreen(),
                  ),
                  GoRoute(
                    path: 'business',
                    builder: (context, state) => const BusinessSettingsScreen(),
                  ),
                  GoRoute(
                    path: 'payments',
                    builder: (context, state) => const PaymentsSettingsScreen(),
                  ),
                  GoRoute(
                    path: 'settings',
                    builder: (context, state) => const SettingsScreen(),
                  ),
                  GoRoute(
                    path: 'admin',
                    builder: (context, state) => const AdminHomeScreen(),
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    ],
  );
}
