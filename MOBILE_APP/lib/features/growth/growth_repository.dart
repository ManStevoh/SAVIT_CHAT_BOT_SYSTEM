import 'package:dio/dio.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';
import 'growth_models.dart';

class GrowthRepository {
  GrowthRepository(this._api);

  final ApiClient _api;

  Future<GrowthOverview> loadOverview({String period = '30d'}) async {
    try {
      final results = await Future.wait([
        _api.dio.get(
          '/company/growth/analytics',
          queryParameters: {'period': period},
        ),
        _api.dio.get('/company/growth/posts'),
      ]);

      final analyticsRaw = results[0].data;
      final postsRaw = results[1].data;

      final analytics = analyticsRaw is Map
          ? GrowthAnalytics.fromJson(Map<String, dynamic>.from(analyticsRaw))
          : GrowthAnalytics(
              isDemo: false,
              summary: GrowthSummary.fromJson({'period': period}),
              platformBreakdown: const [],
              topPosts: const [],
              limits: const GrowthLimits(
                aiPostsUsed: 0,
                aiPostsLimit: 0,
                aiImagesUsed: 0,
                aiImagesLimit: 0,
                platformLimit: 0,
              ),
            );

      final posts = postsRaw is List
          ? postsRaw
              .whereType<Map>()
              .map((e) => GrowthPost.fromJson(Map<String, dynamic>.from(e)))
              .toList()
          : <GrowthPost>[];

      return GrowthOverview(analytics: analytics, recentPosts: posts);
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<GrowthPost> createPost({
    required String platform,
    required String content,
    String? title,
    String? contentType,
    List<String>? hashtags,
    List<String>? mediaUrls,
    String? scheduledAt,
  }) async {
    try {
      final response = await _api.dio.post(
        '/company/growth/posts',
        data: {
          'platform': platform,
          'content': content,
          if (title != null && title.trim().isNotEmpty) 'title': title.trim(),
          if (contentType != null && contentType.isNotEmpty) 'contentType': contentType,
          if (hashtags != null && hashtags.isNotEmpty) 'hashtags': hashtags,
          if (mediaUrls != null && mediaUrls.isNotEmpty) 'mediaUrls': mediaUrls,
          if (scheduledAt != null && scheduledAt.isNotEmpty) 'scheduledAt': scheduledAt,
        },
      );
      final postJson = response.data is Map ? response.data['post'] : null;
      if (postJson is! Map) {
        throw ApiException('Could not create post.');
      }
      return GrowthPost.fromJson(Map<String, dynamic>.from(postJson));
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<GrowthPost> approvePost(String id) async {
    try {
      final response = await _api.dio.post('/company/growth/posts/$id/approve');
      final postJson = response.data is Map ? response.data['post'] : null;
      if (postJson is! Map) {
        throw ApiException('Could not approve post.');
      }
      return GrowthPost.fromJson(Map<String, dynamic>.from(postJson));
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<GrowthPost> publishPost(String id) async {
    try {
      final response = await _api.dio.post('/company/growth/posts/$id/publish');
      final postJson = response.data is Map ? response.data['post'] : null;
      if (postJson is! Map) {
        throw ApiException('Could not publish post.');
      }
      return GrowthPost.fromJson(Map<String, dynamic>.from(postJson));
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
