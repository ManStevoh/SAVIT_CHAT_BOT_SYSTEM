import '../../core/utils/json_utils.dart';

class AnalyticsSnapshot {
  const AnalyticsSnapshot({
    required this.period,
    required this.totalMessages,
    required this.totalOrders,
    required this.totalCustomers,
    required this.totalRevenue,
    this.messagesChange = 0,
    this.ordersChange = 0,
    this.customersChange = 0,
    this.revenueChange = 0,
    this.topProducts = const [],
  });

  final String period;
  final int totalMessages;
  final int totalOrders;
  final int totalCustomers;
  final double totalRevenue;
  final double messagesChange;
  final double ordersChange;
  final double customersChange;
  final double revenueChange;
  final List<AnalyticsProduct> topProducts;

  factory AnalyticsSnapshot.fromJson(Map<String, dynamic> json, String period) {
    final products = (json['topProducts'] as List?) ?? [];
    return AnalyticsSnapshot(
      period: period,
      totalMessages: (json['totalMessages'] as num?)?.toInt() ?? 0,
      totalOrders: (json['totalOrders'] as num?)?.toInt() ?? 0,
      totalCustomers: (json['totalCustomers'] as num?)?.toInt() ?? 0,
      totalRevenue: (json['totalRevenue'] as num?)?.toDouble() ?? 0,
      messagesChange: (json['messagesChange'] as num?)?.toDouble() ?? 0,
      ordersChange: (json['ordersChange'] as num?)?.toDouble() ?? 0,
      customersChange: (json['customersChange'] as num?)?.toDouble() ?? 0,
      revenueChange: (json['revenueChange'] as num?)?.toDouble() ?? 0,
      topProducts: products
          .whereType<Map>()
          .map((e) => AnalyticsProduct.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
    );
  }
}

class AnalyticsProduct {
  const AnalyticsProduct({
    required this.name,
    required this.sales,
    required this.revenue,
  });

  final String name;
  final int sales;
  final double revenue;

  factory AnalyticsProduct.fromJson(Map<String, dynamic> json) {
    return AnalyticsProduct(
      name: jsonString(json['name'], 'Product'),
      sales: (json['sales'] as num?)?.toInt() ?? 0,
      revenue: (json['revenue'] as num?)?.toDouble() ?? 0,
    );
  }
}

class SubscriptionOverview {
  const SubscriptionOverview({
    required this.plan,
    required this.planName,
    required this.status,
    required this.endDate,
    required this.daysRemaining,
    required this.isExpiringSoon,
    required this.accessEndsLabel,
    this.amount = 0,
    this.billingCycle,
    this.paymentMethod,
    this.currency,
  });

  final String plan;
  final String planName;
  final String status;
  final String endDate;
  final int daysRemaining;
  final bool isExpiringSoon;
  final String accessEndsLabel;
  final double amount;
  final String? billingCycle;
  final String? paymentMethod;
  final String? currency;

  factory SubscriptionOverview.fromJson(Map<String, dynamic> json) {
    return SubscriptionOverview(
      plan: jsonString(json['plan'], 'starter'),
      planName: jsonString(json['planName'], 'Starter'),
      status: jsonString(json['status'], 'trial'),
      endDate: jsonString(json['endDate']),
      daysRemaining: (json['daysRemaining'] as num?)?.toInt() ?? 0,
      isExpiringSoon: json['isExpiringSoon'] == true,
      accessEndsLabel: jsonString(json['accessEndsLabel'], 'Renews on'),
      amount: (json['amount'] as num?)?.toDouble() ?? 0,
      billingCycle: jsonStringOrNull(json['billingCycle']),
      paymentMethod: jsonStringOrNull(json['paymentMethod']),
      currency: jsonStringOrNull(json['currency']),
    );
  }
}

class UsageMeter {
  const UsageMeter({
    required this.name,
    required this.used,
    this.limit,
  });

  final String name;
  final int used;
  final int? limit;

  factory UsageMeter.fromJson(Map<String, dynamic> json) {
    return UsageMeter(
      name: jsonString(json['name'], 'Usage'),
      used: (json['used'] as num?)?.toInt() ?? 0,
      limit: (json['limit'] as num?)?.toInt(),
    );
  }

