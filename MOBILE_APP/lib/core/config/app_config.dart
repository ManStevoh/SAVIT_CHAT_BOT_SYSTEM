class AppConfig {
  const AppConfig({required this.apiBaseUrl});

  /// Laravel API root, e.g. `http://10.0.2.2:8080/api` (Android emulator)
  /// or `http://127.0.0.1:8080/api` (iOS simulator / desktop).
  final String apiBaseUrl;

  /// Web dashboard origin (API root without trailing `/api`).
  String get webOrigin => apiBaseUrl.replaceAll(RegExp(r'/api/?$'), '');

  String dashboardUrl(String path) {
    final clean = path.startsWith('/') ? path : '/$path';
    return '$webOrigin$clean';
  }

  factory AppConfig.fromEnvironment() {
    const raw = String.fromEnvironment(
      'API_BASE_URL',
      defaultValue: 'https://relayiq.app/api',
    );
    return AppConfig(apiBaseUrl: raw.replaceAll(RegExp(r'/$'), ''));
  }
}
