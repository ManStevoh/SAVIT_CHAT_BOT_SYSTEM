import 'package:go_router/go_router.dart';

import '../../features/admin/admin_home_screen.dart';
import '../../features/auth/forgot_password_screen.dart';
import '../../features/auth/login_screen.dart';
import '../../features/chats/chat_list_screen.dart';
import '../../features/chats/chat_thread_screen.dart';
import '../../features/contacts/add_contact_screen.dart';
import '../../features/contacts/contacts_screen.dart';
import '../../features/faqs/faqs_screen.dart';
import '../../features/growth/growth_screen.dart';
import '../../features/home/home_screen.dart';
import '../../features/more/more_screen.dart';
import '../../features/onboarding/onboarding_screen.dart';
import '../../features/orders/orders_screen.dart';
import '../../features/products/products_screen.dart';
import '../../features/settings/settings_screen.dart';
import '../../features/shell/app_shell.dart';
import '../../features/splash/splash_screen.dart';
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
                      if (extra is Map) {
                        name = extra['name']?.toString();
                        phone = extra['phone']?.toString();
                      }
                      return ChatThreadScreen(
                        chatId: state.pathParameters['chatId']!,
                        customerName: name,
                        customerPhone: phone,
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
                    path: 'products',
                    builder: (context, state) => const ProductsScreen(),
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
