import 'package:dio/dio.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  factory ApiException.fromDio(DioException error) {
    final data = error.response?.data;
    String message = 'Something went wrong. Please try again.';

    if (data is Map) {
      if (data['message'] is String && (data['message'] as String).isNotEmpty) {
        message = data['message'] as String;
      } else if (data['errors'] is Map) {
        final errors = data['errors'] as Map;
        for (final value in errors.values) {
          if (value is List && value.isNotEmpty && value.first is String) {
            message = value.first as String;
            break;
          }
          if (value is String) {
            message = value;
            break;
          }
        }
      }
    } else if (error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout ||
        error.type == DioExceptionType.connectionError) {
      message = 'Cannot reach the server. Check API_BASE_URL and that Laravel is running.';
    }

    return ApiException(message, statusCode: error.response?.statusCode);
  }

  @override
  String toString() => message;
}
