import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/config/app_config.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_state_views.dart';
import '../../shared/widgets/app_surface.dart';
import '../settings/company_settings_controller.dart';
import 'product_form_screen.dart';
import 'product_models.dart';
import 'product_repository.dart';
import 'product_variants_screen.dart';

class _ProductFilter {
  const _ProductFilter(this.key, this.label);
  final String key;
  final String label;
}

const _kFilters = [
  _ProductFilter('all', 'All'),
  _ProductFilter('active', 'Active'),
  _ProductFilter('inactive', 'Inactive'),
  _ProductFilter('physical', 'Physical'),
  _ProductFilter('digital', 'Digital'),
  _ProductFilter('service', 'Service'),
];

class ProductsScreen extends StatefulWidget {
  const ProductsScreen({super.key});

  @override
  State<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends State<ProductsScreen> {
  late final ProductRepository _repo;
  late Future<List<Product>> _future;
  final _search = TextEditingController();
  Timer? _searchDebounce;
  String _filter = 'all';
  final Set<String> _togglingIds = {};

  @override
  void initState() {
    super.initState();
    _repo = context.read<ProductRepository>();
    _future = _repo.listProducts();
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  Future<void> _reload() async {
    setState(() {
      _future = _repo.listProducts(search: _search.text.trim());
    });
    await _future;
  }

  void _onSearchChanged(String _) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 350), () {
      if (!mounted) return;
      _reload();
    });
    setState(() {});
  }

  List<Product> _applyFilter(List<Product> products) {
    switch (_filter) {
      case 'active':
        return products.where((p) => p.isActive).toList();
      case 'inactive':
        return products.where((p) => !p.isActive).toList();
      case 'physical':
      case 'digital':
      case 'service':
        return products.where((p) => p.productType == _filter).toList();
      case 'all':
      default:
        return products;
    }
  }

  Future<void> _openVariants(Product product) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ProductVariantsScreen(product: product),
      ),
    );
    await _reload();
  }

  Future<void> _openForm({Product? product}) async {
    final isEditing = product != null;
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => ProductFormScreen(product: product),
      ),
    );
    if (saved == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(isEditing ? 'Product updated' : 'Product created'),
        ),
      );
      await _reload();
    }
  }

  Future<void> _toggleStatus(Product product) async {
    if (_togglingIds.contains(product.id)) return;
    setState(() => _togglingIds.add(product.id));
    final newStatus = product.isActive ? 'inactive' : 'active';
    try {
      await _repo.updateProductStatus(product.id, newStatus);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '"${product.name}" marked ${newStatus == 'active' ? 'active' : 'inactive'}',
          ),
        ),
      );
      await _reload();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    } finally {
      if (mounted) setState(() => _togglingIds.remove(product.id));
    }
  }

  Future<void> _duplicate(Product product) async {
    try {
      final copy = await _repo.duplicateProduct(product);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('"${copy.name}" created')),
      );
      await _reload();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    }
  }

  Future<void> _confirmDelete(Product product) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete product?'),
        content: Text(
          'Remove "${product.name}" from your catalog? This cannot be undone.',
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
      await _repo.deleteProduct(product.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('"${product.name}" deleted')),
      );
      await _reload();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final apiBaseUrl = context.read<AppConfig>().apiBaseUrl;
    final formatMoney = context.watch<CompanySettingsController>().formatMoney;

    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(title: const Text('Products')),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _openForm(),
        tooltip: 'Add product',
        child: const Icon(Icons.add),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onChanged: _onSearchChanged,
              onSubmitted: (_) => _reload(),
              decoration: InputDecoration(
                hintText: 'Search products by name',
                prefixIcon:
                    const Icon(Icons.search, color: AppColors.textMuted),
                filled: true,
                fillColor: AppColors.surface,
                suffixIcon: _search.text.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.clear),
                        tooltip: 'Clear search',
                        onPressed: () {
                          _search.clear();
                          _reload();
                          setState(() {});
                        },
                      ),
              ),
            ),
          ),
          SizedBox(
            height: 44,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 6),
              itemCount: _kFilters.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (context, index) {
                final filter = _kFilters[index];
                final selected = _filter == filter.key;
                return ChoiceChip(
                  label: Text(filter.label),
                  selected: selected,
                  onSelected: (_) => setState(() => _filter = filter.key),
                );
              },
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _reload,
              child: FutureBuilder<List<Product>>(
                future: _future,
                builder: (context, snapshot) {
                  if (snapshot.connectionState == ConnectionState.waiting) {
                    return const Center(child: CircularProgressIndicator());
                  }
                  if (snapshot.hasError) {
                    final message = snapshot.error is ApiException
                        ? (snapshot.error as ApiException).message
                        : snapshot.error.toString();
                    return AppErrorState(message: message, onRetry: _reload);
                  }

                  final allProducts = snapshot.data ?? [];
                  final products = _applyFilter(allProducts);

                  if (products.isEmpty) {
                    final filtering = _filter != 'all' ||
                        _search.text.trim().isNotEmpty;
                    return AppEmptyState(
                      icon: filtering
                          ? Icons.filter_alt_off_outlined
                          : Icons.inventory_2_outlined,
                      title: filtering ? 'No matches' : 'No products yet',
                      subtitle: filtering
                          ? 'Try a different search or filter.'
                          : 'Tap + to add your first product.',
                      actionLabel: filtering ? null : 'Add product',
                      onAction: filtering ? null : () => _openForm(),
                    );
                  }

                  return ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 88),
                    itemCount: products.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, index) {
                      final product = products[index];
                      final imageUrl =
                          Product.resolveImageUrl(product.image, apiBaseUrl);
                      final toggling = _togglingIds.contains(product.id);

                      return AppSurface(
                        onTap: () => _openForm(product: product),
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 6,
                        ),
                        child: ListTile(
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 4,
                          ),
                          leading: ClipRRect(
                            borderRadius: BorderRadius.circular(AppRadii.sm),
                            child: imageUrl != null
                                ? Image.network(
                                    imageUrl,
                                    width: 52,
                                    height: 52,
                                    fit: BoxFit.cover,
                                    errorBuilder: (_, __, ___) =>
                                        _placeholderAvatar(product.name),
                                  )
                                : _placeholderAvatar(product.name),
                          ),
                          title: Text(
                            product.name,
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                          subtitle: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const SizedBox(height: 2),
                              Row(
                                children: [
                                  _TypeBadge(type: product.productType),
                                  const SizedBox(width: 6),
                                  Expanded(
                                    child: Text(
                                      product.category.isNotEmpty
                                          ? product.category
                                          : 'Uncategorized',
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(
                                        color: AppColors.textMuted,
                                        fontSize: 12,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 4),
                              Text(
                                product.trackInventory
                                    ? 'Stock: ${product.stock}'
                                    : 'Stock not tracked',
                                style: TextStyle(
                                  fontSize: 12,
                                  color: product.trackInventory &&
                                          product.stock <= 0
                                      ? Colors.orange.shade700
                                      : AppColors.textMuted,
                                ),
                              ),
                            ],
                          ),
                          trailing: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Text(
                                    formatMoney(product.price),
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w800,
                                      color: AppColors.primary,
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  GestureDetector(
                                    onTap: toggling
                                        ? null
                                        : () => _toggleStatus(product),
                                    child: toggling
                                        ? const SizedBox(
                                            width: 14,
                                            height: 14,
                                            child: CircularProgressIndicator(
                                              strokeWidth: 2,
                                            ),
                                          )
                                        : _StatusChip(active: product.isActive),
                                  ),
                                ],
                              ),
                              PopupMenuButton<String>(
                                onSelected: (value) {
                                  if (value == 'edit') {
                                    _openForm(product: product);
                                  } else if (value == 'variants') {
                                    _openVariants(product);
                                  } else if (value == 'duplicate') {
                                    _duplicate(product);
                                  } else if (value == 'delete') {
                                    _confirmDelete(product);
                                  }
                                },
                                itemBuilder: (context) => const [
                                  PopupMenuItem(
                                    value: 'edit',
                                    child: Text('Edit'),
                                  ),
                                  PopupMenuItem(
                                    value: 'variants',
                                    child: Text('Variants'),
                                  ),
                                  PopupMenuItem(
                                    value: 'duplicate',
                                    child: Text('Duplicate'),
                                  ),
                                  PopupMenuItem(
                                    value: 'delete',
                                    child: Text('Delete'),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _placeholderAvatar(String name) {
    final initial = name.isNotEmpty ? name[0].toUpperCase() : '?';
    return Container(
      width: 52,
      height: 52,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: AppColors.primarySoft,
        borderRadius: BorderRadius.circular(AppRadii.sm),
      ),
      child: Text(
        initial,
        style: const TextStyle(
          fontWeight: FontWeight.w800,
          color: AppColors.primaryDark,
        ),
      ),
    );
  }
}

class _TypeBadge extends StatelessWidget {
  const _TypeBadge({required this.type});

  final String type;

  Color get _color {
    switch (type) {
      case 'digital':
        return AppColors.accentTeal;
      case 'service':
        return AppColors.accentAmber;
      case 'physical':
      default:
        return AppColors.accentBlue;
    }
  }

  IconData get _icon {
    switch (type) {
      case 'digital':
        return Icons.cloud_download_outlined;
      case 'service':
        return Icons.event_available_outlined;
      case 'physical':
      default:
        return Icons.local_shipping_outlined;
    }
  }

  String get _label {
    switch (type) {
      case 'digital':
        return 'Digital';
      case 'service':
        return 'Service';
      case 'physical':
      default:
        return 'Physical';
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
      decoration: BoxDecoration(
        color: _color.withOpacity(0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(_icon, size: 11, color: _color),
          const SizedBox(width: 3),
          Text(
            _label,
            style: GoogleFonts.manrope(
              fontSize: 10,
              fontWeight: FontWeight.w700,
              color: _color,
            ),
          ),
        ],
      ),
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
