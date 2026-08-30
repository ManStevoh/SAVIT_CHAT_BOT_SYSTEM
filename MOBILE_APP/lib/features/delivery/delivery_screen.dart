import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';
import '../companion/companion_models.dart';
import '../companion/companion_repository.dart';

class DeliveryScreen extends StatefulWidget {
  const DeliveryScreen({super.key});

  @override
  State<DeliveryScreen> createState() => _DeliveryScreenState();
}

class _DeliveryScreenState extends State<DeliveryScreen> {
  late Future<List<DeliveryZone>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<CompanionRepository>().deliveryZones();
  }

  Future<void> _reload() async {
    setState(() => _future = context.read<CompanionRepository>().deliveryZones());
    await _future;
  }

  Future<void> _edit([DeliveryZone? zone]) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _ZoneSheet(zone: zone),
    );
    if (saved == true) await _reload();
  }

  Future<void> _delete(DeliveryZone zone) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete zone?'),
        content: Text(zone.name),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Delete')),
        ],
      ),
    );
    if (ok != true) return;
    final repo = context.read<CompanionRepository>();
    try {
      await repo.deleteDeliveryZone(zone.id);
      if (!mounted) return;
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
        title: Text('Delivery', style: GoogleFonts.manrope(fontWeight: FontWeight.w800)),
        actions: [IconButton(onPressed: () => _edit(), icon: const Icon(Icons.add))],
      ),
      body: FutureBuilder<List<DeliveryZone>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text(snapshot.error is ApiException ? (snapshot.error! as ApiException).message : 'Failed'));
          }
          final zones = snapshot.data ?? [];
          if (zones.isEmpty) {
            return Center(
              child: Text('No delivery zones yet.', style: GoogleFonts.manrope(color: AppColors.textMuted)),
            );
          }
          return ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
            itemCount: zones.length,
            itemBuilder: (context, i) {
              final z = zones[i];
              return Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: AppSurface(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                  child: ListTile(
                    title: Text(z.name, style: GoogleFonts.manrope(fontWeight: FontWeight.w800)),
                    subtitle: Text(
                      'Fee ${z.fee.toStringAsFixed(0)}${z.minOrderAmount != null ? ' · min ${z.minOrderAmount!.toStringAsFixed(0)}' : ''}${z.isActive ? '' : ' · off'}',
                    ),
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        IconButton(onPressed: () => _edit(z), icon: const Icon(Icons.edit_outlined)),
                        IconButton(onPressed: () => _delete(z), icon: const Icon(Icons.delete_outline)),
                      ],
                    ),
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class _ZoneSheet extends StatefulWidget {
  const _ZoneSheet({this.zone});
  final DeliveryZone? zone;

  @override
  State<_ZoneSheet> createState() => _ZoneSheetState();
}

class _ZoneSheetState extends State<_ZoneSheet> {
  late final TextEditingController _name;
  late final TextEditingController _fee;
  late final TextEditingController _min;
  late final TextEditingController _keywords;
  bool _active = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final z = widget.zone;
    _name = TextEditingController(text: z?.name ?? '');
    _fee = TextEditingController(text: z == null ? '' : z.fee.toString());
    _min = TextEditingController(text: z?.minOrderAmount?.toString() ?? '');
    _keywords = TextEditingController(text: z?.keywords.join(', ') ?? '');
    _active = z?.isActive ?? true;
  }

  @override
  void dispose() {
    _name.dispose();
    _fee.dispose();
    _min.dispose();
    _keywords.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final name = _name.text.trim();
    final fee = double.tryParse(_fee.text.trim());
    if (name.isEmpty || fee == null || fee < 0) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Name and a valid fee are required.')));
      return;
    }
    setState(() => _saving = true);
    try {
      await context.read<CompanionRepository>().saveDeliveryZone(
            id: widget.zone?.id,
            name: name,
            fee: fee,
            minOrderAmount: double.tryParse(_min.text.trim()),
            keywords: _keywords.text.split(',').map((e) => e.trim()).where((e) => e.isNotEmpty).toList(),
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
          Text(widget.zone == null ? 'New zone' : 'Edit zone', style: GoogleFonts.manrope(fontWeight: FontWeight.w800, fontSize: 18)),
          TextField(controller: _name, decoration: const InputDecoration(labelText: 'Area name'), textCapitalization: TextCapitalization.words),
          TextField(controller: _fee, decoration: const InputDecoration(labelText: 'Fee'), keyboardType: TextInputType.number),
          TextField(controller: _min, decoration: const InputDecoration(labelText: 'Minimum order (optional)'), keyboardType: TextInputType.number),
          TextField(controller: _keywords, decoration: const InputDecoration(labelText: 'Keywords, comma-separated')),
          SwitchListTile(value: _active, onChanged: (v) => setState(() => _active = v), title: const Text('Active')),
          FilledButton(onPressed: _saving ? null : _save, child: Text(_saving ? 'Saving…' : 'Save zone')),
        ],
      ),
    );
  }
}
