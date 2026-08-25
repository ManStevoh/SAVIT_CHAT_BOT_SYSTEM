import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../network/api_exception.dart';
import 'auth_user.dart';

class AuthController extends ChangeNotifier {
  AuthController({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  /// In-memory session for widget tests (avoids secure-storage plugins).
  AuthController.memory() : _storage = null;

  static const _tokenKey = 'essem_auth_token';
  static const _userKey = 'essem_auth_user';

  final FlutterSecureStorage? _storage;
  final Map<String, String> _memory = {};

  String? _token;
  AuthUser? _user;
  bool _ready = false;

  bool get isReady => _ready;
  bool get isAuthenticated =>
      _token != null && _token!.isNotEmpty && _user != null;
  String? get token => _token;
  AuthUser? get user => _user;

  Future<void> bootstrap() async {
    _token = await _read(_tokenKey);
    final rawUser = await _read(_userKey);
    if (rawUser != null && rawUser.isNotEmpty) {
      try {
        _user = AuthUser.fromJson(jsonDecode(rawUser) as Map<String, dynamic>);
      } catch (_) {
        _user = null;
      }
    }
    final hasToken = _token != null && _token!.isNotEmpty;
    if ((hasToken && _user == null) || (!hasToken && _user != null)) {
      await clearSession();
    }
    _ready = true;
    notifyListeners();
  }

  Future<void> setSession({required String token, AuthUser? user}) async {
    _token = token;
    _user = user;
    await _write(_tokenKey, token);
    if (user != null) {
      await _write(_userKey, jsonEncode(user.toJson()));
    } else {
      await _delete(_userKey);
    }
    notifyListeners();
  }

  Future<void> clearSession() async {
    _token = null;
    _user = null;
    await _delete(_tokenKey);
    await _delete(_userKey);
    notifyListeners();
  }

  Future<String?> _read(String key) async {
    final storage = _storage;
    if (storage == null) return _memory[key];
    return storage.read(key: key);
  }

  Future<void> _write(String key, String value) async {
    final storage = _storage;
    if (storage == null) {
      _memory[key] = value;
      return;
    }
    await storage.write(key: key, value: value);
  }

  Future<void> _delete(String key) async {
    final storage = _storage;
    if (storage == null) {
      _memory.remove(key);
      return;
    }
    await storage.delete(key: key);
  }
}

class AuthRepository {
  AuthRepository(this._dio, this._auth);

  final Dio _dio;
  final AuthController _auth;

  Future<AuthUser> login({required String email, required String password}) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        '/auth/login',
        data: {'email': email, 'password': password},
      );
      final data = response.data ?? {};
      final token = data['token'] as String?;
      final userJson = data['user'];
      if (token == null || token.isEmpty || userJson is! Map) {
        throw ApiException(data['message']?.toString() ?? 'Login failed.');
      }
      final user = AuthUser.fromJson(Map<String, dynamic>.from(userJson));
      await _auth.setSession(token: token, user: user);
      return user;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> forgotPassword({required String email}) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        '/auth/forgot-password',
        data: {'email': email},
      );
      final data = response.data ?? {};
      if (data['success'] != true) {
        throw ApiException(data['message']?.toString() ?? 'Request failed.');
      }
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> logout() async {
    try {
      await _dio.post('/auth/logout');
    } catch (_) {
      // Always clear local session.
    } finally {
      await _auth.clearSession();
    }
  }

  Future<AuthUser> updateProfile({
    required String name,
    required String email,
    String? phone,
  }) async {
    try {
      final response = await _dio.put<Map<String, dynamic>>(
        '/auth/profile',
        data: {
          'name': name,
          'email': email,
          'phone': phone?.trim().isEmpty == true ? null : phone?.trim(),
        },
      );
      final data = response.data ?? {};
      if (data['success'] != true) {
        throw ApiException(data['message']?.toString() ?? 'Profile update failed.');
      }
      final userJson = data['user'];
      if (userJson is! Map) {
        throw ApiException(data['message']?.toString() ?? 'Profile update failed.');
      }
      final user = AuthUser.fromJson(Map<String, dynamic>.from(userJson));
      final token = _auth.token;
      if (token == null || token.isEmpty) {
        throw ApiException('Session expired. Please sign in again.');
      }
      await _auth.setSession(token: token, user: user);
      return user;
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> updatePassword({
    required String currentPassword,
    required String password,
    required String confirmPassword,
  }) async {
    try {
      final response = await _dio.put<Map<String, dynamic>>(
        '/auth/password',
        data: {
          'currentPassword': currentPassword,
          'password': password,
          'confirmPassword': confirmPassword,
        },
      );
      final data = response.data ?? {};
      if (data['success'] != true) {
        throw ApiException(data['message']?.toString() ?? 'Password update failed.');
      }
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
