import 'dart:io';

import 'package:dio/dio.dart';
import 'package:dio/io.dart';

import 'package:ir4_mobile/core/network/api_exception.dart';
import 'package:ir4_mobile/core/storage/session_store.dart';

typedef UnauthorizedCallback = void Function();

final class ApiClient {
  ApiClient({
    required SessionStore sessionStore,
    UnauthorizedCallback? onUnauthorized,
  })  : _sessionStore = sessionStore,
        _onUnauthorized = onUnauthorized,
        _dio = Dio(
          BaseOptions(
            connectTimeout: const Duration(seconds: 20),
            receiveTimeout: const Duration(seconds: 30),
            headers: <String, String>{
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
          ),
        ) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (RequestOptions options, RequestInterceptorHandler handler) async {
          final String? token = await _sessionStore.readToken();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
        onError: (DioException error, ErrorInterceptorHandler handler) async {
          if (error.response?.statusCode == 401) {
            await _sessionStore.clearSession();
            _onUnauthorized?.call();
          }
          handler.next(error);
        },
      ),
    );
    _configureBadCertificate();
  }

  final Dio _dio;
  final SessionStore _sessionStore;
  final UnauthorizedCallback? _onUnauthorized;

  Dio get dio => _dio;

  Future<void> setBaseUrl(String baseUrl) async {
    final String normalized = _normalizeBaseUrl(baseUrl);
    _dio.options.baseUrl = normalized;
    await _sessionStore.writeBaseUrl(normalized);
  }

  Future<void> restoreBaseUrl() async {
    final String? stored = await _sessionStore.readBaseUrl();
    if (stored != null && stored.isNotEmpty) {
      _dio.options.baseUrl = stored;
    }
  }

  Future<Map<String, dynamic>> getJson(String path) async {
    return _unwrap(await _request(() => _dio.get<dynamic>(path)));
  }

  Future<Map<String, dynamic>> postJson(
    String path, {
    Map<String, dynamic>? body,
  }) async {
    return _unwrap(
      await _request(() => _dio.post<dynamic>(path, data: body ?? <String, dynamic>{})),
    );
  }

  Future<Response<dynamic>> _request(
    Future<Response<dynamic>> Function() send,
  ) async {
    try {
      return await send();
    } on DioException catch (error) {
      throw ApiException.fromDio(error);
    }
  }

  Map<String, dynamic> _unwrap(Response<dynamic> response) {
    final Object? payload = response.data;
    if (payload is Map<String, dynamic>) {
      final Object? data = payload['data'];
      if (data is Map<String, dynamic>) {
        return data;
      }
      return payload;
    }
    throw const ApiException(
      code: 'INVALID_RESPONSE',
      message: 'Unexpected response from server.',
    );
  }

  void _configureBadCertificate() {
    _dio.httpClientAdapter = IOHttpClientAdapter(
      createHttpClient: () {
        final HttpClient client = HttpClient();
        client.badCertificateCallback =
            (X509Certificate cert, String host, int port) {
          final String base = _dio.options.baseUrl;
          if (base.isEmpty) {
            return false;
          }
          final Uri? uri = Uri.tryParse(base);
          if (uri == null || uri.host.isEmpty) {
            return false;
          }
          return host == uri.host;
        };
        return client;
      },
    );
  }

  String _normalizeBaseUrl(String input) {
    String value = input.trim();
    if (value.endsWith('/')) {
      value = value.substring(0, value.length - 1);
    }
    if (!value.startsWith('http://') && !value.startsWith('https://')) {
      value = 'https://$value';
    }
    return value;
  }
}
