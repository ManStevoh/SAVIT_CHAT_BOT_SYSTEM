import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/config/app_config.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import 'product_form_screen.dart';
import 'product_models.dart';
import 'product_repository.dart';
import 'product_variants_screen.dart';

class ProductsScreen extends StatefulWidget {
  const ProductsScreen({super.key});

  @override
  State<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends State<ProductsScreen> {
  late final ProductRepository _repo;
  late Future<List<Product>> _future;

  @override
  void initState() {
    super.initState();
    _repo = context.read<ProductRepository>();
    _future = _repo.listProducts();
  }

  Future<void> _reload() async {
    setState(() {
      _future = _repo.listProducts();
    });
    await _future;
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
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => ProductFormScreen(product: product),
      ),
    );
    if (saved == true && mounted) {
      await _reload();
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

  String _formatPrice(double price) {
    if (price == price.roundToDouble()) {
      return price.toStringAsFixed(0);
    }
    return price.toStringAsFixed(2);
  }

  @override
  Widget build(BuildContext context) {
    final apiBaseUrl = context.read<AppConfig>().apiBaseUrl;

    return Scaffold(
      appBar: AppBar(title: const Text('Products')),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _openForm(),
        tooltip: 'Add product',
        child: const Icon(Icons.add),
      ),
      body: RefreshIndicator(
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
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  const SizedBox(height: 120),
                  Icon(Icons.error_outline, color: Colors.red.shade300, size: 40),
                  const SizedBox(height: 12),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 24),
                    child: Text(message, textAlign: TextAlign.center),
                  ),
                ],
              );
            }

            final products = snapshot.data ?? [];
            if (products.isEmpty) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: const [
                  SizedBox(height: 120),
                  Icon(Icons.inventory_2_outlined, color: AppColors.primary, size: 40),
                  SizedBox(height: 12),
                  Text('No products yet', textAlign: TextAlign.center),
                  SizedBox(height: 4),
                  Text(
                    'Tap + to add your first product.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: AppColors.textMuted),
                  ),
                ],
              );
            }

            return ListView.separated(
              padding: const EdgeInsets.only(bottom: 88),
              itemCount: products.length,
              separatorBuilder: (_, __) => const Divider(height: 1),
              itemBuilder: (context, index) {
                final product = products[index];
                final imageUrl = Product.resolveImageUrl(product.image, apiBaseUrl);

                return ListTile(
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                  leading: ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: imageUrl != null
                        ? Image.network(
                            imageUrl,
                            width: 48,
                            height: 48,
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) => _placeholderAvatar(product.name),
                          )
                        : _placeholderAvatar(product.name),
                  ),
                  title: Text(
                    product.name,
                    style: const TextStyle(fontWeight: FontWeight.w600),
                  ),
                  subtitle: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${product.category.isNotEmpty ? product.category : 'Uncategorized'} · ${product.productType} · ${product.fulfillmentType}',
                        style: const TextStyle(color: AppColors.textMuted, fontSize: 12),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Stock: ${product.stock}',
                        style: TextStyle(
                          fontSize: 12,
                          color: product.stock > 0 ? AppColors.textMuted : Colors.orange.shade700,
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
                            _formatPrice(product.price),
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              color: AppColors.primary,
                            ),
                          ),
                          const SizedBox(height: 4),
                          _StatusChip(active: product.isActive),
                        ],
                      ),
                      PopupMenuButton<String>(
                        onSelected: (value) {
                          if (value == 'edit') {
                            _openForm(product: product);
                          } else if (value == 'variants') {
                            _openVariants(product);
                          } else if (value == 'delete') {
                            _confirmDelete(product);
                          }
                        },
                        itemBuilder: (context) => const [
                          PopupMenuItem(value: 'edit', child: Text('Edit')),
                          PopupMenuItem(value: 'variants', child: Text('Variants')),
                          PopupMenuItem(value: 'delete', child: Text('Delete')),
                        ],
                      ),
                    ],
                  ),
                  onTap: () => _openForm(product: product),
                );
              },
            );
          },
        ),
      ),
    );
  }

  Widget _placeholderAvatar(String name) {
    final initial = name.isNotEmpty ? name[0].toUpperCase() : '?';
    return CircleAvatar(
      radius: 24,
      backgroundColor: AppColors.bubbleIncoming,
      child: Text(initial, style: const TextStyle(fontWeight: FontWeight.w600)),
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
