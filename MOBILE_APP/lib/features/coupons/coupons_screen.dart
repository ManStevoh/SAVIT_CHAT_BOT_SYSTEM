import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';
import '../companion/companion_models.dart';
import '../companion/companion_repository.dart';

class CouponsScreen extends StatefulWidget {
  const CouponsScreen({super.key});

  @override
  State<CouponsScreen> createState() => _CouponsScreenState();
}

class _CouponsScreenState extends State<CouponsScreen> {
  late Future<List<StoreCoupon>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<CompanionRepository>().coupons();
  }

  Future<void> _reload() async {
    setState(() => _future = context.read<CompanionRepository>().coupons());
    await _future;
  }

  Future<void> _edit([StoreCoupon? coupon]) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _CouponSheet(coupon: coupon),
    );
    if (saved == true) await _reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: Text('Coupons', style: GoogleFonts.manrope(fontWeight: FontWeight.w800)),
        actions: [IconButton(onPressed: () => _edit(), icon: const Icon(Icons.add))],
      ),
      body: FutureBuilder<List<StoreCoupon>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text(snapshot.error is ApiException ? (snapshot.error! as ApiException).message : 'Failed'));
          }
          final items = snapshot.data ?? [];
          if (items.isEmpty) {
            return Center(child: Text('No storefront coupons yet.', style: GoogleFonts.manrope(color: AppColors.textMuted)));
          }
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
            children: items
                .map(
                  (c) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: AppSurface(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                      child: ListTile(
                        title: Text(c.code, style: GoogleFonts.manrope(fontWeight: FontWeight.w800)),
                        subtitle: Text('${c.label} · ${c.redeemedCount} used${c.isCurrentlyValid ? '' : ' · inactive window'}'),
                        trailing: IconButton(onPressed: () => _edit(c), icon: const Icon(Icons.edit_outlined)),
                      ),
                    ),
                  ),
                )
                .toList(),
          );
        },
      ),
    );
  }
}

class _CouponSheet extends StatefulWidget {
  const _CouponSheet({this.coupon});
  final StoreCoupon? coupon;

  @override
  State<_CouponSheet> createState() => _CouponSheetState();
}

class _CouponSheetState extends State<_CouponSheet> {
  late final TextEditingController _code;
  late final TextEditingController _value;
  late final TextEditingController _min;
  String _type = 'percent';
  bool _active = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _code = TextEditingController(text: widget.coupon?.code ?? '');
    _value = TextEditingController(text: widget.coupon?.value.toString() ?? '');
    _min = TextEditingController(text: widget.coupon?.minOrder?.toString() ?? '');
    _type = widget.coupon?.type ?? 'percent';
    _active = widget.coupon?.isActive ?? true;
  }

  @override
  void dispose() {
    _code.dispose();
    _value.dispose();
    _min.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final code = _code.text.trim().toUpperCase();
    final value = double.tryParse(_value.text.trim());
    if (code.isEmpty || value == null || value <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Code and a positive value are required.')));
      return;
    }
    setState(() => _saving = true);
    try {
      await context.read<CompanionRepository>().saveCoupon(
            id: widget.coupon?.id,
            code: code,
            type: _type,
            value: value,
            minOrder: double.tryParse(_min.text.trim()),
            isActive: _active,
          );
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 16, 16, 16 + MediaQuery.of(context).viewInsets.bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(widget.coupon == null ? 'New coupon' : 'Edit coupon', style: GoogleFonts.manrope(fontWeight: FontWeight.w800, fontSize: 18)),
          TextField(controller: _code, decoration: const InputDecoration(labelText: 'Code'), textCapitalization: TextCapitalization.characters),
          DropdownButtonFormField<String>(
            value: _type,
            items: const [
              DropdownMenuItem(value: 'percent', child: Text('Percent')),
              DropdownMenuItem(value: 'fixed', child: Text('Fixed amount')),
            ],
            onChanged: (v) => setState(() => _type = v ?? 'percent'),
            decoration: const InputDecoration(labelText: 'Type'),
          ),
          TextField(controller: _value, decoration: const InputDecoration(labelText: 'Value'), keyboardType: TextInputType.number),
          TextField(controller: _min, decoration: const InputDecoration(labelText: 'Minimum order (optional)'), keyboardType: TextInputType.number),
          SwitchListTile(value: _active, onChanged: (v) => setState(() => _active = v), title: const Text('Active')),
          FilledButton(onPressed: _saving ? null : _save, child: Text(_saving ? 'Saving…' : 'Save coupon')),
        ],
      ),
    );
  }
}
