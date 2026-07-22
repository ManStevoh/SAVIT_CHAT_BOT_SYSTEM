import 'package:dio/dio.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';

class CustomerContact {
  const CustomerContact({
    required this.name,
    required this.phone,
    required this.totalOrders,
    required this.totalSpent,
    this.lastOrderDate = '',
  });

  final String name;
  final String phone;
  final int totalOrders;
  final double totalSpent;
  final String lastOrderDate;

  factory CustomerContact.fromJson(Map<String, dynamic> json) {
    return CustomerContact(
      name: (json['name'] ?? 'Customer') as String,
      phone: (json['phone'] ?? '') as String,
      totalOrders: (json['totalOrders'] as num?)?.toInt() ?? 0,
      totalSpent: (json['totalSpent'] as num?)?.toDouble() ?? 0,
      lastOrderDate: (json['lastOrderDate'] ?? '') as String,
    );
  }
}

class ContactDirectoryItem {
  const ContactDirectoryItem({
    required this.name,
    required this.phone,
    this.chatId,
    this.totalOrders = 0,
    this.subtitle,
  });

  final String name;
  final String phone;
  final String? chatId;
  final int totalOrders;
  final String? subtitle;

  bool get hasOpenChat => chatId != null && chatId!.isNotEmpty;
}

class CustomerRepository {
  CustomerRepository(this._api);

  final ApiClient _api;

  Future<List<CustomerContact>> listCustomers({String? search, int limit = 100}) async {
    try {
      final response = await _api.dio.get(
        '/company/customers',
        queryParameters: {
          'limit': limit,
          if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
        },
      );
      final data = response.data;
      if (data is! Map) return [];
      final customers = data['customers'];
      if (customers is! List) return [];
      return customers
          .whereType<Map>()
          .map((e) => CustomerContact.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
