class GrowthSummary {
  const GrowthSummary({
    required this.period,
    required this.leads,
    required this.whatsappStarts,
    required this.clicks,
    required this.orders,
    required this.revenue,
    required this.adSpend,
    required this.conversionRate,
    required this.leadToOrderRate,
    this.costPerLead,
    this.customerAcquisitionCost,
    this.roi,
  });

  final String period;
  final int leads;
  final int whatsappStarts;
  final int clicks;
  final int orders;
  final double revenue;
  final double adSpend;
  final double conversionRate;
  final double leadToOrderRate;
  final double? costPerLead;
  final double? customerAcquisitionCost;
  final double? roi;

  factory GrowthSummary.fromJson(Map<String, dynamic> json) {
    return GrowthSummary(
      period: (json['period'] ?? '30d') as String,
      leads: (json['leads'] as num?)?.toInt() ?? 0,
      whatsappStarts: (json['whatsappStarts'] as num?)?.toInt() ?? 0,
      clicks: (json['clicks'] as num?)?.toInt() ?? 0,
      orders: (json['orders'] as num?)?.toInt() ?? 0,
      revenue: (json['revenue'] as num?)?.toDouble() ?? 0,
      adSpend: (json['adSpend'] as num?)?.toDouble() ?? 0,
      conversionRate: (json['conversionRate'] as num?)?.toDouble() ?? 0,
      leadToOrderRate: (json['leadToOrderRate'] as num?)?.toDouble() ?? 0,
      costPerLead: (json['costPerLead'] as num?)?.toDouble(),
      customerAcquisitionCost: (json['customerAcquisitionCost'] as num?)?.toDouble(),
      roi: (json['roi'] as num?)?.toDouble(),
    );
  }
}

class PlatformBreakdown {
  const PlatformBreakdown({
    required this.platform,
    required this.orders,
    required this.revenue,
    required this.leads,
  });

  final String platform;
  final int orders;
  final double revenue;
  final int leads;

  factory PlatformBreakdown.fromJson(Map<String, dynamic> json) {
    return PlatformBreakdown(
      platform: (json['platform'] ?? 'unknown') as String,
      orders: (json['orders'] as num?)?.toInt() ?? 0,
      revenue: (json['revenue'] as num?)?.toDouble() ?? 0,
      leads: (json['leads'] as num?)?.toInt() ?? 0,
    );
  }
}

class TopPost {
  const TopPost({
    required this.id,
    required this.title,
    required this.platform,
    required this.reach,
    required this.clicks,
    required this.leads,
    required this.orders,
    required this.revenue,
    required this.engagementRate,
    this.performanceScore,
    this.contentTags = const [],
  });

  final String id;
  final String title;
  final String platform;
  final int reach;
  final int clicks;
  final int leads;
  final int orders;
  final double revenue;
  final double engagementRate;
  final double? performanceScore;
  final List<String> contentTags;

  factory TopPost.fromJson(Map<String, dynamic> json) {
    return TopPost(
      id: '${json['id']}',
      title: (json['title'] ?? 'Untitled post') as String,
      platform: (json['platform'] ?? 'unknown') as String,
      reach: (json['reach'] as num?)?.toInt() ?? 0,
      clicks: (json['clicks'] as num?)?.toInt() ?? 0,
      leads: (json['leads'] as num?)?.toInt() ?? 0,
      orders: (json['orders'] as num?)?.toInt() ?? 0,
      revenue: (json['revenue'] as num?)?.toDouble() ?? 0,
      engagementRate: (json['engagementRate'] as num?)?.toDouble() ?? 0,
      performanceScore: (json['performanceScore'] as num?)?.toDouble(),
      contentTags: (json['contentTags'] as List?)
              ?.map((e) => e.toString())
              .toList() ??
          const [],
    );
  }
}

class GrowthLimits {
  const GrowthLimits({
    required this.aiPostsUsed,
    required this.aiPostsLimit,
    required this.aiImagesUsed,
    required this.aiImagesLimit,
    required this.platformLimit,
  });

  final int aiPostsUsed;
  final int aiPostsLimit;
  final int aiImagesUsed;
  final int aiImagesLimit;
  final int platformLimit;

  factory GrowthLimits.fromJson(Map<String, dynamic> json) {
    return GrowthLimits(
      aiPostsUsed: (json['aiPostsUsed'] as num?)?.toInt() ?? 0,
      aiPostsLimit: (json['aiPostsLimit'] as num?)?.toInt() ?? 0,
      aiImagesUsed: (json['aiImagesUsed'] as num?)?.toInt() ?? 0,
      aiImagesLimit: (json['aiImagesLimit'] as num?)?.toInt() ?? 0,
      platformLimit: (json['platformLimit'] as num?)?.toInt() ?? 0,
    );
  }
}

class GrowthCelebration {
  const GrowthCelebration({
    required this.showHighlight,
    required this.message,
    this.firstAttributedSaleAt,
  });

  final bool showHighlight;
  final String message;
  final String? firstAttributedSaleAt;

  factory GrowthCelebration.fromJson(Map<String, dynamic> json) {
    return GrowthCelebration(
      showHighlight: json['showHighlight'] == true,
      message: (json['message'] ?? '') as String,
      firstAttributedSaleAt: json['firstAttributedSaleAt']?.toString(),
    );
  }
}

