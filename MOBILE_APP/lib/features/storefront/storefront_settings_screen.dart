import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';
import '../settings/company_settings_controller.dart';

class StorefrontSettingsScreen extends StatefulWidget {
  const StorefrontSettingsScreen({super.key});

  @override
  State<StorefrontSettingsScreen> createState() =>
      _StorefrontSettingsScreenState();
}

class _StorefrontSettingsScreenState extends State<StorefrontSettingsScreen> {
  final _slugCtrl = TextEditingController();
  final _deliveryFeeCtrl = TextEditingController();
  final _freeDeliveryCtrl = TextEditingController();

  bool _storefrontEnabled = false;
  bool _linkInBioEnabled = false;
  bool _ordersAcceptCod = false;
  bool _deliveryFeesEnabled = false;
  bool _dineInEnabled = false;
  String? _storefrontUrl;

  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _bootstrap());
  }

  @override
  void dispose() {
    _slugCtrl.dispose();
    _deliveryFeeCtrl.dispose();
    _freeDeliveryCtrl.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    final controller = context.read<CompanySettingsController>();
    await controller.load(force: true);
    if (!mounted) return;
    final s = controller.settings;
    setState(() {
      _slugCtrl.text = s.storeSlug ?? '';
      _storefrontEnabled = s.storefrontEnabled;
      _linkInBioEnabled = s.linkInBioEnabled;
      _ordersAcceptCod = s.ordersAcceptCod;
      _deliveryFeesEnabled = s.deliveryFeesEnabled;
      _deliveryFeeCtrl.text =
          s.defaultDeliveryFee > 0 ? s.defaultDeliveryFee.toStringAsFixed(2) : '';
      _freeDeliveryCtrl.text = s.freeDeliveryAbove != null
          ? s.freeDeliveryAbove!.toStringAsFixed(2)
          : '';
      _dineInEnabled = s.dineInEnabled;
      _storefrontUrl = s.storefrontUrl;
      _loading = false;
    });
  }

  Future<void> _copyUrl() async {
    final url = _storefrontUrl;
    if (url == null || url.isEmpty) return;
    await Clipboard.setData(ClipboardData(text: url));
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Store link copied to clipboard')),
    );
  }

  Future<void> _save() async {
    final slug = _slugCtrl.text.trim().toLowerCase();
    if (_storefrontEnabled && slug.isEmpty) {
      setState(() =>
          _error = 'Add a store link before turning your storefront on.');
      return;
    }
    if (slug.isNotEmpty && !RegExp(r'^[a-z0-9-]+$').hasMatch(slug)) {
      setState(() => _error =
          'Store link can only contain lowercase letters, numbers, and hyphens.');
      return;
    }

    final deliveryFeeRaw = _deliveryFeeCtrl.text.trim();
    final deliveryFee =
        deliveryFeeRaw.isEmpty ? 0.0 : double.tryParse(deliveryFeeRaw);
    if (deliveryFeeRaw.isNotEmpty && deliveryFee == null) {
      setState(() => _error = 'Enter a valid delivery fee.');
      return;
    }
    final freeDeliveryRaw = _freeDeliveryCtrl.text.trim();
    final freeDeliveryAbove =
        freeDeliveryRaw.isEmpty ? null : double.tryParse(freeDeliveryRaw);
    if (freeDeliveryRaw.isNotEmpty && freeDeliveryAbove == null) {
      setState(() => _error = 'Enter a valid free-delivery threshold.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      await context.read<CompanySettingsController>().updateStorefrontSettings(
            storeSlug: slug.isEmpty ? null : slug,
            storefrontEnabled: _storefrontEnabled,
            linkInBioEnabled: _linkInBioEnabled,
            ordersAcceptCod: _ordersAcceptCod,
            deliveryFeesEnabled: _deliveryFeesEnabled,
            defaultDeliveryFee: deliveryFee ?? 0,
            freeDeliveryAbove: freeDeliveryAbove,
            dineInEnabled: _dineInEnabled,
          );
      if (!mounted) return;
      final settings = context.read<CompanySettingsController>().settings;
      setState(() => _storefrontUrl = settings.storefrontUrl);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Storefront settings saved')),
      );
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
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
          'Storefront',
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
                        'Online store',
                        style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Let customers browse your catalog and check out on a shareable link.',
                        style: GoogleFonts.manrope(
                          color: AppColors.textMuted,
                          fontSize: 13,
                        ),
                      ),
                      const SizedBox(height: 12),
                      SwitchListTile.adaptive(
                        value: _storefrontEnabled,
                        onChanged: _saving
                            ? null
                            : (value) =>
                                setState(() => _storefrontEnabled = value),
                        title: const Text('Storefront enabled'),
                        subtitle: const Text('Turn your online store on or off'),
                        contentPadding: EdgeInsets.zero,
                      ),
                      const SizedBox(height: 4),
                      TextField(
                        controller: _slugCtrl,
                        textInputAction: TextInputAction.done,
                        decoration: const InputDecoration(
                          labelText: 'Store link',
                          hintText: 'e.g. my-shop',
                          prefixIcon: Icon(Icons.link_outlined),
                          prefixText: '/s/',
                        ),
                        onChanged: (_) => setState(() {}),
                      ),
                      if (_storefrontUrl != null &&
                          _storefrontUrl!.isNotEmpty) ...[
                        const SizedBox(height: 12),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 10,
                          ),
                          decoration: BoxDecoration(
                            color: AppColors.primarySoft,
                            borderRadius: BorderRadius.circular(AppRadii.md),
                          ),
                          child: Row(
                            children: [
                              const Icon(Icons.storefront_outlined,
                                  color: AppColors.primaryDark, size: 18),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  _storefrontUrl!,
                                  overflow: TextOverflow.ellipsis,
                                  style: GoogleFonts.manrope(
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.primaryDark,
                                    fontSize: 13,
                                  ),
                                ),
                              ),
                              IconButton(
                                onPressed: _copyUrl,
                                tooltip: 'Copy store link',
                                icon: const Icon(Icons.copy_outlined,
                                    color: AppColors.primaryDark, size: 18),
                              ),
                            ],
                          ),
                        ),
                      ],
                      const SizedBox(height: 4),
                      SwitchListTile.adaptive(
                        value: _linkInBioEnabled,
                        onChanged: _saving
                            ? null
                            : (value) =>
                                setState(() => _linkInBioEnabled = value),
                        title: const Text('Link-in-bio page'),
                        subtitle: const Text(
                          'Show a shareable bio page with your store link',
                        ),
                        contentPadding: EdgeInsets.zero,
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
                        'Payments & delivery',
                        style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Choose how customers pay and whether delivery fees apply.',
                        style: GoogleFonts.manrope(
                          color: AppColors.textMuted,
                          fontSize: 13,
                        ),
                      ),
                      const SizedBox(height: 4),
                      SwitchListTile.adaptive(
                        value: _ordersAcceptCod,
                        onChanged: _saving
                            ? null
                            : (value) =>
                                setState(() => _ordersAcceptCod = value),
                        title: const Text('Cash on delivery'),
                        subtitle: const Text('Allow customers to pay on delivery'),
                        contentPadding: EdgeInsets.zero,
                      ),
                      SwitchListTile.adaptive(
                        value: _deliveryFeesEnabled,
                        onChanged: _saving
                            ? null
                            : (value) =>
                                setState(() => _deliveryFeesEnabled = value),
                        title: const Text('Delivery fees'),
                        subtitle:
                            const Text('Charge a fee for delivery orders'),
                        contentPadding: EdgeInsets.zero,
                      ),
                      if (_deliveryFeesEnabled) ...[
                        const SizedBox(height: 8),
                        TextField(
                          controller: _deliveryFeeCtrl,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          decoration: const InputDecoration(
                            labelText: 'Default delivery fee',
                            prefixIcon: Icon(Icons.local_shipping_outlined),
                          ),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _freeDeliveryCtrl,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          decoration: const InputDecoration(
                            labelText: 'Free delivery above',
                            hintText: 'Blank = never free',
                            prefixIcon: Icon(Icons.card_giftcard_outlined),
                          ),
                        ),
                      ],
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
                        'Dine-in',
                        style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Let customers order from a table via QR code.',
                        style: GoogleFonts.manrope(
                          color: AppColors.textMuted,
                          fontSize: 13,
                        ),
                      ),
                      SwitchListTile.adaptive(
                        value: _dineInEnabled,
                        onChanged: _saving
                            ? null
                            : (value) => setState(() => _dineInEnabled = value),
                        title: const Text('Dine-in ordering'),
                        contentPadding: EdgeInsets.zero,
                      ),
                    ],
                  ),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(_error!, style: const TextStyle(color: Colors.redAccent)),
                ],
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: _saving ? null : _save,
                  style: FilledButton.styleFrom(
                    minimumSize: const Size.fromHeight(52),
                  ),
                  child: _saving
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text('Save storefront settings'),
                ),
              ],
            ),
    );
  }
}
