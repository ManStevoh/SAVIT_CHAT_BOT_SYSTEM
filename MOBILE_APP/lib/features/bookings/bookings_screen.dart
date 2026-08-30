import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';
import '../companion/companion_repository.dart';

class BookingsScreen extends StatefulWidget {
  const BookingsScreen({super.key});

  @override
  State<BookingsScreen> createState() => _BookingsScreenState();
}

class _BookingsScreenState extends State<BookingsScreen> {
  bool _loading = true;
  String? _error;
  String? _blocked;
  Map<String, dynamic>? _settings;
  List<Map<String, dynamic>> _bookings = const [];
  String? _publicUrl;
  String? _calendarUrl;
  int _used = 0;
  int? _max;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
      _blocked = null;
    });
    final dio = context.read<ApiClient>().dio;
    try {
      final settingsRes = await dio.get('/company/bookings/settings');
      final data = Map<String, dynamic>.from(settingsRes.data as Map);
      final listRes = await dio.get(
        '/company/bookings',
        queryParameters: {'upcoming': 1},
      );
      final listData = Map<String, dynamic>.from(listRes.data as Map);
      final bookingsRaw = listData['bookings'];
      setState(() {
        _settings = Map<String, dynamic>.from(data['settings'] as Map);
        _publicUrl = data['publicBookingUrl']?.toString();
        _calendarUrl = data['calendarFeedUrl']?.toString();
        _used = (data['bookingsThisMonth'] as num?)?.toInt() ?? 0;
        _max = (data['maxBookingsPerMonth'] as num?)?.toInt();
        _bookings = bookingsRaw is List
            ? bookingsRaw
                .whereType<Map>()
                .map((e) => Map<String, dynamic>.from(e))
                .toList()
            : const [];
      });
    } on DioException catch (e) {
      final api = ApiException.fromDio(e);
      final code =
          e.response?.data is Map ? (e.response!.data as Map)['code'] : null;
      if (code == 'bookings_required' || e.response?.statusCode == 403) {
        setState(() => _blocked = api.message);
      } else {
        setState(() => _error = api.message);
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _copy(String? value) async {
    if (value == null || value.isEmpty) return;
    await Clipboard.setData(ClipboardData(text: value));
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Copied')),
    );
  }

  Future<void> _editSettings() async {
    if (_settings == null) return;
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _BookingSettingsSheet(settings: _settings!),
    );
    if (saved == true) await _load();
  }

  Future<void> _setStatus(Map<String, dynamic> booking, String status) async {
    final id = booking['id']?.toString();
    if (id == null || id.isEmpty) return;
    try {
      await context.read<CompanionRepository>().updateBookingStatus(id, status);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Booking $status')),
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: Text(
          'Bookings',
          style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
        ),
        actions: [
          IconButton(
            tooltip: 'Booking settings',
            onPressed: _loading || _settings == null ? null : _editSettings,
            icon: const Icon(Icons.tune),
          ),
          IconButton(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _blocked != null
              ? Padding(
                  padding: const EdgeInsets.all(16),
                  child: AppSurface(
                    padding: const EdgeInsets.all(20),
                    child: Text(
                      _blocked!,
                      style: GoogleFonts.manrope(color: AppColors.textMuted),
                    ),
                  ),
                )
              : ListView(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
                  children: [
                    if (_error != null)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Text(
                          _error!,
                          style: const TextStyle(color: Colors.redAccent),
                        ),
                      ),
                    AppSurface(
                      padding: const EdgeInsets.all(18),
                      color: AppColors.primarySoft,
                      elevation: false,
                      child: Row(
                        children: [
                          const AppIconChip(
                            icon: Icons.event_available,
                            color: AppColors.accentTeal,
                            size: 48,
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'This month',
                                  style: GoogleFonts.manrope(
                                    color: AppColors.textMuted,
                                    fontWeight: FontWeight.w600,
                                    fontSize: 13,
                                  ),
                                ),
                                Text(
                                  '$_used${_max == null ? ' · unlimited' : ' / $_max'}',
                                  style: GoogleFonts.manrope(
                                    fontWeight: FontWeight.w800,
                                    fontSize: 22,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    AppSurface(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 4,
                      ),
                      child: Column(
                        children: [
                          ListTile(
                            title: Text(
                              'Public booking page',
                              style: GoogleFonts.manrope(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            subtitle: Text(
                              _publicUrl ?? '',
                              style: const TextStyle(fontSize: 12),
                            ),
                            trailing: IconButton(
                              icon: const Icon(Icons.copy),
                              onPressed: () => _copy(_publicUrl),
                            ),
                          ),
                          ListTile(
                            title: Text(
                              'Calendar feed (ICS)',
                              style: GoogleFonts.manrope(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            subtitle: Text(
                              _calendarUrl ?? '',
                              style: const TextStyle(fontSize: 12),
                            ),
                            trailing: IconButton(
                              icon: const Icon(Icons.copy),
                              onPressed: () => _copy(_calendarUrl),
                            ),
                          ),
                          if (_settings != null)
                            ListTile(
                              title: Text(
                                'Slot settings',
                                style: GoogleFonts.manrope(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              subtitle: Text(
                                '${_settings!['isEnabled'] == false ? 'Disabled' : 'Enabled'} · '
                                '${_settings!['timezone']} · '
                                '${_settings!['defaultDurationMinutes']} min · '
                                '${_settings!['bufferMinutes'] ?? 0} min buffer',
                                style: GoogleFonts.manrope(
                                  color: AppColors.textMuted,
                                  fontSize: 12,
                                ),
                              ),
                              trailing: TextButton(
                                onPressed: _editSettings,
                                child: const Text('Edit'),
                              ),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 22),
                    Text(
                      'Upcoming',
                      style: GoogleFonts.manrope(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 10),
                    if (_bookings.isEmpty)
                      AppSurface(
                        padding: const EdgeInsets.all(20),
                        child: Text(
                          'No upcoming bookings.',
                          style: GoogleFonts.manrope(
                            color: AppColors.textMuted,
                          ),
                        ),
                      )
                    else
                      ..._bookings.map((b) {
                        final starts = b['startsAt']?.toString() ?? '';
                        final title = b['title']?.toString() ?? 'Meeting';
                        final customer = b['customerName']?.toString() ?? '';
                        final status = b['status']?.toString() ?? '';
                        final google = b['googleCalendarUrl']?.toString();
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: AppSurface(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 6,
                              vertical: 4,
                            ),
                            child: ListTile(
                              leading: const AppIconChip(
                                icon: Icons.event,
                                color: AppColors.accentTeal,
                                size: 42,
                              ),
                              title: Text(
                                title,
                                style: GoogleFonts.manrope(
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              subtitle: Text(
                                '$customer\n$starts\n$status',
                                style: GoogleFonts.manrope(
                                  color: AppColors.textMuted,
                                  fontSize: 13,
                                ),
                              ),
                              isThreeLine: true,
                              trailing: PopupMenuButton<String>(
                                onSelected: (value) {
                                  if (value == 'copy' && google != null) {
                                    _copy(google);
                                    return;
                                  }
                                  _setStatus(b, value);
                                },
                                itemBuilder: (context) => [
                                  if (status != 'confirmed')
                                    const PopupMenuItem(
                                      value: 'confirmed',
                                      child: Text('Confirm'),
                                    ),
                                  if (status != 'completed')
                                    const PopupMenuItem(
                                      value: 'completed',
                                      child: Text('Complete'),
                                    ),
                                  if (status != 'cancelled')
                                    const PopupMenuItem(
                                      value: 'cancelled',
                                      child: Text('Cancel'),
                                    ),
                                  if (google != null)
                                    const PopupMenuItem(
                                      value: 'copy',
                                      child: Text('Copy calendar link'),
                                    ),
                                ],
                              ),
                            ),
                          ),
                        );
                      }),
                  ],
                ),
    );
  }
}

class _BookingSettingsSheet extends StatefulWidget {
  const _BookingSettingsSheet({required this.settings});

  final Map<String, dynamic> settings;

  @override
  State<_BookingSettingsSheet> createState() => _BookingSettingsSheetState();
}

class _BookingSettingsSheetState extends State<_BookingSettingsSheet> {
  late final TextEditingController _timezone;
  late final TextEditingController _duration;
  late final TextEditingController _buffer;
  late bool _enabled;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _timezone = TextEditingController(
      text: widget.settings['timezone']?.toString() ?? 'Africa/Nairobi',
    );
    _duration = TextEditingController(
      text: '${widget.settings['defaultDurationMinutes'] ?? 30}',
    );
    _buffer = TextEditingController(
      text: '${widget.settings['bufferMinutes'] ?? 0}',
    );
    _enabled = widget.settings['isEnabled'] != false;
  }

  @override
  void dispose() {
    _timezone.dispose();
    _duration.dispose();
    _buffer.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final duration = int.tryParse(_duration.text.trim());
    final buffer = int.tryParse(_buffer.text.trim());
    if (duration == null || duration < 5 || duration > 480) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Duration must be 5–480 minutes.')),
      );
      return;
    }
    if (buffer == null || buffer < 0 || buffer > 240) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Buffer must be 0–240 minutes.')),
      );
      return;
    }
    setState(() => _saving = true);
    try {
      await context.read<CompanionRepository>().updateBookingSettings({
        'timezone': _timezone.text.trim(),
        'defaultDurationMinutes': duration,
        'bufferMinutes': buffer,
        'isEnabled': _enabled,
      });
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(
        16,
        16,
        16,
        16 + MediaQuery.of(context).viewInsets.bottom,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            'Booking settings',
            style: GoogleFonts.manrope(fontWeight: FontWeight.w800, fontSize: 18),
          ),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            value: _enabled,
            onChanged: (v) => setState(() => _enabled = v),
            title: const Text('Accept bookings'),
          ),
          TextField(
            controller: _timezone,
            decoration: const InputDecoration(labelText: 'Timezone'),
          ),
          TextField(
            controller: _duration,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Slot length (minutes)'),
          ),
          TextField(
            controller: _buffer,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Buffer (minutes)'),
          ),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: _saving ? null : _save,
            child: Text(_saving ? 'Saving…' : 'Save settings'),
          ),
        ],
      ),
    );
  }
}
