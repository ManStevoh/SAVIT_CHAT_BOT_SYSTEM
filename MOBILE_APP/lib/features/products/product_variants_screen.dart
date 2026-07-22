import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import 'product_models.dart';
import 'product_repository.dart';

class ProductVariantsScreen extends StatefulWidget {
  const ProductVariantsScreen({super.key, required this.product});

  final Product product;

  @override
  State<ProductVariantsScreen> createState() => _ProductVariantsScreenState();
}

class _ProductVariantsScreenState extends State<ProductVariantsScreen> {
  late final ProductRepository _repo;
  late List<ProductVariant> _variants;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _repo = context.read<ProductRepository>();
    _variants = List<ProductVariant>.from(widget.product.variants);
    _reload();
  }

  Future<void> _reload() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final products = await _repo.listProducts();
      final matches = products.where((p) => p.id == widget.product.id);
      final product = matches.isEmpty ? null : matches.first;
      if (!mounted) return;
      setState(() {
        _variants = List<ProductVariant>.from(product?.variants ?? _variants);
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  Future<void> _openForm({ProductVariant? variant}) async {
    final saved = await showDialog<ProductVariant>(
      context: context,
      builder: (context) => _VariantFormDialog(
        productId: widget.product.id,
        variant: variant,
      ),
    );
    if (saved == null || !mounted) return;

    setState(() {
      final index = _variants.indexWhere((v) => v.id == saved.id);
      if (index >= 0) {
        _variants[index] = saved;
      } else {
        _variants = [..._variants, saved];
      }
    });
  }

  Future<void> _confirmDelete(ProductVariant variant) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete variant?'),
        content: Text(
          'Remove "${variant.label}" from "${widget.product.name}"? This cannot be undone.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: FilledButton.styleFrom(backgroundColor: Colors.redAccent),
            child: const Text('Delete'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    try {
      await _repo.deleteVariant(variant.id);
      if (!mounted) return;
      setState(() {
        _variants = _variants.where((v) => v.id != variant.id).toList();
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('"${variant.label}" deleted')),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    }
  }

  String _formatPrice(double price) {
    if (price == price.roundToDouble()) {
      return price.toStringAsFixed(0);
    }
    return price.toStringAsFixed(2);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Variants · ${widget.product.name}'),
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: _loading ? null : () => _openForm(),
        tooltip: 'Add variant',
        child: const Icon(Icons.add),
      ),
      body: RefreshIndicator(
        onRefresh: _reload,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_loading && _variants.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null && _variants.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          const SizedBox(height: 120),
          Icon(Icons.error_outline, color: Colors.red.shade300, size: 40),
          const SizedBox(height: 12),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 24),
            child: Text(_error!, textAlign: TextAlign.center),
          ),
          const SizedBox(height: 16),
          Center(
            child: TextButton(onPressed: _reload, child: const Text('Retry')),
          ),
        ],
      );
    }

    if (_variants.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: const [
          SizedBox(height: 120),
          Icon(Icons.view_list_outlined, color: AppColors.primary, size: 40),
          SizedBox(height: 12),
          Text('No variants yet', textAlign: TextAlign.center),
          SizedBox(height: 4),
          Text(
            'Tap + to add size, color, or other options.',
            textAlign: TextAlign.center,
            style: TextStyle(color: AppColors.textMuted),
          ),
        ],
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.only(bottom: 88),
      itemCount: _variants.length,
      separatorBuilder: (_, __) => const Divider(height: 1),
      itemBuilder: (context, index) {
        final variant = _variants[index];
        return ListTile(
          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
          leading: CircleAvatar(
            backgroundColor: AppColors.bubbleIncoming,
            child: Text(
              variant.label.isNotEmpty ? variant.label[0].toUpperCase() : '?',
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
          title: Text(
            variant.label,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
          subtitle: Text(
            'Stock: ${variant.stock}',
            style: TextStyle(
              fontSize: 12,
              color: variant.stock > 0 ? AppColors.textMuted : Colors.orange.shade700,
            ),
          ),
          trailing: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    _formatPrice(variant.price),
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      color: AppColors.primary,
                    ),
                  ),
                  const SizedBox(height: 4),
                  _StatusChip(active: variant.isActive),
                ],
              ),
              PopupMenuButton<String>(
                onSelected: (value) {
                  if (value == 'edit') {
                    _openForm(variant: variant);
                  } else if (value == 'delete') {
                    _confirmDelete(variant);
                  }
                },
                itemBuilder: (context) => const [
                  PopupMenuItem(value: 'edit', child: Text('Edit')),
                  PopupMenuItem(value: 'delete', child: Text('Delete')),
                ],
              ),
            ],
          ),
          onTap: () => _openForm(variant: variant),
        );
      },
    );
  }
}

