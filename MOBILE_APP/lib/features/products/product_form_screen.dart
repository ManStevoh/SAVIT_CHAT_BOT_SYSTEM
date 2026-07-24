import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../core/config/app_config.dart';
import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import 'product_models.dart';
import 'product_repository.dart';

class ProductFormScreen extends StatefulWidget {
  const ProductFormScreen({super.key, this.product});

  final Product? product;

  bool get isEditing => product != null;

  @override
  State<ProductFormScreen> createState() => _ProductFormScreenState();
}

class _ProductFormScreenState extends State<ProductFormScreen> {
  late final ProductRepository _repo;
  late final TextEditingController _name;
  late final TextEditingController _description;
  late final TextEditingController _price;
  late final TextEditingController _category;
  late final TextEditingController _accessUrl;
  late final TextEditingController _serviceBookingUrl;
  late final TextEditingController _fulfillmentInstructions;
  late final TextEditingController _licenseKeyPrefix;
  late final TextEditingController _accessExpiresDays;
  late final TextEditingController _maxDownloads;
  late final TextEditingController _bookingDurationMinutes;
  late final TextEditingController _licenseKeys;
  late final TextEditingController _stock;
  late String _status;
  late String _productType;
  late String _fulfillmentType;
  late String _licenseKeyMode;
  late bool _trackInventory;
  late bool _requiresDeliveryAddress;
  late bool _bookable;
  String? _imagePath;
  String? _digitalFilePath;

  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _repo = context.read<ProductRepository>();
    final product = widget.product;
    _name = TextEditingController(text: product?.name ?? '');
    _description = TextEditingController(text: product?.description ?? '');
    _price = TextEditingController(
      text: product != null ? product.price.toStringAsFixed(2) : '',
    );
    _category = TextEditingController(text: product?.category ?? '');
    _accessUrl = TextEditingController(text: product?.accessUrl ?? '');
    _serviceBookingUrl =
        TextEditingController(text: product?.serviceBookingUrl ?? '');
    _fulfillmentInstructions = TextEditingController(
      text: product?.fulfillmentInstructions ?? '',
    );
    _licenseKeyPrefix =
        TextEditingController(text: product?.licenseKeyPrefix ?? '');
    _accessExpiresDays = TextEditingController(
      text: product?.accessExpiresDays != null
          ? '${product!.accessExpiresDays}'
          : '',
    );
    _maxDownloads = TextEditingController(
      text: product?.maxDownloads != null ? '${product!.maxDownloads}' : '',
    );
    _bookingDurationMinutes = TextEditingController(
      text: product?.bookingDurationMinutes != null
          ? '${product!.bookingDurationMinutes}'
          : '',
    );
    _licenseKeys = TextEditingController();
    _stock = TextEditingController(
      text: product != null ? '${product.stock}' : '0',
    );
    _status = product?.status ?? 'active';
    _productType = product?.productType ?? 'physical';
    _fulfillmentType = product?.fulfillmentType ?? 'shipping';
    _licenseKeyMode = product?.licenseKeyMode ?? 'none';
    _trackInventory = product?.trackInventory ?? true;
    _requiresDeliveryAddress = product?.requiresDeliveryAddress ?? true;
    _bookable = product?.bookable ?? false;
  }

  @override
  void dispose() {
    _name.dispose();
    _description.dispose();
    _price.dispose();
    _category.dispose();
    _accessUrl.dispose();
    _serviceBookingUrl.dispose();
    _fulfillmentInstructions.dispose();
    _licenseKeyPrefix.dispose();
    _accessExpiresDays.dispose();
    _maxDownloads.dispose();
    _bookingDurationMinutes.dispose();
    _licenseKeys.dispose();
    _stock.dispose();
    super.dispose();
  }

  ProductInput? _buildInput() {
    final name = _name.text.trim();
    if (name.isEmpty) {
      setState(() => _error = 'Name is required.');
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

    final description = _description.text.trim();
    final category = _category.text.trim();
    final accessUrl = _accessUrl.text.trim();
    final serviceBookingUrl = _serviceBookingUrl.text.trim();
    final fulfillmentInstructions = _fulfillmentInstructions.text.trim();
    final licenseKeyPrefix = _licenseKeyPrefix.text.trim();
    final licenseKeys = _licenseKeys.text.trim();
    final accessExpiresRaw = _accessExpiresDays.text.trim();
    final maxDownloadsRaw = _maxDownloads.text.trim();
    final bookingDurationRaw = _bookingDurationMinutes.text.trim();
    final accessExpiresDays =
        accessExpiresRaw.isEmpty ? null : int.tryParse(accessExpiresRaw);

    if (accessExpiresRaw.isNotEmpty &&
        (accessExpiresDays == null || accessExpiresDays < 1)) {
      setState(() => _error = 'Enter a valid access expiry in days.');
      return null;
    }
    final maxDownloads =
        maxDownloadsRaw.isEmpty ? null : int.tryParse(maxDownloadsRaw);
    if (maxDownloadsRaw.isNotEmpty &&
        (maxDownloads == null || maxDownloads < 1)) {
      setState(() => _error = 'Download limit must be at least 1.');
      return null;
    }
    final bookingDurationMinutes =
        bookingDurationRaw.isEmpty ? null : int.tryParse(bookingDurationRaw);
    if (_bookable &&
        bookingDurationRaw.isNotEmpty &&
        (bookingDurationMinutes == null ||
            bookingDurationMinutes < 5 ||
            bookingDurationMinutes > 480)) {
      setState(
          () => _error = 'Booking duration must be between 5 and 480 minutes.');
      return null;
    }

    return ProductInput(
      name: name,
      description: description.isEmpty ? null : description,
      price: price,
      category: category.isEmpty ? null : category,
      productType: _productType,
      fulfillmentType: _fulfillmentType,
      trackInventory: _trackInventory,
      requiresDeliveryAddress: _requiresDeliveryAddress,
      accessUrl: accessUrl.isEmpty ? null : accessUrl,
      serviceBookingUrl: serviceBookingUrl.isEmpty ? null : serviceBookingUrl,
      fulfillmentInstructions:
          fulfillmentInstructions.isEmpty ? null : fulfillmentInstructions,
      licenseKeyMode: _licenseKeyMode,
      licenseKeyPrefix: licenseKeyPrefix.isEmpty ? null : licenseKeyPrefix,
      accessExpiresDays: accessExpiresDays,
      maxDownloads: maxDownloads,
      bookable: _bookable,
      bookingDurationMinutes: bookingDurationMinutes,
      licenseKeys: licenseKeys.isEmpty ? null : licenseKeys,
      stock: stock,
      status: widget.isEditing ? _status : null,
    );
  }

  Future<void> _pickImage() async {
    final picker = ImagePicker();
    final file = await picker.pickImage(
      source: ImageSource.gallery,
      maxWidth: 1600,
      imageQuality: 85,
    );
    if (file == null) return;
    setState(() => _imagePath = file.path);
  }

  Future<void> _pickDigitalFile() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const [
        'pdf',
        'epub',
        'txt',
        'csv',
        'zip',
        'doc',
        'docx'
      ],
    );
    final file = result?.files.single;
    if (file?.path == null) return;
    setState(() => _digitalFilePath = file!.path);
  }

  Future<void> _submit() async {
    final input = _buildInput();
    if (input == null) return;

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      if (widget.isEditing) {
        await _repo.updateProduct(
          widget.product!.id,
          input,
          imagePath: _imagePath,
          digitalFilePath: _digitalFilePath,
        );
      } else {
        await _repo.createProduct(
          input,
          imagePath: _imagePath,
          digitalFilePath: _digitalFilePath,
        );
      }
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final apiBase = context.watch<AppConfig>().apiBaseUrl;
    final existingUrl = Product.resolveImageUrl(widget.product?.image, apiBase);

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.isEditing ? 'Edit Product' : 'Add Product'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Center(
            child: InkWell(
              onTap: _saving ? null : _pickImage,
              borderRadius: BorderRadius.circular(16),
              child: Container(
                width: 120,
                height: 120,
                decoration: BoxDecoration(
                  color: AppColors.canvas,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: Colors.grey.shade300),
                ),
                clipBehavior: Clip.antiAlias,
                child: _imagePath != null
                    ? Image.file(File(_imagePath!), fit: BoxFit.cover)
                    : existingUrl != null
                        ? Image.network(
                            existingUrl,
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) =>
                                const _ImagePlaceholder(),
                          )
                        : const _ImagePlaceholder(),
              ),
            ),
          ),
          const SizedBox(height: 8),
          TextButton.icon(
            onPressed: _saving ? null : _pickImage,
            icon: const Icon(Icons.photo_library_outlined),
            label:
                Text(_imagePath == null ? 'Add product image' : 'Change image'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _name,
            textInputAction: TextInputAction.next,
            decoration: const InputDecoration(
              labelText: 'Name *',
              prefixIcon: Icon(Icons.inventory_2_outlined),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _description,
            textInputAction: TextInputAction.next,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'Description',
              prefixIcon: Icon(Icons.notes_outlined),
              alignLabelWithHint: true,
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
            controller: _category,
            textInputAction: TextInputAction.next,
            decoration: const InputDecoration(
              labelText: 'Category',
              prefixIcon: Icon(Icons.category_outlined),
            ),
          ),
          const SizedBox(height: 12),
          InputDecorator(
            decoration: const InputDecoration(
              labelText: 'Item type',
              prefixIcon: Icon(Icons.sell_outlined),
            ),
            child: DropdownButtonHideUnderline(
              child: DropdownButton<String>(
                value: _productType,
                isExpanded: true,
                items: const [
                  DropdownMenuItem(
                      value: 'physical', child: Text('Physical product')),
                  DropdownMenuItem(
                      value: 'digital', child: Text('Digital good')),
                  DropdownMenuItem(value: 'service', child: Text('Service')),
                ],
                onChanged: _saving
                    ? null
                    : (value) {
                        if (value == null) return;
                        setState(() => _productType = value);
                      },
              ),
            ),
          ),
          const SizedBox(height: 12),
          InputDecorator(
            decoration: const InputDecoration(
              labelText: 'Fulfillment',
              prefixIcon: Icon(Icons.link_outlined),
            ),
            child: DropdownButtonHideUnderline(
              child: DropdownButton<String>(
                value: _fulfillmentType,
                isExpanded: true,
                items: const [
                  DropdownMenuItem(
                      value: 'shipping', child: Text('Shipping / delivery')),
                  DropdownMenuItem(
                      value: 'download', child: Text('Download file')),
                  DropdownMenuItem(value: 'link', child: Text('Access link')),
                  DropdownMenuItem(
                      value: 'booking', child: Text('Booking link')),
                  DropdownMenuItem(
                      value: 'manual', child: Text('Manual instructions')),
                ],
                onChanged: _saving
                    ? null
                    : (value) {
                        if (value == null) return;
                        setState(() => _fulfillmentType = value);
                      },
              ),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _stock,
            keyboardType: TextInputType.number,
            textInputAction: TextInputAction.done,
            decoration: const InputDecoration(
              labelText: 'Stock *',
              prefixIcon: Icon(Icons.layers_outlined),
            ),
          ),
          const SizedBox(height: 8),
          SwitchListTile.adaptive(
            value: _trackInventory,
            onChanged: _saving
                ? null
                : (value) => setState(() => _trackInventory = value),
            title: const Text('Track inventory'),
            subtitle:
                const Text('Turn off for services or unlimited digital goods'),
            contentPadding: EdgeInsets.zero,
          ),
          SwitchListTile.adaptive(
            value: _requiresDeliveryAddress,
            onChanged: _saving
                ? null
                : (value) => setState(() => _requiresDeliveryAddress = value),
            title: const Text('Ask for delivery address'),
            subtitle: const Text('Turn off for downloads, links, or services'),
            contentPadding: EdgeInsets.zero,
          ),
          if (_productType != 'physical') ...[
            const SizedBox(height: 12),
            TextField(
              controller: _accessUrl,
              textInputAction: TextInputAction.next,
              decoration: const InputDecoration(
                labelText: 'Access link',
                prefixIcon: Icon(Icons.open_in_new_outlined),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _serviceBookingUrl,
              textInputAction: TextInputAction.next,
              decoration: const InputDecoration(
                labelText: 'Booking / secondary link',
                prefixIcon: Icon(Icons.event_available_outlined),
              ),
            ),
            const SizedBox(height: 12),
            if (_productType == 'digital') ...[
              TextField(
                controller: _maxDownloads,
                keyboardType: TextInputType.number,
                textInputAction: TextInputAction.next,
                decoration: const InputDecoration(
                  labelText: 'Maximum downloads',
                  hintText: 'Blank = unlimited',
                  prefixIcon: Icon(Icons.download_outlined),
                ),
              ),
              const SizedBox(height: 12),
            ],
            if (_productType == 'service' || _fulfillmentType == 'booking') ...[
              SwitchListTile.adaptive(
                value: _bookable,
                onChanged: _saving
                    ? null
                    : (value) => setState(() => _bookable = value),
                title: const Text('Enable customer bookings'),
                contentPadding: EdgeInsets.zero,
              ),
              if (_bookable) ...[
                const SizedBox(height: 8),
                TextField(
                  controller: _bookingDurationMinutes,
                  keyboardType: TextInputType.number,
                  textInputAction: TextInputAction.next,
                  decoration: const InputDecoration(
                    labelText: 'Meeting duration (minutes)',
                    hintText: 'Blank = booking default',
                    prefixIcon: Icon(Icons.schedule_outlined),
                  ),
                ),
              ],
              const SizedBox(height: 12),
            ],
            TextField(
              controller: _fulfillmentInstructions,
              textInputAction: TextInputAction.next,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Fulfillment instructions',
                prefixIcon: Icon(Icons.info_outline),
                alignLabelWithHint: true,
              ),
            ),
            const SizedBox(height: 8),
            TextButton.icon(
              onPressed: _saving ? null : _pickDigitalFile,
              icon: const Icon(Icons.attach_file),
              label: Text(
                _digitalFilePath == null
                    ? (widget.product?.digitalFileName != null
                        ? 'Replace digital file'
                        : 'Add digital file')
                    : 'Change digital file',
              ),
            ),
            if (_digitalFilePath != null)
              Text(
                _digitalFilePath!.split(RegExp(r'[\\/]')).last,
                style:
                    const TextStyle(color: AppColors.textMuted, fontSize: 12),
              )
            else if (widget.product?.digitalFileName != null)
              Text(
                'Current file: ${widget.product!.digitalFileName!} (private)',
                style:
                    const TextStyle(color: AppColors.textMuted, fontSize: 12),
              ),
            const SizedBox(height: 12),
            InputDecorator(
              decoration: const InputDecoration(
                labelText: 'License keys',
                prefixIcon: Icon(Icons.vpn_key_outlined),
              ),
              child: DropdownButtonHideUnderline(
                child: DropdownButton<String>(
                  value: _licenseKeyMode,
                  isExpanded: true,
                  items: const [
                    DropdownMenuItem(value: 'none', child: Text('None')),
                    DropdownMenuItem(
                        value: 'auto', child: Text('Auto-generate')),
                    DropdownMenuItem(
                        value: 'pool', child: Text('From key pool')),
                  ],
                  onChanged: _saving
                      ? null
                      : (value) {
                          if (value == null) return;
                          setState(() => _licenseKeyMode = value);
                        },
                ),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _accessExpiresDays,
              keyboardType: TextInputType.number,
              textInputAction: TextInputAction.next,
              decoration: const InputDecoration(
                labelText: 'Access expires (days)',
                hintText: 'Blank = no expiry',
                prefixIcon: Icon(Icons.timer_outlined),
              ),
            ),
            if (_licenseKeyMode != 'none') ...[
              const SizedBox(height: 12),
              TextField(
                controller: _licenseKeyPrefix,
                textInputAction: TextInputAction.next,
                decoration: const InputDecoration(
                  labelText: 'License key prefix',
                  prefixIcon: Icon(Icons.tag_outlined),
                ),
              ),
            ],
            if (_licenseKeyMode == 'pool') ...[
              const SizedBox(height: 12),
              TextField(
                controller: _licenseKeys,
                textInputAction: TextInputAction.newline,
                maxLines: 4,
                decoration: InputDecoration(
                  labelText: 'Import license keys',
                  hintText: 'One key per line',
                  helperText: widget.product != null
                      ? 'Available in pool: ${widget.product!.licenseKeysAvailable}'
                      : 'Keys are assigned after payment',
                  prefixIcon: const Icon(Icons.playlist_add_outlined),
                  alignLabelWithHint: true,
                ),
              ),
            ],
          ],
          if (widget.isEditing) ...[
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
                    DropdownMenuItem(
                        value: 'inactive', child: Text('Inactive')),
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
          ],
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(_error!, style: const TextStyle(color: Colors.redAccent)),
          ],
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: _saving ? null : _submit,
            icon: _saving
                ? const SizedBox(
                    width: 16,
                    height: 16,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : Icon(widget.isEditing ? Icons.save_outlined : Icons.add),
            label: Text(_saving
                ? 'Saving…'
                : widget.isEditing
                    ? 'Save Changes'
                    : 'Create Product'),
            style: FilledButton.styleFrom(
              backgroundColor: AppColors.primary,
              foregroundColor: Colors.white,
              minimumSize: const Size.fromHeight(48),
            ),
          ),
        ],
      ),
    );
  }
}

class _ImagePlaceholder extends StatelessWidget {
  const _ImagePlaceholder();

  @override
  Widget build(BuildContext context) {
    return const Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(Icons.add_a_photo_outlined, color: AppColors.primary),
        SizedBox(height: 6),
        Text('Photo',
            style: TextStyle(color: AppColors.textMuted, fontSize: 12)),
      ],
    );
  }
}