  bool get unlimited => limit == null;
  double get progress {
    if (limit == null || limit! <= 0) return 0;
    return (used / limit!).clamp(0, 1).toDouble();
  }
}

class BillingInvoice {
  const BillingInvoice({
    required this.id,
    required this.date,
    required this.amount,
    required this.status,
    this.gateway,
  });

  final String id;
  final String date;
  final String amount;
  final String status;
  final String? gateway;

  factory BillingInvoice.fromJson(Map<String, dynamic> json) {
    return BillingInvoice(
      id: jsonString(json['id']),
      date: jsonString(json['date']),
      amount: jsonString(json['amount']),
      status: jsonString(json['status'], 'paid'),
      gateway: jsonStringOrNull(json['gateway']),
    );
  }
}

class DeliveryZone {
  const DeliveryZone({
    required this.id,
    required this.name,
    required this.fee,
    this.minOrderAmount,
    this.keywords = const [],
    this.isActive = true,
  });

  final String id;
  final String name;
  final double fee;
  final double? minOrderAmount;
  final List<String> keywords;
  final bool isActive;

  factory DeliveryZone.fromJson(Map<String, dynamic> json) {
    final keys = json['keywords'];
    return DeliveryZone(
      id: jsonString(json['id']),
      name: jsonString(json['name'], 'Zone'),
      fee: (json['fee'] as num?)?.toDouble() ?? 0,
      minOrderAmount: (json['minOrderAmount'] as num?)?.toDouble(),
      keywords: keys is List ? keys.map((e) => e.toString()).where((e) => e.isNotEmpty).toList() : const [],
      isActive: json['isActive'] != false,
    );
  }
}

class DineInTable {
  const DineInTable({
    required this.id,
    required this.name,
    this.code,
    this.seats,
    this.isActive = true,
    this.orderUrl,
    this.whatsappOrderUrl,
  });

  final String id;
  final String name;
  final String? code;
  final int? seats;
  final bool isActive;
  final String? orderUrl;
  final String? whatsappOrderUrl;

  factory DineInTable.fromJson(Map<String, dynamic> json) {
    return DineInTable(
      id: jsonString(json['id']),
      name: jsonString(json['name'], 'Table'),
      code: jsonStringOrNull(json['code']),
      seats: (json['seats'] as num?)?.toInt(),
      isActive: json['isActive'] != false,
      orderUrl: jsonStringOrNull(json['orderUrl']),
      whatsappOrderUrl: jsonStringOrNull(json['whatsappOrderUrl']),
    );
  }
}

class StoreCoupon {
  const StoreCoupon({
    required this.id,
    required this.code,
    required this.type,
    required this.value,
    this.minOrder,
    this.maxRedemptions,
    this.redeemedCount = 0,
    this.isActive = true,
    this.isCurrentlyValid = true,
  });

  final String id;
  final String code;
  final String type;
  final double value;
  final double? minOrder;
  final int? maxRedemptions;
  final int redeemedCount;
  final bool isActive;
  final bool isCurrentlyValid;

  factory StoreCoupon.fromJson(Map<String, dynamic> json) {
    return StoreCoupon(
      id: jsonString(json['id']),
      code: jsonString(json['code']),
      type: jsonString(json['type'], 'percent'),
      value: (json['value'] as num?)?.toDouble() ?? 0,
      minOrder: (json['minOrder'] as num?)?.toDouble(),
      maxRedemptions: (json['maxRedemptions'] as num?)?.toInt(),
      redeemedCount: (json['redeemedCount'] as num?)?.toInt() ?? 0,
      isActive: json['isActive'] != false,
      isCurrentlyValid: json['isCurrentlyValid'] == true,
    );
  }

  String get label =>
      type == 'percent' ? '${value.toStringAsFixed(0)}% off' : '${value.toStringAsFixed(0)} off';
}

class CampaignSummary {
  const CampaignSummary({
    required this.id,
    required this.name,
    required this.status,
    required this.segment,
    this.caption,
    this.sentCount = 0,
    this.totalRecipients = 0,
    this.failedCount = 0,
  });

