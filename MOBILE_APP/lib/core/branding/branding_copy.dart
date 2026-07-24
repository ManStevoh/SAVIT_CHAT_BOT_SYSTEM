/// RelayIQ product branding — company is Essem Digital Innovation Limited.
class AppBrandingCopy {
  static const productName = 'RelayIQ';
  static const legalEntity = 'Essem Digital Innovation Limited';
  static const companyWebsite = 'https://essemdigital.com';
  static const tagline = 'Every Conversation. Smarter.';
  static const poweredBy = 'Powered by Essem Digital Innovation Limited';
  static const productOf =
      'RelayIQ is a product of Essem Digital Innovation Limited.';

  static String copyright([int? year]) {
    final y = year ?? DateTime.now().year;
    return '© $y Essem Digital Innovation Limited. RelayIQ is a product of Essem Digital Innovation Limited.';
  }
}
