import 'package:dio/dio.dart';

import '../auth/auth_controller.dart';
import '../config/app_config.dart';

class ApiClient {
  ApiClient({required AppConfig config, required AuthController auth})
      : _auth = auth,
        dio = Dio(
          BaseOptions(
            baseUrl: config.apiBaseUrl,
            connectTimeout: const Duration(seconds: 20),
            receiveTimeout: const Duration(seconds: 30),
            headers: const {
              'Accept': 'application/json',
            },
          ),
        ) {
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          final token = _auth.token;
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          // Let Dio set multipart boundary; force JSON only for Map/List bodies.
          if (options.data is! FormData &&
              options.data != null &&
              options.headers['Content-Type'] == null) {
            options.headers['Content-Type'] = 'application/json';
          }
          handler.next(options);
        },
        onError: (error, handler) async {
          if (error.response?.statusCode == 401) {
            await _auth.clearSession();
          }
          handler.next(error);
        },
      ),
    );
  }

  final AuthController _auth;
  final Dio dio;
}
