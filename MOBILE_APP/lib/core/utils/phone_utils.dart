/// Digits-only phone, matching Laravel `ChatController::start` normalization.
String normalizePhoneDigits(String raw) => raw.replaceAll(RegExp(r'\D'), '');

/// Canonical merge key so local `07…` and international `2547…` collide.
///
/// Defaults to Kenya (`254`) which matches the product's primary market and
/// WhatsApp Cloud API E.164 expectations for local numbers.
String phoneMergeKey(String raw, {String defaultCountry = '254'}) {
  var digits = normalizePhoneDigits(raw);
  if (digits.isEmpty) return '';

  if (digits.startsWith('00')) {
    digits = digits.substring(2);
  }

  // 07XXXXXXXX / 01XXXXXXXX → 2547XXXXXXXX
  if (digits.length == 10 && digits.startsWith('0')) {
    return '$defaultCountry${digits.substring(1)}';
  }

  // Bare mobile without leading 0: 7XXXXXXXX
  if (digits.length == 9 && digits.startsWith('7')) {
    return '$defaultCountry$digits';
  }

  return digits;
}
