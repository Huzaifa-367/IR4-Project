import 'package:ir4_mobile/core/network/api_client.dart';
import 'package:ir4_mobile/core/storage/session_store.dart';
import 'package:ir4_mobile/features/auth/domain/auth_user.dart';

abstract interface class AuthRepository {
  Future<LoginResult> login({
    required String baseUrl,
    required String email,
    required String password,
    String deviceName = 'IR4 Android',
  });

  Future<AuthUser?> restoreSession();

  Future<void> logout();

  Future<String?> readBaseUrl();
}

final class AuthRepositoryImpl implements AuthRepository {
  AuthRepositoryImpl({
    required ApiClient apiClient,
    required SessionStore sessionStore,
  })  : _apiClient = apiClient,
        _sessionStore = sessionStore;

  final ApiClient _apiClient;
  final SessionStore _sessionStore;

  @override
  Future<LoginResult> login({
    required String baseUrl,
    required String email,
    required String password,
    String deviceName = 'IR4 Android',
  }) async {
    await _apiClient.setBaseUrl(baseUrl);
    final Map<String, dynamic> data = await _apiClient.postJson(
      '/api/mobile/login',
      body: <String, dynamic>{
        'email': email.trim(),
        'password': password,
        'device_name': deviceName,
      },
    );
    final String token = data['token'] as String;
    await _sessionStore.writeToken(token);
    final AuthUser user = AuthUser.fromJson(
      data['user'] as Map<String, dynamic>,
      data['permissions'] as List<dynamic>? ?? <dynamic>[],
    );
    return LoginResult(token: token, user: user);
  }

  @override
  Future<AuthUser?> restoreSession() async {
    await _apiClient.restoreBaseUrl();
    final String? token = await _sessionStore.readToken();
    final String? baseUrl = await _sessionStore.readBaseUrl();
    if (token == null || token.isEmpty || baseUrl == null || baseUrl.isEmpty) {
      return null;
    }
    final Map<String, dynamic> data = await _apiClient.getJson('/api/mobile/me');
    return AuthUser.fromJson(
      data['user'] as Map<String, dynamic>,
      data['permissions'] as List<dynamic>? ?? <dynamic>[],
    );
  }

  @override
  Future<void> logout() async {
    try {
      await _apiClient.postJson('/api/mobile/logout');
    } catch (_) {
      // Always clear local session even if the server call fails.
    }
    await _sessionStore.clearSession();
  }

  @override
  Future<String?> readBaseUrl() => _sessionStore.readBaseUrl();
}