class _VariantFormDialog extends StatefulWidget {
  const _VariantFormDialog({
    required this.productId,
    this.variant,
  });

  final String productId;
  final ProductVariant? variant;

  bool get isEditing => variant != null;

  @override
  State<_VariantFormDialog> createState() => _VariantFormDialogState();
}

class _VariantFormDialogState extends State<_VariantFormDialog> {
  late final ProductRepository _repo;
  late final TextEditingController _label;
  late final TextEditingController _price;
  late final TextEditingController _stock;
  late String _status;

  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _repo = context.read<ProductRepository>();
    final variant = widget.variant;
    _label = TextEditingController(text: variant?.label ?? '');
    _price = TextEditingController(
      text: variant != null ? variant.price.toStringAsFixed(2) : '',
    );
    _stock = TextEditingController(
      text: variant != null ? '${variant.stock}' : '0',
    );
    _status = variant?.status ?? 'active';
  }

  @override
  void dispose() {
    _label.dispose();
    _price.dispose();
    _stock.dispose();
    super.dispose();
  }

  ProductVariantInput? _buildInput() {
    final label = _label.text.trim();
    if (label.isEmpty) {
      setState(() => _error = 'Label is required.');
      return null;
    }

    final price = double.tryParse(_price.text.trim());
    if (price == null || price < 0) {
      setState(() => _error = 'Enter a valid price.');
      return null;
    }

    final stock = int.tryParse(_stock.text.trim());
    if (stock == null || stock < 0) {
      setState(() => _error = 'Enter a valid stock quantity.');
      return null;
    }

    return ProductVariantInput(
      label: label,
      price: price,
      stock: stock,
      status: _status,
    );
  }

  Future<void> _submit() async {
    final input = _buildInput();
    if (input == null) return;

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final saved = widget.isEditing
          ? await _repo.updateVariant(widget.variant!.id, input)
          : await _repo.createVariant(widget.productId, input);
      if (!mounted) return;
      Navigator.of(context).pop(saved);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.isEditing ? 'Edit Variant' : 'Add Variant'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: _label,
              textInputAction: TextInputAction.next,
              decoration: const InputDecoration(
                labelText: 'Label *',
                hintText: 'e.g. Large, Red',
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _price,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              textInputAction: TextInputAction.next,
              decoration: const InputDecoration(
                labelText: 'Price *',
                prefixIcon: Icon(Icons.attach_money),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _stock,
              keyboardType: TextInputType.number,
              textInputAction: TextInputAction.done,
              decoration: const InputDecoration(
                labelText: 'Stock',
                prefixIcon: Icon(Icons.layers_outlined),
              ),
            ),
            const SizedBox(height: 12),
            InputDecorator(
              decoration: const InputDecoration(
                labelText: 'Status',
                prefixIcon: Icon(Icons.toggle_on_outlined),
              ),
              child: DropdownButtonHideUnderline(
                child: DropdownButton<String>(
                  value: _status,
                  isExpanded: true,
                  items: const [
                    DropdownMenuItem(value: 'active', child: Text('Active')),
                    DropdownMenuItem(value: 'inactive', child: Text('Inactive')),
                  ],
                  onChanged: _saving
                      ? null
                      : (value) {
                          if (value == null) return;
                          setState(() => _status = value);
                        },
                ),
              ),
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!, style: const TextStyle(color: Colors.redAccent)),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.of(context).pop(),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: _saving ? null : _submit,
          style: FilledButton.styleFrom(
            backgroundColor: AppColors.primary,
            foregroundColor: Colors.white,
          ),
          child: _saving
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                )
              : Text(widget.isEditing ? 'Save' : 'Add'),
        ),
      ],
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.active});

  final bool active;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: active
            ? AppColors.primary.withOpacity(0.12)
            : Colors.grey.shade200,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        active ? 'Active' : 'Inactive',
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: active ? AppColors.primary : AppColors.textMuted,
        ),
      ),
    );
  }
}
