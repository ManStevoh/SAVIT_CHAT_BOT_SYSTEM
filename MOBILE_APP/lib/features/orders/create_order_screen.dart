import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import '../../core/network/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/app_surface.dart';
import '../../shared/widgets/customer_avatar.dart';
import '../chats/chat_models.dart';
import '../chats/chat_repository.dart';
import '../products/product_models.dart';
import '../products/product_repository.dart';
import '../settings/company_settings_controller.dart';
import 'order_detail_screen.dart';
import 'order_models.dart';
import 'order_repository.dart';

/// Raise an order for a WhatsApp customer: Customer → Products → Review.
class CreateOrderScreen extends StatefulWidget {
  const CreateOrderScreen({
    super.key,
    this.initialChatId,
    this.initialCustomerName,
    this.initialCustomerPhone,
  });

  final String? initialChatId;
  final String? initialCustomerName;
  final String? initialCustomerPhone;

  @override
  State<CreateOrderScreen> createState() => _CreateOrderScreenState();
}

class _CreateOrderScreenState extends State<CreateOrderScreen> {
  final _pageController = PageController();
  final _customerSearch = TextEditingController();
  final _productSearch = TextEditingController();

  int _step = 0;
  bool _loadingChats = true;
  bool _loadingProducts = true;
  bool _submitting = false;
  String? _error;

  List<ChatSummary> _chats = [];
  List<Product> _products = [];
  ChatSummary? _selectedChat;
  final _lines = <_CartLine>[];
  bool _sendWhatsApp = true;
  OrderTotalsPreview? _totalsPreview;
  Timer? _previewTimer;

