/// Digits-only phone key, matching Laravel `ChatController::start` normalization.
String normalizePhoneDigits(String raw) => raw.replaceAll(RegExp(r'\D'), '');
