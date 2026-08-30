import 'package:flutter_test/flutter_test.dart';

import 'package:essem_mobile/core/auth/auth_user.dart';
import 'package:essem_mobile/core/utils/phone_utils.dart';
import 'package:essem_mobile/core/config/app_config.dart';
import 'package:essem_mobile/features/chats/chat_models.dart';
import 'package:essem_mobile/features/companion/companion_models.dart';
import 'package:essem_mobile/features/faqs/faq_models.dart';
import 'package:essem_mobile/features/growth/growth_models.dart';
import 'package:essem_mobile/features/orders/order_models.dart';
import 'package:essem_mobile/features/products/product_models.dart';

void main() {
  test('AppConfig derives dashboard URLs from the API root', () {
    const config = AppConfig(apiBaseUrl: 'https://relayiq.app/api');
    expect(config.webOrigin, 'https://relayiq.app');
    expect(config.dashboardUrl('/dashboard/subscription'), 'https://relayiq.app/dashboard/subscription');
  });

  test('AuthUser parses Laravel login user shape', () {
    final user = AuthUser.fromJson({
      'id': 7,
      'name': 'Demo Owner',
      'email': 'demo1@company.local',
      'phone': '+254700000000',
      'role': 'company_owner',
      'companyId': '3',
      'companyName': 'Demo Co',
    });

    expect(user.id, '7');
    expect(user.companyId, '3');
    expect(user.companyName, 'Demo Co');
    expect(user.hasCompany, isTrue);
    expect(user.isPlatformAdminOnly, isFalse);
    expect(user.toJson()['email'], 'demo1@company.local');
  });

  test('platform admin without company is admin-only', () {
    final admin = AuthUser.fromJson({
      'id': 1,
      'name': 'Admin',
      'email': 'admin@platform.local',
      'role': 'admin',
      'companyId': null,
    });
    expect(admin.isPlatformAdmin, isTrue);
    expect(admin.hasCompany, isFalse);
    expect(admin.isPlatformAdminOnly, isTrue);
  });

  test('ChatSummary and ChatMessage parse company chat payloads', () {
    final chat = ChatSummary.fromJson({
      'id': 3,
      'customerName': 'Jane',
      'customerPhone': '254700111222',
      'lastMessage': 'Hello',
      'lastMessageTime': '2 hours ago',
      'unreadCount': 2,
      'status': 'active',
    });
    final message = ChatMessage.fromJson({
      'id': 9,
      'content': 'Hi',
      'sender': 'customer',
      'timestamp': '10:00 AM',
      'status': 'sent',
    });

    expect(chat.id, '3');
    expect(chat.unreadCount, 2);
    expect(message.isIncoming, isTrue);
  });

  test('normalizePhoneDigits strips formatting', () {
    expect(normalizePhoneDigits('+254 700-111-222'), '254700111222');
    expect(normalizePhoneDigits('(0700) 111 222'), '0700111222');
  });

  test('phoneMergeKey unifies local and international Kenya numbers', () {
    expect(phoneMergeKey('0700111222'), '254700111222');
    expect(phoneMergeKey('+254 700 111 222'), '254700111222');
    expect(phoneMergeKey('700111222'), '254700111222');
    expect(phoneMergeKey('00254700111222'), '254700111222');
  });

  test('Product and notification tolerate non-string JSON values', () {
    final product = Product.fromJson({
      'id': 5,
      'name': 42,
      'description': null,
      'price': 99.9,
      'category': true,
      'image': null,
      'stock': 4,
      'status': 'active',
      'createdAt': 2026,
      'images': [],
      'variants': [],
    });
    expect(product.name, '42');
    expect(product.description, '');
    expect(product.category, 'true');
    expect(product.price, 99.9);
  });

  test('Order and Product and Faq parse list payloads', () {
    final order = Order.fromJson({
      'id': '1',
      'orderNumber': 'ORD-1',
      'customerName': 'A',
      'customerPhone': '2547',
      'products': [
        {'id': '1', 'name': 'Item', 'quantity': 2, 'price': 10.5},
      ],
      'total': 21,
      'status': 'pending',
      'paymentStatus': 'pending',
      'createdAt': '2026-01-01T00:00:00Z',
      'updatedAt': '2026-01-01T00:00:00Z',
    });
    final product = Product.fromJson({
      'id': '5',
      'name': 'Bag',
      'description': 'Nice',
      'price': 99.9,
      'category': 'General',
      'image': null,
      'stock': 4,
      'status': 'active',
      'createdAt': '2026-01-01',
      'images': [],
      'variants': [],
    });
    final faq = Faq.fromJson({
      'id': '2',
      'question': 'Hours?',
      'answer': '9-5',
      'category': 'general',
      'keywords': ['hours', 'time'],
      'isActive': true,
      'usageCount': 3,
      'createdAt': '2026-01-01',
    });

    expect(order.products.first.lineTotal, 21);
    expect(product.isActive, isTrue);
    expect(faq.keywords, ['hours', 'time']);
  });

  test('GrowthPost workflow flags', () {
    final draft = GrowthPost.fromJson({
      'id': '1',
      'platform': 'whatsapp',
      'content': 'Hi',
      'status': 'draft',
      'approvedAt': null,
    });
    expect(draft.canApprove, isTrue);
    expect(draft.canPublish, isFalse);

    final approved = GrowthPost.fromJson({
      'id': '2',
      'platform': 'whatsapp',
      'content': 'Hi',
      'status': 'draft',
      'approvedAt': '2026-01-01T00:00:00Z',
    });
    expect(approved.canApprove, isFalse);
    expect(approved.canPublish, isTrue);

    final ig = GrowthPost.fromJson({
      'id': '3',
      'platform': 'instagram',
      'content': 'Shot',
      'status': 'draft',
      'approvedAt': '2026-01-01T00:00:00Z',
      'mediaUrls': [],
    });
    expect(ig.needsMediaForPublish, isTrue);
    expect(ig.canPublish, isFalse);
  });

  test('companion billing and commerce models parse API shapes', () {
    final analytics = AnalyticsSnapshot.fromJson({
      'totalMessages': 12,
      'totalOrders': 3,
      'totalCustomers': 8,
      'totalRevenue': 1499.5,
      'messagesChange': 4.2,
      'topProducts': [
        {'name': 'Bag', 'sales': 2, 'revenue': 200},
      ],
    }, '7d');
    expect(analytics.totalRevenue, 1499.5);
    expect(analytics.topProducts.first.name, 'Bag');

    final sub = SubscriptionOverview.fromJson({
      'plan': 'growth',
      'planName': 'Growth',
      'status': 'active',
      'endDate': '2026-09-28',
      'daysRemaining': 12,
      'isExpiringSoon': false,
      'accessEndsLabel': 'Renews on',
    });
    expect(sub.planName, 'Growth');

    final usage = UsageMeter.fromJson({'name': 'Messages', 'used': 40, 'limit': 100});
    expect(usage.progress, closeTo(0.4, 0.001));

    final zone = DeliveryZone.fromJson({
      'id': 4,
      'name': 'Nairobi',
      'fee': 250,
      'keywords': ['westlands', 'cbd'],
    });
    expect(zone.id, '4');
    expect(zone.keywords, ['westlands', 'cbd']);

    final coupon = StoreCoupon.fromJson({
      'id': 9,
      'code': 'SAVE50',
      'type': 'percent',
      'value': 50,
      'isCurrentlyValid': true,
    });
    expect(coupon.label, '50% off');

    final campaign = CampaignSummary.fromJson({
      'id': 2,
      'name': 'August blast',
      'status': 'draft',
      'segment': 'all',
      'sentCount': 0,
      'totalRecipients': 12,
    });
    expect(campaign.status, 'draft');

    final wa = WhatsAppStatus.fromJson({
      'connected': true,
      'displayPhoneNumber': '+254700000000',
      'webhookSubscribed': true,
    });
    expect(wa.connected, isTrue);

    final payments = PaymentCollectionSettings.fromJson({
      'ordersCollectPaymentEnabled': true,
      'ordersAcceptMpesa': true,
      'ordersAcceptCod': true,
    });
    expect(payments.acceptMpesa, isTrue);
    expect(payments.acceptStripe, isFalse);
  });

  test('chat messages keep learning feedback', () {
    final message = ChatMessage.fromJson({
      'id': 11,
      'content': 'Karibu',
      'sender': 'bot',
      'timestamp': '10:01 AM',
      'learningFeedback': 1,
    });
    expect(message.isBot, isTrue);
    expect(message.copyWith(learningFeedback: -1).learningFeedback, -1);
  });
}