  @override
  void initState() {
    super.initState();
    if (widget.initialChatId != null && widget.initialChatId!.isNotEmpty) {
      _selectedChat = ChatSummary(
        id: widget.initialChatId!,
        customerName: widget.initialCustomerName?.trim().isNotEmpty == true
            ? widget.initialCustomerName!.trim()
            : 'Customer',
        customerPhone: widget.initialCustomerPhone ?? '',
        lastMessage: '',
        lastMessageTime: '',
        unreadCount: 0,
        status: 'active',
      );
      _step = 1;
    }
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadChats();
      _loadProducts();
      if (_selectedChat != null) {
        _pageController.jumpToPage(1);
      }
    });
  }

  @override
  void dispose() {
    _previewTimer?.cancel();
    _pageController.dispose();
    _customerSearch.dispose();
    _productSearch.dispose();
    super.dispose();
  }

  Future<void> _loadChats() async {
    setState(() {
      _loadingChats = true;
      _error = null;
    });
    try {
      final chats = await context.read<ChatRepository>().listChats();
      if (!mounted) return;
      setState(() {
        _chats = chats;
        _loadingChats = false;
        if (_selectedChat != null) {
          for (final chat in chats) {
            if (chat.id == _selectedChat!.id) {
              _selectedChat = chat;
              break;
            }
          }
        }
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loadingChats = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loadingChats = false;
      });
    }
  }

  Future<void> _loadProducts() async {
    setState(() => _loadingProducts = true);
    try {
      final products = await context.read<ProductRepository>().listProducts(
            status: 'active',
          );
      if (!mounted) return;
      setState(() {
        _products = products.where((p) => p.isActive).toList();
        _loadingProducts = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loadingProducts = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loadingProducts = false;
      });
    }
  }

  void _goTo(int step) {
    setState(() => _step = step);
    _pageController.animateToPage(
      step,
      duration: const Duration(milliseconds: 280),
      curve: Curves.easeOutCubic,
    );
  }

  void _selectChat(ChatSummary chat) {
    setState(() => _selectedChat = chat);
    _goTo(1);
  }

  void _addOrBumpProduct(Product product) {
    setState(() {
      final idx = _lines.indexWhere((l) => l.product.id == product.id);
      if (idx >= 0) {
        final current = _lines[idx];
        _lines[idx] = current.copyWith(quantity: current.quantity + 1);
      } else {
        _lines.add(_CartLine(product: product, quantity: 1));
      }
    });
    _schedulePreviewTotals();
  }

  void _setQty(int index, int qty) {
    if (qty < 1) {
      setState(() => _lines.removeAt(index));
      _schedulePreviewTotals();
      return;
    }
    setState(() => _lines[index] = _lines[index].copyWith(quantity: qty));
    _schedulePreviewTotals();
  }

  double get _catalogTotal =>
      _lines.fold<double>(0, (sum, line) => sum + line.lineTotal);

  double get _displayTotal => _totalsPreview?.total ?? _catalogTotal;

  void _schedulePreviewTotals() {
    _previewTimer?.cancel();
    if (_lines.isEmpty) {
      setState(() => _totalsPreview = null);
      return;
    }
    _previewTimer = Timer(const Duration(milliseconds: 280), () async {
      try {
        final preview = await context.read<OrderRepository>().previewTotals(
              items: _lines
                  .map(
                    (l) => CreateOrderLineItem(
                      productId: l.product.id,
                      name: l.product.name,
                      quantity: l.quantity,
                      price: l.product.price,
                    ),
                  )
                  .toList(),
            );
        if (!mounted) return;
        setState(() => _totalsPreview = preview);
      } catch (_) {
        // Fall back to catalog total in UI if preview fails.
      }
    });
  }

  bool get _canReview => _selectedChat != null && _lines.isNotEmpty;

  Future<void> _submit() async {
    final chat = _selectedChat;
    if (chat == null || _lines.isEmpty || _submitting) return;

    setState(() => _submitting = true);
    try {
      final result = await context.read<OrderRepository>().createOrder(
            chatId: chat.id,
            items: _lines
                .map(
                  (l) => CreateOrderLineItem(
                    productId: l.product.id,
                    name: l.product.name,
                    quantity: l.quantity,
                    price: l.product.price,
                  ),
                )
                .toList(),
            sendWhatsApp: _sendWhatsApp,
          );
      if (!mounted) return;

      final snack = result.whatsappSent
          ? 'Order ${result.orderNumber} created · invoice sent on WhatsApp'
          : (result.whatsappError != null && result.whatsappError!.isNotEmpty
              ? 'Order ${result.orderNumber} created · WhatsApp: ${result.whatsappError}'
              : 'Order ${result.orderNumber} created');
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(snack)));

      if (result.orderId.isNotEmpty) {
        await Navigator.of(context).pushReplacement(
          MaterialPageRoute<void>(
            builder: (_) => OrderDetailScreen(orderId: result.orderId),
          ),
        );
      } else {
        Navigator.of(context).pop(true);
      }
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text('$e')));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.canvas,
      appBar: AppBar(
        title: Text(
          'New order',
          style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
        ),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
            child: _StepHeader(step: _step),
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
              child: Text(
                _error!,
                style: GoogleFonts.manrope(
                  color: AppColors.accentRose,
                  fontSize: 13,
                ),
              ),
            ),
          Expanded(
            child: PageView(
              controller: _pageController,
              physics: const NeverScrollableScrollPhysics(),
              children: [
                _CustomerStep(
                  loading: _loadingChats,
                  chats: _filteredChats,
                  searchController: _customerSearch,
                  selectedId: _selectedChat?.id,
                  onSearchChanged: (_) => setState(() {}),
                  onRefresh: _loadChats,
                  onSelect: _selectChat,
                ),
                _ProductsStep(
                  loading: _loadingProducts,
                  products: _filteredProducts,
                  lines: _lines,
                  searchController: _productSearch,
                  onSearchChanged: (_) => setState(() {}),
                  onRefresh: _loadProducts,
                  onAdd: _addOrBumpProduct,
                  onQtyChanged: _setQty,
                  total: _displayTotal,
                  taxTotal: _totalsPreview?.taxTotal ?? 0,
                ),
                _ReviewStep(
                  chat: _selectedChat,
                  lines: _lines,
                  total: _displayTotal,
                  subtotal: _totalsPreview?.subtotal,
                  taxTotal: _totalsPreview?.taxTotal ?? 0,
                  taxBreakdown: _totalsPreview?.taxBreakdown ?? const [],
                  sendWhatsApp: _sendWhatsApp,
                  onSendWhatsAppChanged: (v) =>
                      setState(() => _sendWhatsApp = v),
                  onEditCustomer: () => _goTo(0),
                  onEditProducts: () => _goTo(1),
                ),
              ],
            ),
          ),
          _BottomBar(
            step: _step,
            canContinue: _step == 0
                ? _selectedChat != null
                : _step == 1
                    ? _canReview
                    : !_submitting && _canReview,
            submitting: _submitting,
            onBack: _step == 0 ? null : () => _goTo(_step - 1),
            onContinue: () {
              if (_step == 0 && _selectedChat != null) {
                _goTo(1);
              } else if (_step == 1 && _canReview) {
                _goTo(2);
              } else if (_step == 2) {
                _submit();
              }
            },
            continueLabel: _step == 2
                ? (_sendWhatsApp ? 'Create & send invoice' : 'Create order')
                : 'Continue',
          ),
        ],
      ),
    );
  }

  List<ChatSummary> get _filteredChats {
    final q = _customerSearch.text.trim().toLowerCase();
    if (q.isEmpty) return _chats;
    return _chats.where((c) {
      return c.customerName.toLowerCase().contains(q) ||
          c.customerPhone.toLowerCase().contains(q);
    }).toList();
  }

  List<Product> get _filteredProducts {
    final q = _productSearch.text.trim().toLowerCase();
    if (q.isEmpty) return _products;
    return _products.where((p) {
      return p.name.toLowerCase().contains(q) ||
          p.category.toLowerCase().contains(q);
    }).toList();
  }
}

