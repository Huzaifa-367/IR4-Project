import 'package:auto_route/auto_route.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ir4_mobile/core/di/injector.dart';
import 'package:ir4_mobile/core/router/app_router.dart';
import 'package:ir4_mobile/core/theme/app_theme.dart';
import 'package:ir4_mobile/core/widgets/app_background.dart';
import 'package:ir4_mobile/features/auth/presentation/auth_controller.dart';
import 'package:ir4_mobile/features/auth/presentation/auth_state.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const ProviderScope(child: Ir4MobileApp()));
}

class Ir4MobileApp extends ConsumerStatefulWidget {
  const Ir4MobileApp({super.key});

  @override
  ConsumerState<Ir4MobileApp> createState() => _Ir4MobileAppState();
}

class _Ir4MobileAppState extends ConsumerState<Ir4MobileApp> {
  AppRouter? _router;
  bool _bootstrapped = false;

  @override
  void initState() {
    super.initState();
    configureDependencies(
      onUnauthorized: () {
        ref
            .read(authControllerProvider.notifier)
            .markUnauthenticated('Session expired. Sign in again.');
      },
    );
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await ref.read(authControllerProvider.notifier).bootstrap();
      if (mounted) {
        setState(() => _bootstrapped = true);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    _router ??= AppRouter(ref: ref);
    ref.listen<AuthState>(authControllerProvider, (
      AuthState? previous,
      AuthState next,
    ) {
      if (next is AuthUnauthenticated && previous is AuthAuthenticated) {
        _router?.replaceAll(<PageRouteInfo>[const LoginRoute()]);
      }
      if (next is AuthAuthenticated && previous is! AuthAuthenticated) {
        _router?.replaceAll(<PageRouteInfo>[const ScanRoute()]);
      }
    });

    if (!_bootstrapped) {
      return MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: AppTheme.dark(),
        home: const Scaffold(
          backgroundColor: Colors.transparent,
          body: AppBackground(
            child: Center(child: CircularProgressIndicator()),
          ),
        ),
      );
    }

    return MaterialApp.router(
      title: 'IR4 Mobile',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.dark(),
      routerConfig: _router!.config(),
    );
  }
}
