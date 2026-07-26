import 'package:auto_route/auto_route.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ir4_mobile/features/auth/presentation/auth_controller.dart';
import 'package:ir4_mobile/features/auth/presentation/auth_state.dart';
import 'package:ir4_mobile/features/auth/presentation/login_page.dart';
import 'package:ir4_mobile/features/equipment/presentation/equipment_detail_page.dart';
import 'package:ir4_mobile/features/scan/presentation/scan_page.dart';

part 'app_router.gr.dart';

@AutoRouterConfig(replaceInRouteName: 'Page,Route')
class AppRouter extends RootStackRouter {
  AppRouter({required this.ref});

  final WidgetRef ref;

  @override
  List<AutoRoute> get routes => <AutoRoute>[
        AutoRoute(page: LoginRoute.page, path: '/login', initial: true),
        AutoRoute(page: ScanRoute.page, path: '/scan'),
        AutoRoute(
          page: EquipmentDetailRoute.page,
          path: '/equipment/:qrToken',
        ),
      ];

  @override
  List<AutoRouteGuard> get guards => <AutoRouteGuard>[
        AutoRouteGuard.simple((
          NavigationResolver resolver,
          StackRouter router,
        ) {
          final AuthState auth = ref.read(authControllerProvider);
          final bool isLogin = resolver.route.name == LoginRoute.name;
          final bool authenticated = auth is AuthAuthenticated;
          if (!authenticated && !isLogin) {
            resolver.redirectUntil(const LoginRoute());
            return;
          }
          if (authenticated && isLogin) {
            resolver.redirectUntil(const ScanRoute());
            return;
          }
          resolver.next();
        }),
      ];
}