class _CartLine {
  const _CartLine({required this.product, required this.quantity});

  final Product product;
  final int quantity;

  double get lineTotal => product.price * quantity;

  _CartLine copyWith({Product? product, int? quantity}) {
    return _CartLine(
      product: product ?? this.product,
      quantity: quantity ?? this.quantity,
    );
  }
}

class _StepHeader extends StatelessWidget {
  const _StepHeader({required this.step});

  final int step;

  @override
  Widget build(BuildContext context) {
    const labels = ['Customer', 'Products', 'Review'];
    return AppSurface(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
      color: AppColors.primarySoft,
      elevation: false,
      child: Row(
        children: [
          for (var i = 0; i < labels.length; i++) ...[
            if (i > 0)
              Expanded(
                child: Container(
                  height: 2,
                  margin: const EdgeInsets.symmetric(horizontal: 6),
                  color: i <= step ? AppColors.primary : AppColors.border,
                ),
              ),
            _StepDot(index: i, active: i <= step, label: labels[i]),
          ],
        ],
      ),
    );
  }
}

class _StepDot extends StatelessWidget {
  const _StepDot({
    required this.index,
    required this.active,
    required this.label,
  });

  final int index;
  final bool active;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Container(
          width: 28,
          height: 28,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: active ? AppColors.primary : AppColors.surface,
            shape: BoxShape.circle,
            border: Border.all(
              color: active ? AppColors.primary : AppColors.borderStrong,
            ),
          ),
          child: Text(
            '${index + 1}',
            style: GoogleFonts.manrope(
              fontWeight: FontWeight.w800,
              fontSize: 12,
              color: active ? Colors.white : AppColors.textMuted,
            ),
          ),
        ),
        const SizedBox(height: 4),
        Text(
          label,
          style: GoogleFonts.manrope(
            fontSize: 11,
            fontWeight: FontWeight.w700,
            color: active ? AppColors.primaryDark : AppColors.textMuted,
          ),
        ),
      ],
    );
  }
}

class _BottomBar extends StatelessWidget {
  const _BottomBar({
    required this.step,
    required this.canContinue,
    required this.submitting,
    required this.onContinue,
    required this.continueLabel,
    this.onBack,
  });

