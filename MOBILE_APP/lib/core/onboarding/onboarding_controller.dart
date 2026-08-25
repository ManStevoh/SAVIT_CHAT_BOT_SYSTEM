import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Tracks first-run onboarding completion.
class OnboardingController extends ChangeNotifier {
  OnboardingController({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  /// In-memory store for widget tests.
  OnboardingController.memory({bool completed = false})
      : _storage = null,
        _completed = completed,
        _ready = true;

  static const _key = 'essem_onboarding_done';

  final FlutterSecureStorage? _storage;
  final Map<String, String> _memory = {};

  bool _ready = false;
  bool _completed = false;

  bool get isReady => _ready;
  bool get hasCompleted => _completed;

  Future<void> bootstrap() async {
    final raw = await _read(_key);
    _completed = raw == '1';
    _ready = true;
    notifyListeners();
  }

  Future<void> complete() async {
    _completed = true;
    await _write(_key, '1');
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
}
