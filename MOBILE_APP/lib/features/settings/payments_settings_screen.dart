import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';
import '../companion/companion_models.dart';
import '../companion/companion_repository.dart';
import 'company_settings_controller.dart';

class PaymentsSettingsScreen extends StatefulWidget {
  const PaymentsSettingsScreen({super.key});

  @override
  State<PaymentsSettingsScreen> createState() => _PaymentsSettingsScreenState();
}

class _PaymentsSettingsScreenState extends State<PaymentsSettingsScreen> {
  PaymentCollectionSettings? _settings;
  bool _loading = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final json = await context.read<CompanionRepository>().companySettings();
      setState(() => _settings = PaymentCollectionSettings.fromJson(json));
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
    final s = _settings;
    if (s == null) return;
    setState(() => _saving = true);
    final repo = context.read<CompanionRepository>();
    final settings = context.read<CompanySettingsController>();
    try {
      await repo.updateSettings({
        'ordersCollectPaymentEnabled': s.collectEnabled,
        'ordersAcceptMpesa': s.acceptMpesa,
        'ordersAcceptPaystack': s.acceptPaystack,
        'ordersAcceptStripe': s.acceptStripe,
        'ordersAcceptCod': s.acceptCod,
        'ordersAcceptBankTransfer': s.acceptBank,
      });
      if (!mounted) return;
      await settings.load(force: true);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Payment methods saved')));
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = _settings;
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(title: Text('Payments', style: GoogleFonts.manrope(fontWeight: FontWeight.w800))),
      body: _loading || s == null
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
              children: [
                AppSurface(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: Column(
                    children: [
                      SwitchListTile(
                        value: s.collectEnabled,
                        onChanged: (v) => setState(() => _settings = PaymentCollectionSettings(
                              collectEnabled: v,
                              acceptMpesa: s.acceptMpesa,
                              acceptPaystack: s.acceptPaystack,
                              acceptStripe: s.acceptStripe,
                              acceptCod: s.acceptCod,
                              acceptBank: s.acceptBank,
                            )),
                        title: const Text('Collect payment on orders'),
                      ),
                      SwitchListTile(
                        value: s.acceptMpesa,
                        onChanged: (v) => setState(() => _settings = PaymentCollectionSettings(
                              collectEnabled: s.collectEnabled,
                              acceptMpesa: v,
                              acceptPaystack: s.acceptPaystack,
                              acceptStripe: s.acceptStripe,
                              acceptCod: s.acceptCod,
                              acceptBank: s.acceptBank,
                            )),
                        title: const Text('M-Pesa'),
                      ),
                      SwitchListTile(
                        value: s.acceptPaystack,
                        onChanged: (v) => setState(() => _settings = PaymentCollectionSettings(
                              collectEnabled: s.collectEnabled,
                              acceptMpesa: s.acceptMpesa,
                              acceptPaystack: v,
                              acceptStripe: s.acceptStripe,
                              acceptCod: s.acceptCod,
                              acceptBank: s.acceptBank,
                            )),
                        title: const Text('Paystack'),
                      ),
                      SwitchListTile(
                        value: s.acceptStripe,
                        onChanged: (v) => setState(() => _settings = PaymentCollectionSettings(
                              collectEnabled: s.collectEnabled,
                              acceptMpesa: s.acceptMpesa,
                              acceptPaystack: s.acceptPaystack,
                              acceptStripe: v,
                              acceptCod: s.acceptCod,
                              acceptBank: s.acceptBank,
                            )),
                        title: const Text('Stripe'),
                      ),
                      SwitchListTile(
                        value: s.acceptCod,
                        onChanged: (v) => setState(() => _settings = PaymentCollectionSettings(
                              collectEnabled: s.collectEnabled,
                              acceptMpesa: s.acceptMpesa,
                              acceptPaystack: s.acceptPaystack,
                              acceptStripe: s.acceptStripe,
                              acceptCod: v,
                              acceptBank: s.acceptBank,
                            )),
                        title: const Text('Cash on delivery'),
                      ),
                      SwitchListTile(
                        value: s.acceptBank,
                        onChanged: (v) => setState(() => _settings = PaymentCollectionSettings(
                              collectEnabled: s.collectEnabled,
                              acceptMpesa: s.acceptMpesa,
                              acceptPaystack: s.acceptPaystack,
                              acceptStripe: s.acceptStripe,
                              acceptCod: s.acceptCod,
                              acceptBank: v,
                            )),
                        title: const Text('Bank transfer'),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                FilledButton(onPressed: _saving ? null : _save, child: Text(_saving ? 'Saving…' : 'Save payment methods')),
              ],
            ),
    );
  }
}