  final int step;
  final bool canContinue;
  final bool submitting;
  final VoidCallback onContinue;
  final String continueLabel;
  final VoidCallback? onBack;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
        decoration: const BoxDecoration(
          color: AppColors.surface,
          border: Border(top: BorderSide(color: AppColors.border)),
        ),
        child: Row(
          children: [
            if (onBack != null)
              OutlinedButton(
                onPressed: submitting ? null : onBack,
                child: const Text('Back'),
              ),
            if (onBack != null) const SizedBox(width: 10),
            Expanded(
              child: FilledButton(
                onPressed: canContinue && !submitting ? onContinue : null,
                style: FilledButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  minimumSize: const Size.fromHeight(48),
                ),
                child: submitting
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : Text(continueLabel),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CustomerStep extends StatelessWidget {
  const _CustomerStep({
    required this.loading,
    required this.chats,
    required this.searchController,
    required this.onSearchChanged,
    required this.onRefresh,
    required this.onSelect,
    this.selectedId,
  });

  final bool loading;
  final List<ChatSummary> chats;
  final TextEditingController searchController;
  final ValueChanged<String> onSearchChanged;
  final Future<void> Function() onRefresh;
  final ValueChanged<ChatSummary> onSelect;
  final String? selectedId;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Who is this order for?',
                style: GoogleFonts.manrope(
                  fontWeight: FontWeight.w800,
                  fontSize: 18,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Pick a WhatsApp chat. The invoice is sent to that number.',
                style: GoogleFonts.manrope(
                  color: AppColors.textMuted,
                  fontSize: 13,
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: searchController,
                onChanged: onSearchChanged,
                decoration: InputDecoration(
                  hintText: 'Search name or phone',
                  prefixIcon: const Icon(Icons.search),
                  filled: true,
                  fillColor: AppColors.surface,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(AppRadii.md),
                    borderSide: const BorderSide(color: AppColors.border),
                  ),
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: onRefresh,
            child: loading
                ? ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    children: const [
                      SizedBox(height: 120),
                      Center(child: CircularProgressIndicator()),
                    ],
                  )
                : chats.isEmpty
                    ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.all(24),
                        children: [
                          AppSurface(
                            padding: const EdgeInsets.all(24),
                            child: Column(
                              children: [
                                const AppIconChip(
                                  icon: Icons.chat_bubble_outline,
                                  color: AppColors.primary,
                                ),
                                const SizedBox(height: 12),
                                Text(
                                  'No chats yet',
                                  style: GoogleFonts.manrope(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  'Start a WhatsApp chat from Contacts, then raise an order here.',
                                  textAlign: TextAlign.center,
                                  style: GoogleFonts.manrope(
                                    color: AppColors.textMuted,
                                    fontSize: 13,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      )
                    : ListView.separated(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                        itemCount: chats.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 8),
                        itemBuilder: (context, index) {
                          final chat = chats[index];
                          final selected = chat.id == selectedId;
                          return AppSurface(
                            onTap: () => onSelect(chat),
                            padding: const EdgeInsets.all(14),
                            showBorder: selected,
                            borderColor: AppColors.primary,
                            color: selected
                                ? AppColors.primarySoft
                                : AppColors.surface,
                            child: Row(
                              children: [
                                CustomerAvatar(
                                  name: chat.customerName,
                                  size: 44,
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        chat.customerName,
                                        style: GoogleFonts.manrope(
                                          fontWeight: FontWeight.w800,
                                        ),
                                      ),
                                      const SizedBox(height: 2),
                                      Text(
                                        chat.customerPhone,
                                        style: GoogleFonts.manrope(
                                          color: AppColors.textMuted,
                                          fontSize: 13,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                Icon(
                                  selected
                                      ? Icons.check_circle
                                      : Icons.chevron_right_rounded,
                                  color: selected
                                      ? AppColors.primary
                                      : AppColors.textMuted,
                                ),
                              ],
                            ),
                          );
                        },
                      ),
          ),
        ),
      ],
    );
  }
}

class _ProductsStep extends StatelessWidget {
  const _ProductsStep({
    required this.loading,
    required this.products,
    required this.lines,
    required this.searchController,
    required this.onSearchChanged,
    required this.onRefresh,
    required this.onAdd,
    required this.onQtyChanged,
    required this.total,
    this.taxTotal = 0,
  });

  final bool loading;
  final List<Product> products;
  final List<_CartLine> lines;
  final TextEditingController searchController;
  final ValueChanged<String> onSearchChanged;
  final Future<void> Function() onRefresh;
  final ValueChanged<Product> onAdd;
  final void Function(int index, int qty) onQtyChanged;
  final double total;
  final double taxTotal;

  @override
  Widget build(BuildContext context) {
    final formatMoney = context.watch<CompanySettingsController>().formatMoney;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Add products',
                style: GoogleFonts.manrope(
                  fontWeight: FontWeight.w800,
                  fontSize: 18,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Tap a product to add it. Adjust quantities in your cart.',
                style: GoogleFonts.manrope(
                  color: AppColors.textMuted,
                  fontSize: 13,
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: searchController,
                onChanged: onSearchChanged,
                decoration: InputDecoration(
                  hintText: 'Search catalog',
                  prefixIcon: const Icon(Icons.search),
                  filled: true,
                  fillColor: AppColors.surface,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(AppRadii.md),
                    borderSide: const BorderSide(color: AppColors.border),
                  ),
                ),
              ),
            ],
          ),
        ),
        if (lines.isNotEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: AppSurface(
              padding: const EdgeInsets.all(14),
              color: AppColors.primarySoft,
              elevation: false,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(
                        'Cart · ${lines.length} item${lines.length == 1 ? '' : 's'}',
                        style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
                      ),
                      const Spacer(),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(
                            formatMoney(total),
                            style: GoogleFonts.manrope(
                              fontWeight: FontWeight.w800,
                              color: AppColors.primaryDark,
                            ),
                          ),
                          if (taxTotal > 0)
                            Text(
                              'incl. tax ${formatMoney(taxTotal)}',
                              style: GoogleFonts.manrope(
                                fontSize: 11,
                                color: AppColors.textMuted,
                              ),
                            ),
                        ],
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  for (var i = 0; i < lines.length; i++)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Row(
                        children: [
                          Expanded(
                            child: Text(
                              lines[i].product.name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: GoogleFonts.manrope(
                                fontWeight: FontWeight.w600,
                                fontSize: 13,
                              ),
                            ),
                          ),
                          IconButton(
                            visualDensity: VisualDensity.compact,
                            onPressed: () =>
                                onQtyChanged(i, lines[i].quantity - 1),
                            icon: const Icon(Icons.remove_circle_outline),
                          ),
                          Text(
                            '${lines[i].quantity}',
                            style: GoogleFonts.manrope(
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          IconButton(
                            visualDensity: VisualDensity.compact,
                            onPressed: () =>
                                onQtyChanged(i, lines[i].quantity + 1),
                            icon: const Icon(Icons.add_circle_outline),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
          ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: onRefresh,
            child: loading
                ? ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    children: const [
                      SizedBox(height: 80),
                      Center(child: CircularProgressIndicator()),
                    ],
                  )
                : products.isEmpty
                    ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.all(24),
                        children: [
                          Text(
                            'No active products. Add products in the catalog first.',
                            textAlign: TextAlign.center,
                            style: GoogleFonts.manrope(
                              color: AppColors.textMuted,
                            ),
                          ),
                        ],
                      )
                    : ListView.separated(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                        itemCount: products.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 8),
                        itemBuilder: (context, index) {
                          final product = products[index];
                          final inCart = lines.any(
                            (l) => l.product.id == product.id,
                          );
                          return AppSurface(
                            onTap: () => onAdd(product),
                            padding: const EdgeInsets.all(14),
                            child: Row(
                              children: [
                                Container(
                                  width: 44,
                                  height: 44,
                                  alignment: Alignment.center,
                                  decoration: BoxDecoration(
                                    color: AppColors.canvasDeep,
                                    borderRadius:
                                        BorderRadius.circular(AppRadii.sm),
                                  ),
                                  child: Icon(
                                    inCart
                                        ? Icons.check_rounded
                                        : Icons.add_rounded,
                                    color: AppColors.primary,
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        product.name,
                                        style: GoogleFonts.manrope(
                                          fontWeight: FontWeight.w800,
                                        ),
                                      ),
                                      if (product.category.isNotEmpty)
                                        Text(
                                          product.category,
                                          style: GoogleFonts.manrope(
                                            color: AppColors.textMuted,
                                            fontSize: 12,
                                          ),
                                        ),
                                    ],
                                  ),
                                ),
                                Text(
                                  formatMoney(product.price),
                                  style: GoogleFonts.manrope(
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.primaryDark,
                                  ),
                                ),
                              ],
                            ),
                          );
                        },
                      ),
          ),
        ),
      ],
    );
  }
}

class _ReviewStep extends StatelessWidget {
  const _ReviewStep({
    required this.chat,
    required this.lines,
    required this.total,
    this.subtotal,
    this.taxTotal = 0,
    this.taxBreakdown = const [],
    required this.sendWhatsApp,
    required this.onSendWhatsAppChanged,
    required this.onEditCustomer,
    required this.onEditProducts,
  });

