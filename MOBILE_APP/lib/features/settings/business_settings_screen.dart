import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';
import '../companion/companion_models.dart';
import '../companion/companion_repository.dart';
import 'company_settings_controller.dart';

class BusinessSettingsScreen extends StatefulWidget {
  const BusinessSettingsScreen({super.key});

  @override
  State<BusinessSettingsScreen> createState() => _BusinessSettingsScreenState();
}

class _BusinessSettingsScreenState extends State<BusinessSettingsScreen> {
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _timezone = TextEditingController();
  String _mode = 'hybrid';
  bool _bookings = true;
  bool _dineIn = false;
  bool _loading = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _timezone.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final json = await context.read<CompanionRepository>().companySettings();
      final profile = BusinessProfile.fromJson(json);
      _name.text = profile.companyName;
      _phone.text = profile.phone;
      _timezone.text = profile.timezone;
      _mode = profile.businessMode;
      _bookings = profile.enableBookings;
      _dineIn = profile.enableDineIn;
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    final repo = context.read<CompanionRepository>();
    final settings = context.read<CompanySettingsController>();
    try {
      await repo.updateSettings({
        'companyName': _name.text.trim(),
        'phone': _phone.text.trim(),
        'timezone': _timezone.text.trim(),
        'businessMode': _mode,
        'enableBookings': _bookings,
        'enableDineIn': _dineIn,
      });
      if (!mounted) return;
      await settings.load(force: true);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Business profile saved')));
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(title: Text('Business', style: GoogleFonts.manrope(fontWeight: FontWeight.w800))),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
              children: [
                AppSurface(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    children: [
                      TextField(controller: _name, decoration: const InputDecoration(labelText: 'Company name')),
                      TextField(controller: _phone, decoration: const InputDecoration(labelText: 'Business phone'), keyboardType: TextInputType.phone),
                      TextField(controller: _timezone, decoration: const InputDecoration(labelText: 'Timezone')),
                      DropdownButtonFormField<String>(
                        value: _mode,
                        items: const [
                          DropdownMenuItem(value: 'retail', child: Text('Retail')),
                          DropdownMenuItem(value: 'services', child: Text('Services')),
                          DropdownMenuItem(value: 'restaurant', child: Text('Restaurant')),
                          DropdownMenuItem(value: 'hybrid', child: Text('Hybrid')),
                        ],
                        onChanged: (v) => setState(() => _mode = v ?? 'hybrid'),
                        decoration: const InputDecoration(labelText: 'Business mode'),
                      ),
                      SwitchListTile(value: _bookings, onChanged: (v) => setState(() => _bookings = v), title: const Text('Enable bookings')),
                      SwitchListTile(value: _dineIn, onChanged: (v) => setState(() => _dineIn = v), title: const Text('Enable dine-in')),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                FilledButton(onPressed: _saving ? null : _save, child: Text(_saving ? 'Saving…' : 'Save business profile')),
              ],
            ),
    );
  }
}
