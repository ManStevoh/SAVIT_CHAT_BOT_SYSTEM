class AdminOverview {
  const AdminOverview({
    required this.totalCompanies,
    required this.activeCompanies,
    required this.totalUsers,
    required this.totalRevenue,
    required this.monthlyRevenue,
    required this.totalMessages,
    required this.totalOrders,
    required this.companiesChange,
    required this.revenueChange,
    required this.messagesChange,
    required this.usersChange,
  });

  final int totalCompanies;
  final int activeCompanies;
  final int totalUsers;
  final double totalRevenue;
  final double monthlyRevenue;
  final int totalMessages;
  final int totalOrders;
  final double companiesChange;
  final double revenueChange;
  final double messagesChange;
  final double usersChange;

  factory AdminOverview.fromJson(Map<String, dynamic> json) {
    return AdminOverview(
      totalCompanies: (json['totalCompanies'] as num?)?.toInt() ?? 0,
      activeCompanies: (json['activeCompanies'] as num?)?.toInt() ?? 0,
      totalUsers: (json['totalUsers'] as num?)?.toInt() ?? 0,
      totalRevenue: (json['totalRevenue'] as num?)?.toDouble() ?? 0,
      monthlyRevenue: (json['monthlyRevenue'] as num?)?.toDouble() ?? 0,
      totalMessages: (json['totalMessages'] as num?)?.toInt() ?? 0,
      totalOrders: (json['totalOrders'] as num?)?.toInt() ?? 0,
      companiesChange: (json['companiesChange'] as num?)?.toDouble() ?? 0,
      revenueChange: (json['revenueChange'] as num?)?.toDouble() ?? 0,
      messagesChange: (json['messagesChange'] as num?)?.toDouble() ?? 0,
      usersChange: (json['usersChange'] as num?)?.toDouble() ?? 0,
    );
  }
}

class AdminQueueHealth {
  const AdminQueueHealth({
    required this.pending,
    required this.failed,
    required this.healthy,
  });

  final int pending;
  final int failed;
  final bool healthy;

  factory AdminQueueHealth.fromJson(Map<String, dynamic> json) {
    return AdminQueueHealth(
      pending: (json['pending'] as num?)?.toInt() ?? 0,
      failed: (json['failed'] as num?)?.toInt() ?? 0,
      healthy: json['healthy'] == true,
    );
  }
}

class AdminIntegrationsHealth {
  const AdminIntegrationsHealth({
    required this.metaOAuthConfigured,
    required this.expiringTokens,
  });

  final bool metaOAuthConfigured;
  final int expiringTokens;

  factory AdminIntegrationsHealth.fromJson(Map<String, dynamic> json) {
    return AdminIntegrationsHealth(
      metaOAuthConfigured: json['metaOAuthConfigured'] == true,
      expiringTokens: (json['expiringTokens'] as num?)?.toInt() ?? 0,
    );
  }
}

class AdminSystemHealth {
  const AdminSystemHealth({
    required this.queue,
    required this.integrations,
    required this.alerts,
  });

  final AdminQueueHealth queue;
  final AdminIntegrationsHealth integrations;
  final List<String> alerts;

  factory AdminSystemHealth.fromJson(Map<String, dynamic> json) {
    final queueRaw = json['queue'];
    final integrationsRaw = json['integrations'];
    final alertsRaw = json['alerts'];

    return AdminSystemHealth(
      queue: queueRaw is Map
          ? AdminQueueHealth.fromJson(Map<String, dynamic>.from(queueRaw))
          : const AdminQueueHealth(pending: 0, failed: 0, healthy: true),
      integrations: integrationsRaw is Map
          ? AdminIntegrationsHealth.fromJson(Map<String, dynamic>.from(integrationsRaw))
          : const AdminIntegrationsHealth(metaOAuthConfigured: false, expiringTokens: 0),
      alerts: alertsRaw is List
          ? alertsRaw.whereType<String>().toList()
          : const [],
    );
  }
}

class AdminCompany {
  const AdminCompany({
    required this.id,
    required this.name,
    required this.email,
    required this.plan,
    required this.status,
    required this.totalChats,
    required this.totalOrders,
    required this.createdAt,
    required this.whatsappConnected,
  });

  final String id;
  final String name;
  final String email;
  final String plan;
  final String status;
  final int totalChats;
  final int totalOrders;
  final String createdAt;
  final bool whatsappConnected;

  factory AdminCompany.fromJson(Map<String, dynamic> json) {
    return AdminCompany(
      id: '${json['id']}',
      name: (json['name'] ?? '') as String,
      email: (json['email'] ?? '') as String,
      plan: (json['plan'] ?? 'starter') as String,
      status: (json['status'] ?? 'pending') as String,
      totalChats: (json['totalChats'] as num?)?.toInt() ?? 0,
      totalOrders: (json['totalOrders'] as num?)?.toInt() ?? 0,
      createdAt: (json['createdAt'] ?? '') as String,
      whatsappConnected: json['whatsappConnected'] == true,
    );
  }
}

class AdminDashboard {
  const AdminDashboard({
    required this.overview,
    required this.health,
    required this.companies,
  });

  final AdminOverview overview;
  final AdminSystemHealth health;
  final List<AdminCompany> companies;
}
