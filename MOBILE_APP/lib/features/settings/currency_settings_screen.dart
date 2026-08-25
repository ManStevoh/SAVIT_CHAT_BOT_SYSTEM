import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/money/money_formatter.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';
import 'company_settings_controller.dart';

class CurrencySettingsScreen extends StatefulWidget {
  const CurrencySettingsScreen({super.key});

  @override
  State<CurrencySettingsScreen> createState() => _CurrencySettingsScreenState();
}

class _CurrencySettingsScreenState extends State<CurrencySettingsScreen> {
  static const _currencies = [
    'USD',
    'EUR',
    'GBP',
    'KES',
    'UGX',
    'TZS',
    'RWF',
    'NGN',
    'GHS',
    'ZAR',
    'AED',
    'INR',
  ];

  static const _thousandsOptions = [
    (',', 'Comma (1,000)'),
    ('.', 'Dot (1.000)'),
    (' ', 'Space (1 000)'),
    ("'", "Apostrophe (1'000)"),
  ];

  final _symbolCtrl = TextEditingController();
  String _currency = 'USD';
  String _thousands = ',';
  String _decimal = '.';
  bool _loading = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _bootstrap());
  }

  @override
  void dispose() {
    _symbolCtrl.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    final controller = context.read<CompanySettingsController>();
    await controller.load(force: true);
    if (!mounted) return;
    final s = controller.settings;
    setState(() {
      _currency = s.displayCurrency;
      _symbolCtrl.text = s.currencySymbol ?? '';
      _thousands = s.thousandsSeparator;
      _decimal = s.decimalSeparator;
      _loading = false;
    });
  }

  String get _preview {
    return MoneyFormatter(
      currencyCode: _currency,
      symbol: _symbolCtrl.text.trim().isEmpty ? null : _symbolCtrl.text.trim(),
      thousandsSeparator: _thousands,
      decimalSeparator: _decimal,
    ).format(1234567.89);
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await context.read<CompanySettingsController>().updateCurrencyDisplay(
            displayCurrency: _currency,
            currencySymbol: _symbolCtrl.text.trim(),
            thousandsSeparator: _thousands,
            decimalSeparator: _decimal,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Currency display saved')),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: Text(
          'Currency',
          style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
              children: [
                AppSurface(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Catalog currency',
                        style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Used for prices, orders, and WhatsApp amounts.',
                        style: GoogleFonts.manrope(
                          color: AppColors.textMuted,
                          fontSize: 13,
                        ),
                      ),
                      const SizedBox(height: 14),
                      DropdownButtonFormField<String>(
                        value: _currencies.contains(_currency)
                            ? _currency
                            : 'USD',
                        decoration: const InputDecoration(
                          labelText: 'Currency code',
                          border: OutlineInputBorder(),
                        ),
                        items: [
                          if (!_currencies.contains(_currency))
                            DropdownMenuItem(
                              value: _currency,
                              child: Text(_currency),
                            ),
                          ..._currencies.map(
                            (c) => DropdownMenuItem(value: c, child: Text(c)),
                          ),
                        ],
                        onChanged: (v) {
                          if (v == null) return;
                          setState(() => _currency = v);
                        },
                      ),
                      const SizedBox(height: 14),
                      TextField(
                        controller: _symbolCtrl,
                        maxLength: 16,
                        decoration: const InputDecoration(
                          labelText: 'Currency symbol',
                          hintText: 'e.g. KSh, €, \$',
                          border: OutlineInputBorder(),
                          counterText: '',
                        ),
                        onChanged: (_) => setState(() {}),
                      ),
                      const SizedBox(height: 14),
                      DropdownButtonFormField<String>(
                        value: _thousands,
                        decoration: const InputDecoration(
                          labelText: 'Thousands separator',
                          border: OutlineInputBorder(),
                        ),
                        items: _thousandsOptions
                            .map(
                              (o) => DropdownMenuItem(
                                value: o.$1,
                                child: Text(o.$2),
                              ),
                            )
                            .toList(),
                        onChanged: (v) {
                          if (v == null) return;
                          setState(() {
                            _thousands = v;
                            _decimal =
                                MoneyFormatter.pairedDecimalForThousands(v);
                          });
                        },
                      ),
                      const SizedBox(height: 14),
                      DropdownButtonFormField<String>(
                        value: _decimal == ',' || _decimal == '.'
                            ? _decimal
                            : '.',
                        decoration: const InputDecoration(
                          labelText: 'Decimal separator',
                          border: OutlineInputBorder(),
                        ),
                        items: const [
                          DropdownMenuItem(value: '.', child: Text('Dot (.)')),
                          DropdownMenuItem(value: ',', child: Text('Comma (,)')),
                        ],
                        onChanged: (v) {
                          if (v == null) return;
                          setState(() => _decimal = v);
                        },
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                AppSurface(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Preview',
                        style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        _preview,
                        style: GoogleFonts.manrope(
                          fontSize: 22,
                          fontWeight: FontWeight.w800,
                          color: AppColors.primary,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: _saving ? null : _save,
                  child: _saving
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Save currency display'),
                ),
              ],
            ),
    );
  }
}
