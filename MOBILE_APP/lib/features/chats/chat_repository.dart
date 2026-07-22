import 'package:dio/dio.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';
import 'chat_models.dart';

class ChatRepository {
  ChatRepository(this._api);

  final ApiClient _api;

  Future<List<ChatSummary>> listChats({String? search}) async {
    try {
      final response = await _api.dio.get(
        '/company/chats',
        queryParameters: {
          if (search != null && search.trim().isNotEmpty) 'search': search.trim(),
        },
      );
      final data = response.data;
      if (data is! List) return [];
      return data
          .whereType<Map>()
          .map((e) => ChatSummary.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<List<ChatMessage>> listMessages(String chatId) async {
    try {
      final response = await _api.dio.get('/company/chats/$chatId/messages');
      final data = response.data;
      if (data is! List) return [];
      return data
          .whereType<Map>()
          .map((e) => ChatMessage.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<SendMessageResult> sendMessage(String chatId, String content) async {
    try {
      final response = await _api.dio.post(
        '/company/chats/$chatId/messages',
        data: {'content': content},
      );
      final data = response.data;
      if (data is! Map) {
        return const SendMessageResult(whatsappSent: true);
      }
      return SendMessageResult(
        whatsappSent: data['whatsappSent'] == true,
        whatsappError: data['whatsappError']?.toString(),
        message: data['message']?.toString(),
      );
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<ChatSummary> startChat({required String phone, String? name}) async {
    try {
      final response = await _api.dio.post(
        '/company/chats/start',
        data: {
          'phone': phone,
          if (name != null && name.trim().isNotEmpty) 'name': name.trim(),
        },
      );
      final chatJson = response.data is Map ? response.data['chat'] : null;
      if (chatJson is! Map) {
        throw ApiException('Could not start chat.');
      }
      return ChatSummary.fromJson(Map<String, dynamic>.from(chatJson));
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }

  Future<void> handBack(String chatId) async {
    try {
      await _api.dio.post('/company/chats/$chatId/hand-back');
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
