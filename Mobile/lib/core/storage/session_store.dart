import 'package:flutter_secure_storage/flutter_secure_storage.dart';

final class SessionStore {
  SessionStore({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  static const String _tokenKey = 'ir4.auth.token';
  static const String _baseUrlKey = 'ir4.auth.base_url';

  final FlutterSecureStorage _storage;

  Future<void> writeToken(String token) => _storage.write(key: _tokenKey, value: token);

  Future<String?> readToken() => _storage.read(key: _tokenKey);

  Future<void> writeBaseUrl(String baseUrl) =>
      _storage.write(key: _baseUrlKey, value: baseUrl);

  Future<String?> readBaseUrl() => _storage.read(key: _baseUrlKey);

  Future<void> clearSession() async {
    await _storage.delete(key: _tokenKey);
  }

  Future<void> clearAll() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _baseUrlKey);
  }
}
