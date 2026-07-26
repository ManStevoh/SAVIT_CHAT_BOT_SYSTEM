import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';
import 'tax_models.dart';
import 'tax_repository.dart';

class TaxesScreen extends StatefulWidget {
  const TaxesScreen({super.key});

  @override
  State<TaxesScreen> createState() => _TaxesScreenState();
}

class _TaxesScreenState extends State<TaxesScreen> {
  late TaxRepository _repo;
  late Future<_TaxPageData> _future;

  @override
  void initState() {
    super.initState();
    _repo = context.read<TaxRepository>();
    _future = _load();
  }

  Future<_TaxPageData> _load() async {
    final enabled = await _repo.isTaxEnabled();
    final rates = await _repo.listTaxRates();
    return _TaxPageData(enabled: enabled, rates: rates);
  }

  Future<void> _reload() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _toggleEnabled(bool value) async {
    try {
      await _repo.setTaxEnabled(value);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(value ? 'Tax calculation enabled' : 'Tax calculation disabled'),
        ),
      );
      await _reload();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _openForm({TaxRate? rate}) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _TaxRateFormSheet(repo: _repo, rate: rate),
    );
    if (saved == true) await _reload();
  }

  Future<void> _confirmDelete(TaxRate rate) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete tax rate?'),
        content: Text(rate.name),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await _repo.deleteTaxRate(rate.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Tax rate deleted')),
      );
      await _reload();
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
          'Taxes',
          style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
        ),
        actions: [
          IconButton(
            onPressed: () => _openForm(),
            icon: const Icon(Icons.add),
            tooltip: 'Add tax rate',
          ),
        ],
      ),
      body: FutureBuilder<_TaxPageData>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            final message = snapshot.error is ApiException
                ? (snapshot.error! as ApiException).message
                : 'Failed to load taxes';
            return Center(child: Text(message));
          }
          final data = snapshot.data!;
          return RefreshIndicator(
            onRefresh: _reload,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              children: [
                AppSurface(
                  padding: const EdgeInsets.all(16),
                  child: SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text(
                      'Enable tax on orders',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                    subtitle: const Text(
                      'Checkout adds or extracts tax using your rates.',
                    ),
                    value: data.enabled,
                    onChanged: _toggleEnabled,
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  'Tax rates',
                  style: GoogleFonts.manrope(
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 8),
                if (data.rates.isEmpty)
                  const AppSurface(
                    padding: EdgeInsets.all(18),
                    child: Text('No tax rates yet. Tap + to add VAT, GST, or sales tax.'),
                  )
                else
                  ...data.rates.map(
                    (rate) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: AppSurface(
                        padding: const EdgeInsets.all(14),
                        child: ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(
                            rate.name,
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          subtitle: Text(
                            [
                              if (rate.code != null && rate.code!.isNotEmpty) rate.code!,
                              '${rate.rate}%',
                              rate.isInclusive ? 'inclusive' : 'exclusive',
                              if (rate.isDefault) 'default',
                              rate.isActive ? 'active' : 'inactive',
                            ].join(' · '),
                          ),
                          trailing: PopupMenuButton<String>(
                            onSelected: (value) {
                              if (value == 'edit') _openForm(rate: rate);
                              if (value == 'delete') _confirmDelete(rate);
                            },
                            itemBuilder: (_) => const [
                              PopupMenuItem(value: 'edit', child: Text('Edit')),
                              PopupMenuItem(value: 'delete', child: Text('Delete')),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _TaxPageData {
  const _TaxPageData({required this.enabled, required this.rates});

  final bool enabled;
  final List<TaxRate> rates;
}

class _TaxRateFormSheet extends StatefulWidget {
  const _TaxRateFormSheet({required this.repo, this.rate});

  final TaxRepository repo;
  final TaxRate? rate;

  @override
  State<_TaxRateFormSheet> createState() => _TaxRateFormSheetState();
}

class _TaxRateFormSheetState extends State<_TaxRateFormSheet> {
  late final TextEditingController _name;
  late final TextEditingController _code;
  late final TextEditingController _rate;
  late bool _inclusive;
  late bool _isDefault;
  late bool _active;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final rate = widget.rate;
    _name = TextEditingController(text: rate?.name ?? '');
    _code = TextEditingController(text: rate?.code ?? '');
    _rate = TextEditingController(
      text: rate != null ? rate.rate.toString() : '',
    );
    _inclusive = rate?.isInclusive ?? false;
    _isDefault = rate?.isDefault ?? false;
    _active = rate?.isActive ?? true;
  }

  @override
  void dispose() {
    _name.dispose();
    _code.dispose();
    _rate.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final name = _name.text.trim();
    final rate = double.tryParse(_rate.text.trim());
    if (name.isEmpty || rate == null || rate < 0 || rate > 100) {
      setState(() => _error = 'Enter a name and rate between 0 and 100.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    final input = TaxRateInput(
      name: name,
      code: _code.text.trim().isEmpty ? null : _code.text.trim(),
      rate: rate,
      isInclusive: _inclusive,
      isDefault: _isDefault,
      isActive: _active,
    );
    try {
      if (widget.rate == null) {
        await widget.repo.createTaxRate(input);
      } else {
        await widget.repo.updateTaxRate(widget.rate!.id, input);
      }
      if (!mounted) return;
      Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _saving = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.of(context).viewInsets.bottom;
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 16, 16, 16 + bottom),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              widget.rate == null ? 'Add tax rate' : 'Edit tax rate',
              style: GoogleFonts.manrope(fontWeight: FontWeight.w800, fontSize: 18),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _name,
              decoration: const InputDecoration(labelText: 'Name *'),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _code,
              decoration: const InputDecoration(labelText: 'Code (optional)'),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _rate,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(labelText: 'Rate (%) *'),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Tax inclusive prices'),
              value: _inclusive,
              onChanged: (v) => setState(() => _inclusive = v),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Default rate'),
              value: _isDefault,
              onChanged: (v) => setState(() => _isDefault = v),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Active'),
              value: _active,
              onChanged: (v) => setState(() => _active = v),
            ),
            if (_error != null) ...[
              Text(_error!, style: const TextStyle(color: Colors.red)),
              const SizedBox(height: 8),
            ],
            FilledButton(
              onPressed: _saving ? null : _save,
              child: _saving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Text(widget.rate == null ? 'Create' : 'Save'),
            ),
          ],
        ),
      ),
    );
  }
}
