import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:ir4_mobile/core/di/injector.dart';
import 'package:ir4_mobile/core/network/api_exception.dart';
import 'package:ir4_mobile/features/auth/data/auth_repository.dart';
import 'package:ir4_mobile/features/auth/domain/auth_user.dart';
import 'package:ir4_mobile/features/auth/presentation/auth_controller.dart';
import 'package:ir4_mobile/features/auth/presentation/auth_state.dart';

final class FakeAuthRepository implements AuthRepository {
  FakeAuthRepository({
    this.loginResult,
    this.restoreResult,
    this.loginError,
    this.restoreError,
  });

  LoginResult? loginResult;
  AuthUser? restoreResult;
  ApiException? loginError;
  ApiException? restoreError;
  bool logoutCalled = false;
  String? lastBaseUrl;

  @override
  Future<LoginResult> login({
    required String baseUrl,
    required String email,
    required String password,
    String deviceName = 'IR4 Android',
  }) async {
    lastBaseUrl = baseUrl;
    if (loginError != null) {
      throw loginError!;
    }
    return loginResult!;
  }

  @override
  Future<AuthUser?> restoreSession() async {
    if (restoreError != null) {
      throw restoreError!;
    }
    return restoreResult;
  }

  @override
  Future<void> logout() async {
    logoutCalled = true;
  }

  @override
  Future<String?> readBaseUrl() async => lastBaseUrl;
}

void main() {
  late FakeAuthRepository fakeRepository;
  late ProviderContainer container;

  const AuthUser inputUser = AuthUser(
    id: 1,
    uuid: 'user-uuid',
    name: 'Operator',
    email: 'ops@ir4.local',
    mustChangePassword: false,
    permissions: <String>['view-equipment', 'manage-equipment'],
  );

  setUp(() async {
    await resetDependencies();
    fakeRepository = FakeAuthRepository(
      loginResult: const LoginResult(token: 'token', user: inputUser),
      restoreResult: inputUser,
    );
    getIt.registerSingleton<AuthRepository>(fakeRepository);
    container = ProviderContainer();
  });

  tearDown(() async {
    container.dispose();
    await resetDependencies();
  });

  test('login transitions to authenticated on success', () async {
    final AuthController controller =
        container.read(authControllerProvider.notifier);

    await controller.login(
      baseUrl: 'https://10.0.0.10',
      email: 'ops@ir4.local',
      password: 'secret',
    );

    final AuthState actualState = container.read(authControllerProvider);
    expect(actualState, isA<AuthAuthenticated>());
    expect((actualState as AuthAuthenticated).user.email, 'ops@ir4.local');
    expect(fakeRepository.lastBaseUrl, 'https://10.0.0.10');
  });

  test('login surfaces ApiException message when credentials fail', () async {
    fakeRepository.loginError = const ApiException(
      code: 'VALIDATION_FAILED',
      message: 'These credentials do not match our records.',
    );
    final AuthController controller =
        container.read(authControllerProvider.notifier);

    await controller.login(
      baseUrl: 'https://10.0.0.10',
      email: 'ops@ir4.local',
      password: 'bad',
    );

    final AuthState actualState = container.read(authControllerProvider);
    expect(actualState, isA<AuthUnauthenticated>());
    expect(
      (actualState as AuthUnauthenticated).message,
      'These credentials do not match our records.',
    );
  });

  test('bootstrap restores authenticated session', () async {
    final AuthController controller =
        container.read(authControllerProvider.notifier);

    await controller.bootstrap();

    final AuthState actualState = container.read(authControllerProvider);
    expect(actualState, isA<AuthAuthenticated>());
  });

  test('logout clears session state', () async {
    final AuthController controller =
        container.read(authControllerProvider.notifier);
    await controller.login(
      baseUrl: 'https://10.0.0.10',
      email: 'ops@ir4.local',
      password: 'secret',
    );

    await controller.logout();

    expect(fakeRepository.logoutCalled, isTrue);
    expect(container.read(authControllerProvider), isA<AuthUnauthenticated>());
  });
}
