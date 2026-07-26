/// Lightweight display helpers for API date / datetime strings.
abstract final class DateDisplay {
  static String date(String? raw) {
    if (raw == null || raw.isEmpty) {
      return '—';
    }
    final DateTime? parsed = DateTime.tryParse(raw);
    if (parsed == null) {
      return raw.length >= 10 ? raw.substring(0, 10) : raw;
    }
    final DateTime local = parsed.toLocal();
    final String y = local.year.toString().padLeft(4, '0');
    final String m = local.month.toString().padLeft(2, '0');
    final String d = local.day.toString().padLeft(2, '0');
    return '$y-$m-$d';
  }

  static String dateTime(String? raw) {
    if (raw == null || raw.isEmpty) {
      return '—';
    }
    final DateTime? parsed = DateTime.tryParse(raw);
    if (parsed == null) {
      return raw;
    }
    final DateTime local = parsed.toLocal();
    final String y = local.year.toString().padLeft(4, '0');
    final String m = local.month.toString().padLeft(2, '0');
    final String d = local.day.toString().padLeft(2, '0');
    final String h = local.hour.toString().padLeft(2, '0');
    final String min = local.minute.toString().padLeft(2, '0');
    return '$y-$m-$d $h:$min';
  }
}