  final String id;
  final String name;
  final String status;
  final String segment;
  final String? caption;
  final int sentCount;
  final int totalRecipients;
  final int failedCount;

  factory CampaignSummary.fromJson(Map<String, dynamic> json) {
    return CampaignSummary(
      id: jsonString(json['id']),
      name: jsonString(json['name'], 'Campaign'),
      status: jsonString(json['status'], 'draft'),
      segment: jsonString(json['segment'], 'all'),
      caption: jsonStringOrNull(json['caption']),
      sentCount: (json['sentCount'] as num?)?.toInt() ?? 0,
      totalRecipients: (json['totalRecipients'] as num?)?.toInt() ?? 0,
      failedCount: (json['failedCount'] as num?)?.toInt() ?? 0,
    );
  }
}

class TeamMember {
  const TeamMember({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    required this.status,
  });

  final String id;
  final String name;
  final String email;
  final String role;
  final String status;

  factory TeamMember.fromJson(Map<String, dynamic> json) {
    return TeamMember(
      id: jsonString(json['id']),
      name: jsonString(json['name']),
      email: jsonString(json['email']),
      role: jsonString(json['role'], 'Agent'),
      status: jsonString(json['status'], 'active'),
    );
  }
}

class WhatsAppStatus {
  const WhatsAppStatus({
    required this.connected,
    this.displayPhoneNumber,
    this.qualityRating,
    this.webhookSubscribed = false,
    this.connectedVia,
    this.onboardingError,
  });

  final bool connected;
  final String? displayPhoneNumber;
  final String? qualityRating;
  final bool webhookSubscribed;
  final String? connectedVia;
  final String? onboardingError;

  factory WhatsAppStatus.fromJson(Map<String, dynamic> json) {
    return WhatsAppStatus(
      connected: json['connected'] == true,
      displayPhoneNumber: jsonStringOrNull(json['displayPhoneNumber']),
      qualityRating: jsonStringOrNull(json['qualityRating']),
      webhookSubscribed: json['webhookSubscribed'] == true,
      connectedVia: jsonStringOrNull(json['connectedVia']),
      onboardingError: jsonStringOrNull(json['onboardingError']),
    );
  }
}

class BusinessProfile {
  const BusinessProfile({
    required this.companyName,
    required this.businessMode,
    required this.enableBookings,
    required this.enableDineIn,
    required this.timezone,
    required this.phone,
  });

  final String companyName;
  final String businessMode;
  final bool enableBookings;
  final bool enableDineIn;
  final String timezone;
  final String phone;

  factory BusinessProfile.fromJson(Map<String, dynamic> json) {
    return BusinessProfile(
      companyName: jsonString(json['companyName']),
      businessMode: jsonString(json['businessMode'], 'hybrid'),
      enableBookings: json['enableBookings'] != false,
      enableDineIn: json['enableDineIn'] == true || json['dineInEnabled'] == true,
      timezone: jsonString(json['timezone'], 'Africa/Nairobi'),
      phone: jsonString(json['phone']),
    );
  }
}

class PaymentCollectionSettings {
  const PaymentCollectionSettings({
    required this.collectEnabled,
    required this.acceptMpesa,
    required this.acceptPaystack,
    required this.acceptStripe,
    required this.acceptCod,
    required this.acceptBank,
  });

  final bool collectEnabled;
  final bool acceptMpesa;
  final bool acceptPaystack;
  final bool acceptStripe;
  final bool acceptCod;
  final bool acceptBank;

  factory PaymentCollectionSettings.fromJson(Map<String, dynamic> json) {
    return PaymentCollectionSettings(
      collectEnabled: json['ordersCollectPaymentEnabled'] != false,
      acceptMpesa: json['ordersAcceptMpesa'] == true,
      acceptPaystack: json['ordersAcceptPaystack'] == true,
      acceptStripe: json['ordersAcceptStripe'] == true,
      acceptCod: json['ordersAcceptCod'] == true,
      acceptBank: json['ordersAcceptBankTransfer'] == true,
    );
  }
}
