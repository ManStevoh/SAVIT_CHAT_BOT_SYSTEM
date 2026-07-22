class AppConfig {
  const AppConfig({required this.apiBaseUrl});

  /// Laravel API root, e.g. `http://10.0.2.2:8080/api` (Android emulator)
  /// or `http://127.0.0.1:8080/api` (iOS simulator / desktop).
  final String apiBaseUrl;

  factory AppConfig.fromEnvironment() {
    const raw = String.fromEnvironment(
      'API_BASE_URL',
      defaultValue: 'http://10.0.2.2:8080/api',
    );
    return AppConfig(apiBaseUrl: raw.replaceAll(RegExp(r'/$'), ''));
  }
}
