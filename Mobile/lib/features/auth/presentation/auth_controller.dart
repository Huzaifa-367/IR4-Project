import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:ir4_mobile/core/di/injector.dart';
import 'package:ir4_mobile/features/auth/data/auth_repository.dart';
import 'package:ir4_mobile/features/auth/domain/auth_user.dart';
import 'package:ir4_mobile/features/auth/presentation/auth_state.dart';
import 'package:ir4_mobile/core/network/api_exception.dart';

final authControllerProvider =
    NotifierProvider<AuthController, AuthState>(AuthController.new);

final class AuthController extends Notifier<AuthState> {
  AuthRepository get _repository => getIt<AuthRepository>();

  @override
  AuthState build() => const AuthState.initial();

  Future<void> bootstrap() async {
    state = const AuthState.loading();
    try {
      final AuthUser? user = await _repository.restoreSession();
      if (user == null) {
        state = const AuthState.unauthenticated();
        return;
      }
      state = AuthState.authenticated(user);
    } on ApiException catch (error) {
      await _repository.logout();
      state = AuthState.unauthenticated(message: error.message);
    } catch (_) {
      state = const AuthState.unauthenticated();
    }
  }

  Future<void> login({
    required String baseUrl,
    required String email,
    required String password,
  }) async {
    state = const AuthState.loading();
    try {
      final LoginResult result = await _repository.login(
        baseUrl: baseUrl,
        email: email,
        password: password,
      );
      state = AuthState.authenticated(result.user);
    } on ApiException catch (error) {
      state = AuthState.unauthenticated(message: error.message);
    } catch (_) {
      state = const AuthState.unauthenticated(
        message: 'Login failed. Check credentials and server URL.',
      );
    }
  }

  Future<void> logout() async {
    await _repository.logout();
    state = const AuthState.unauthenticated();
  }

  Future<String?> readStoredBaseUrl() => _repository.readBaseUrl();

  void markUnauthenticated([String? message]) {
    state = AuthState.unauthenticated(message: message);
  }
}