class GrowthAnalytics {
  const GrowthAnalytics({
    required this.isDemo,
    required this.summary,
    required this.platformBreakdown,
    required this.topPosts,
    required this.limits,
    this.celebration,
  });

  final bool isDemo;
  final GrowthSummary summary;
  final List<PlatformBreakdown> platformBreakdown;
  final List<TopPost> topPosts;
  final GrowthLimits limits;
  final GrowthCelebration? celebration;

  factory GrowthAnalytics.fromJson(Map<String, dynamic> json) {
    final summaryRaw = json['summary'];
    final limitsRaw = json['limits'];
    final celebrationRaw = json['celebration'];

    return GrowthAnalytics(
      isDemo: json['isDemo'] == true,
      summary: summaryRaw is Map
          ? GrowthSummary.fromJson(Map<String, dynamic>.from(summaryRaw))
          : const GrowthSummary(
              period: '30d',
              leads: 0,
              whatsappStarts: 0,
              clicks: 0,
              orders: 0,
              revenue: 0,
              adSpend: 0,
              conversionRate: 0,
              leadToOrderRate: 0,
            ),
      platformBreakdown: (json['platformBreakdown'] as List?)
              ?.whereType<Map>()
              .map((e) => PlatformBreakdown.fromJson(Map<String, dynamic>.from(e)))
              .toList() ??
          const [],
      topPosts: (json['topPosts'] as List?)
              ?.whereType<Map>()
              .map((e) => TopPost.fromJson(Map<String, dynamic>.from(e)))
              .toList() ??
          const [],
      limits: limitsRaw is Map
          ? GrowthLimits.fromJson(Map<String, dynamic>.from(limitsRaw))
          : const GrowthLimits(
              aiPostsUsed: 0,
              aiPostsLimit: 0,
              aiImagesUsed: 0,
              aiImagesLimit: 0,
              platformLimit: 0,
            ),
      celebration: celebrationRaw is Map
          ? GrowthCelebration.fromJson(Map<String, dynamic>.from(celebrationRaw))
          : null,
    );
  }
}

class GrowthPostMetrics {
  const GrowthPostMetrics({
    required this.reach,
    required this.clicks,
    required this.likes,
    required this.comments,
    required this.shares,
    required this.engagementRate,
  });

  final int reach;
  final int clicks;
  final int likes;
  final int comments;
  final int shares;
  final double engagementRate;

  factory GrowthPostMetrics.fromJson(Map<String, dynamic> json) {
    return GrowthPostMetrics(
      reach: (json['reach'] as num?)?.toInt() ?? 0,
      clicks: (json['clicks'] as num?)?.toInt() ?? 0,
      likes: (json['likes'] as num?)?.toInt() ?? 0,
      comments: (json['comments'] as num?)?.toInt() ?? 0,
      shares: (json['shares'] as num?)?.toInt() ?? 0,
      engagementRate: (json['engagementRate'] as num?)?.toDouble() ?? 0,
    );
  }
}

class GrowthPost {
  const GrowthPost({
    required this.id,
    required this.platform,
    required this.content,
    required this.status,
    this.title,
    this.metrics,
    this.createdAt,
    this.approvedAt,
    this.mediaUrls = const [],
  });

  final String id;
  final String platform;
  final String? title;
  final String content;
  final String status;
  final GrowthPostMetrics? metrics;
  final String? createdAt;
  final String? approvedAt;
  final List<String> mediaUrls;

  bool get canApprove => status == 'draft' && approvedAt == null;

  bool get needsMediaForPublish =>
      platform.toLowerCase() == 'instagram' && mediaUrls.isEmpty;

  bool get canPublish =>
      approvedAt != null &&
      status != 'published' &&
      status != 'failed' &&
      !needsMediaForPublish;

  String get displayTitle {
    if (title != null && title!.trim().isNotEmpty) return title!.trim();
    if (content.length <= 60) return content;
    return '${content.substring(0, 57)}...';
  }

  factory GrowthPost.fromJson(Map<String, dynamic> json) {
    final metricsRaw = json['metrics'];
    final mediaRaw = json['mediaUrls'];
    final mediaUrls = mediaRaw is List
        ? mediaRaw.map((e) => e.toString()).where((e) => e.isNotEmpty).toList()
        : const <String>[];

    return GrowthPost(
      id: '${json['id']}',
      platform: json['platform']?.toString() ?? 'unknown',
      title: json['title']?.toString(),
      content: json['content']?.toString() ?? '',
      status: json['status']?.toString() ?? 'draft',
      metrics: metricsRaw is Map
          ? GrowthPostMetrics.fromJson(Map<String, dynamic>.from(metricsRaw))
          : null,
      createdAt: json['createdAt']?.toString(),
      approvedAt: json['approvedAt']?.toString(),
      mediaUrls: mediaUrls,
    );
  }
}

/// Supported platforms for creating growth posts.
const growthPlatforms = [
  'facebook',
  'instagram',
  'linkedin',
  'tiktok',
  'twitter',
  'whatsapp',
];

class GrowthOverview {
  const GrowthOverview({
    required this.analytics,
    required this.recentPosts,
  });

  final GrowthAnalytics analytics;
  final List<GrowthPost> recentPosts;
}
