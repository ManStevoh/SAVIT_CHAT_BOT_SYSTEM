import 'package:url_launcher/url_launcher.dart';

/// Opens only http(s) URLs. Rejects javascript:, file:, and custom schemes.
Future<bool> openHttpsUrl(String? raw) async {
  final value = raw?.trim() ?? '';
  if (value.isEmpty) return false;
  final uri = Uri.tryParse(value);
  if (uri == null || !uri.hasScheme) return false;
  final scheme = uri.scheme.toLowerCase();
  if (scheme != 'https' && scheme != 'http') return false;
  return launchUrl(uri, mode: LaunchMode.externalApplication);
}