  final ChatSummary? chat;
  final List<_CartLine> lines;
  final double total;
  final double? subtotal;
  final double taxTotal;
  final List<Map<String, dynamic>> taxBreakdown;
  final bool sendWhatsApp;
  final ValueChanged<bool> onSendWhatsAppChanged;
  final VoidCallback onEditCustomer;
  final VoidCallback onEditProducts;

  @override
  Widget build(BuildContext context) {
    final formatMoney = context.watch<CompanySettingsController>().formatMoney;
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      children: [
        Text(
          'Review & send',
          style: GoogleFonts.manrope(
            fontWeight: FontWeight.w800,
            fontSize: 18,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          'Confirm details, then create the order. Invoice goes out on WhatsApp when enabled.',
          style: GoogleFonts.manrope(
            color: AppColors.textMuted,
            fontSize: 13,
          ),
        ),
        const SizedBox(height: 16),
        AppSurface(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Text(
                    'Customer',
                    style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
                  ),
                  const Spacer(),
                  TextButton(onPressed: onEditCustomer, child: const Text('Edit')),
                ],
              ),
              const SizedBox(height: 8),
              if (chat != null)
                Row(
                  children: [
                    CustomerAvatar(name: chat!.customerName, size: 40),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            chat!.customerName,
                            style: GoogleFonts.manrope(
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          Text(
                            chat!.customerPhone,
                            style: GoogleFonts.manrope(
                              color: AppColors.textMuted,
                              fontSize: 13,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        AppSurface(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Text(
                    'Items',
                    style: GoogleFonts.manrope(fontWeight: FontWeight.w800),
                  ),
                  const Spacer(),
                  TextButton(onPressed: onEditProducts, child: const Text('Edit')),
                ],
              ),
              const SizedBox(height: 8),
              for (final line in lines) ...[
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(
                        '${line.quantity} × ${line.product.name}',
                        style: GoogleFonts.manrope(fontWeight: FontWeight.w600),
                      ),
                    ),
                    Text(
                      formatMoney(line.lineTotal),
                      style: GoogleFonts.manrope(fontWeight: FontWeight.w700),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
              ],
              const Divider(),
              if (taxTotal > 0) ...[
                Row(
                  children: [
                    Text(
                      'Subtotal',
                      style: GoogleFonts.manrope(color: AppColors.textMuted),
                    ),
                    const Spacer(),
                    Text(
                      formatMoney(subtotal ?? (total - taxTotal)),
                      style: GoogleFonts.manrope(fontWeight: FontWeight.w600),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                if (taxBreakdown.isNotEmpty)
                  ...taxBreakdown.map((row) {
                    final label =
                        (row['code'] ?? row['name'] ?? 'Tax').toString();
                    final rate = row['rate'];
                    final amount = (row['amount'] as num?)?.toDouble() ?? 0;
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 6),
                      child: Row(
                        children: [
                          Text(
                            rate != null ? '$label ($rate%)' : label,
                            style: GoogleFonts.manrope(
                              color: AppColors.textMuted,
                            ),
                          ),
                          const Spacer(),
                          Text(
                            formatMoney(amount),
                            style: GoogleFonts.manrope(
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    );
                  })
                else
                  Row(
                    children: [
                      Text(
                        'Tax',
                        style: GoogleFonts.manrope(color: AppColors.textMuted),
                      ),
                      const Spacer(),
                      Text(
                        formatMoney(taxTotal),
                        style: GoogleFonts.manrope(fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                const SizedBox(height: 6),
              ],
              Row(
                children: [
                  Text(
                    'Total',
                    style: GoogleFonts.manrope(
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                    ),
                  ),
                  const Spacer(),
                  Text(
                    formatMoney(total),
                    style: GoogleFonts.manrope(
                      fontWeight: FontWeight.w800,
                      fontSize: 20,
                      color: AppColors.primary,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        AppSurface(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          child: SwitchListTile(
            value: sendWhatsApp,
            onChanged: onSendWhatsAppChanged,
            activeColor: AppColors.primary,
            title: Text(
              'Send invoice on WhatsApp',
              style: GoogleFonts.manrope(fontWeight: FontWeight.w700),
            ),
            subtitle: Text(
              'Includes receipt link and pay options',
              style: GoogleFonts.manrope(
                color: AppColors.textMuted,
                fontSize: 12,
              ),
            ),
          ),
        ),
      ],
    );
  }
}
