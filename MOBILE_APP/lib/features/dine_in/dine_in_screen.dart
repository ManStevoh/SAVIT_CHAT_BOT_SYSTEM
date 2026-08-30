import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/safe_url.dart';
import '../../shared/widgets/app_surface.dart';
import '../companion/companion_models.dart';
import '../companion/companion_repository.dart';

class DineInScreen extends StatefulWidget {
  const DineInScreen({super.key});

  @override
  State<DineInScreen> createState() => _DineInScreenState();
}

class _DineInScreenState extends State<DineInScreen> {
  late Future<({bool allowed, String? message, List<DineInTable> tables})> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<CompanionRepository>().dineInTables();
  }

  Future<void> _reload() async {
    setState(() => _future = context.read<CompanionRepository>().dineInTables());
    await _future;
  }

  Future<void> _edit([DineInTable? table]) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _TableSheet(table: table),
    );
    if (saved == true) await _reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: Text('Dine-in', style: GoogleFonts.manrope(fontWeight: FontWeight.w800)),
        actions: [IconButton(onPressed: () => _edit(), icon: const Icon(Icons.add))],
      ),
      body: FutureBuilder(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text(snapshot.error is ApiException ? (snapshot.error! as ApiException).message : 'Failed'));
          }
          final data = snapshot.data!;
          if (!data.allowed) {
            return Padding(
              padding: const EdgeInsets.all(16),
              child: AppSurface(
                padding: const EdgeInsets.all(20),
                child: Text(data.message ?? 'Dine-in is not on your plan.', style: GoogleFonts.manrope(color: AppColors.textMuted)),
              ),
            );
          }
          if (data.tables.isEmpty) {
            return Center(child: Text('No tables yet. Add a QR table.', style: GoogleFonts.manrope(color: AppColors.textMuted)));
          }
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
            children: data.tables
                .map(
                  (t) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: AppSurface(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                      child: ListTile(
                        title: Text(t.name, style: GoogleFonts.manrope(fontWeight: FontWeight.w800)),
                        subtitle: Text('${t.code ?? 'No code'}${t.seats != null ? ' · ${t.seats} seats' : ''}'),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            IconButton(
                              tooltip: 'Copy order link',
                              onPressed: () async {
                                final url = t.orderUrl;
                                if (url == null) return;
                                await Clipboard.setData(ClipboardData(text: url));
                                if (!context.mounted) return;
                                ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Link copied')));
                              },
                              icon: const Icon(Icons.copy),
                            ),
                            IconButton(
                              onPressed: () => openHttpsUrl(t.orderUrl),
                              icon: const Icon(Icons.qr_code_2_outlined),
                            ),
                            IconButton(onPressed: () => _edit(t), icon: const Icon(Icons.edit_outlined)),
                          ],
                        ),
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

class _TableSheet extends StatefulWidget {
  const _TableSheet({this.table});
  final DineInTable? table;

  @override
  State<_TableSheet> createState() => _TableSheetState();
}

class _TableSheetState extends State<_TableSheet> {
  late final TextEditingController _name;
  late final TextEditingController _code;
  late final TextEditingController _seats;
  bool _active = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _name = TextEditingController(text: widget.table?.name ?? '');
    _code = TextEditingController(text: widget.table?.code ?? '');
    _seats = TextEditingController(text: widget.table?.seats?.toString() ?? '');
    _active = widget.table?.isActive ?? true;
  }

  @override
  void dispose() {
    _name.dispose();
    _code.dispose();
    _seats.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_name.text.trim().isEmpty) return;
    setState(() => _saving = true);
    try {
      await context.read<CompanionRepository>().saveDineInTable(
            id: widget.table?.id,
            name: _name.text,
            code: _code.text,
            seats: int.tryParse(_seats.text.trim()),
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
          Text(widget.table == null ? 'New table' : 'Edit table', style: GoogleFonts.manrope(fontWeight: FontWeight.w800, fontSize: 18)),
          TextField(controller: _name, decoration: const InputDecoration(labelText: 'Table name')),
          TextField(controller: _code, decoration: const InputDecoration(labelText: 'Code (optional)')),
          TextField(controller: _seats, decoration: const InputDecoration(labelText: 'Seats'), keyboardType: TextInputType.number),
          SwitchListTile(value: _active, onChanged: (v) => setState(() => _active = v), title: const Text('Active')),
          FilledButton(onPressed: _saving ? null : _save, child: Text(_saving ? 'Saving…' : 'Save table')),
        ],
      ),
    );
  }
}
