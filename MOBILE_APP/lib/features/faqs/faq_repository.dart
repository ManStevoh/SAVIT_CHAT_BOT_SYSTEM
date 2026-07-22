import 'package:dio/dio.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';
import 'faq_models.dart';

class FaqRepository {
  FaqRepository(this._api);

  final ApiClient _api;

  Future<List<Faq>> listFaqs({String? search, String? category}) async {
    try {
      final response = await _api.dio.get(
        '/company/faqs',
        queryParameters: {
          if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
          if (category != null && category.trim().isNotEmpty && category != 'all')
            'category': category.trim(),
        },
      );
      final data = response.data;
      if (data is! List) return [];
      return data
          .whereType<Map>()
          .map((e) => Faq.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<Faq> createFaq({
    required String question,
    required String answer,
    String? category,
    List<String>? keywords,
  }) async {
    try {
      final response = await _api.dio.post(
        '/company/faqs',
        data: {
          'question': question,
          'answer': answer,
          if (category != null && category.trim().isNotEmpty) 'category': category.trim(),
          if (keywords != null && keywords.isNotEmpty) 'keywords': keywords,
        },
      );
      final faqJson = response.data is Map ? response.data['faq'] : null;
      if (faqJson is! Map) {
        throw ApiException('Could not create FAQ.');
      }
      return Faq.fromJson(Map<String, dynamic>.from(faqJson));
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> updateFaq(
    String id, {
    String? question,
    String? answer,
    String? category,
    List<String>? keywords,
    bool? isActive,
  }) async {
    try {
      await _api.dio.put(
        '/company/faqs/$id',
        data: {
          if (question != null) 'question': question,
          if (answer != null) 'answer': answer,
          if (category != null) 'category': category,
          if (keywords != null) 'keywords': keywords,
          if (isActive != null) 'isActive': isActive,
        },
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> deleteFaq(String id) async {
    try {
      await _api.dio.delete('/company/faqs/$id');
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
