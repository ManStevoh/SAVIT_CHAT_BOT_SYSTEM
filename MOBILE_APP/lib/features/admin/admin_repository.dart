import 'package:dio/dio.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';
import 'admin_models.dart';

class AdminRepository {
  AdminRepository(this._api);

  final ApiClient _api;

  Future<AdminDashboard> loadDashboard({
    String? search,
    int companyLimit = 10,
  }) async {
    try {
      final results = await Future.wait([
        _api.dio.get('/admin/overview'),
        _api.dio.get('/admin/system-health'),
        _api.dio.get(
          '/admin/companies',
          queryParameters: search != null && search.isNotEmpty
              ? {'search': search}
              : null,
        ),
      ]);

      final overviewRaw = results[0].data;
      final healthRaw = results[1].data;
      final companiesRaw = results[2].data;

      final overview = overviewRaw is Map
          ? AdminOverview.fromJson(Map<String, dynamic>.from(overviewRaw))
          : const AdminOverview(
              totalCompanies: 0,
              activeCompanies: 0,
              totalUsers: 0,
              totalRevenue: 0,
              monthlyRevenue: 0,
              totalMessages: 0,
              totalOrders: 0,
              companiesChange: 0,
              revenueChange: 0,
              messagesChange: 0,
              usersChange: 0,
            );

      final health = healthRaw is Map
          ? AdminSystemHealth.fromJson(Map<String, dynamic>.from(healthRaw))
          : const AdminSystemHealth(
              queue: AdminQueueHealth(pending: 0, failed: 0, healthy: true),
              integrations: AdminIntegrationsHealth(
                metaOAuthConfigured: false,
                expiringTokens: 0,
              ),
              alerts: [],
            );

      final companies = companiesRaw is List
          ? companiesRaw
              .whereType<Map>()
              .map((e) => AdminCompany.fromJson(Map<String, dynamic>.from(e)))
              .take(companyLimit)
              .toList()
          : <AdminCompany>[];

      return AdminDashboard(
        overview: overview,
        health: health,
        companies: companies,
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
