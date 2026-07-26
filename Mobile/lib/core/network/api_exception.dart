import 'package:dio/dio.dart';

final class ApiException implements Exception {
  const ApiException({
    required this.code,
    required this.message,
    this.details,
    this.statusCode,
  });

  final String code;
  final String message;
  final Map<String, dynamic>? details;
  final int? statusCode;

  factory ApiException.fromDio(DioException error) {
    final Response<dynamic>? response = error.response;
    final Object? data = response?.data;
    if (data is Map<String, dynamic>) {
      final Object? err = data['error'];
      if (err is Map<String, dynamic>) {
        return ApiException(
          code: (err['code'] as String?) ?? 'HTTP_ERROR',
          message: (err['message'] as String?) ?? 'Request failed.',
          details: err['details'] is Map<String, dynamic>
              ? err['details'] as Map<String, dynamic>
              : null,
          statusCode: response?.statusCode,
        );
      }
    }
    if (error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout ||
        error.type == DioExceptionType.sendTimeout) {
      return const ApiException(
        code: 'TIMEOUT',
        message: 'Server did not respond in time.',
      );
    }
    if (error.type == DioExceptionType.connectionError) {
      return const ApiException(
        code: 'CONNECTION',
        message: 'Could not reach the IR4 server. Check the base URL and LAN.',
      );
    }
    return ApiException(
      code: 'HTTP_ERROR',
      message: error.message ?? 'Request failed.',
      statusCode: response?.statusCode,
    );
  }

  @override
  String toString() => message;
}
