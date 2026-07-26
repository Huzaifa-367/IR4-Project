/// Extract a permanent equipment qr_token from a raw scan value.
/// Accepts a bare UUID or a public URL ending in `/e/{token}`.
String? parseEquipmentQrToken(String raw) {
  final String trimmed = raw.trim();
  if (trimmed.isEmpty) {
    return null;
  }
  final RegExp uuidPattern = RegExp(
    r'^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
    caseSensitive: false,
  );
  if (uuidPattern.hasMatch(trimmed)) {
    return trimmed.toLowerCase();
  }
  final Uri? uri = Uri.tryParse(trimmed);
  if (uri != null && uri.hasAbsolutePath) {
    final List<String> parts =
        uri.pathSegments.where((String part) => part.isNotEmpty).toList();
    final int eIndex = parts.indexOf('e');
    if (eIndex >= 0 && eIndex + 1 < parts.length) {
      return parseEquipmentQrToken(parts[eIndex + 1]);
    }
  }
  final List<String> slashParts =
      trimmed.split('/').where((String part) => part.isNotEmpty).toList();
  if (slashParts.isNotEmpty) {
    final Match? nested = uuidPattern.firstMatch(slashParts.last);
    if (nested != null) {
      return nested.group(0)!.toLowerCase();
    }
  }
  return null;
}
