class AppBranding {
  const AppBranding({
    required this.applicationName,
    this.appLogo,
    this.primaryColor,
    this.secondaryColor,
  });

  final String applicationName;
  final String? appLogo;
  final String? primaryColor;
  final String? secondaryColor;

  static const fallback = AppBranding(applicationName: 'Essem');

  factory AppBranding.fromJson(Map<String, dynamic> json) {
    return AppBranding(
      applicationName: (json['applicationName'] as String?)?.trim().isNotEmpty == true
          ? json['applicationName'] as String
          : 'Essem',
      appLogo: json['appLogo'] as String?,
      primaryColor: json['primaryColor'] as String?,
      secondaryColor: json['secondaryColor'] as String?,
    );
  }
}
