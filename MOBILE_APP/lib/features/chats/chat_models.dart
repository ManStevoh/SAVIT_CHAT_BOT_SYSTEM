import '../../core/utils/json_utils.dart';

class ChatSummary {
  const ChatSummary({
    required this.id,
    required this.customerName,
    required this.customerPhone,
    required this.lastMessage,
    required this.lastMessageTime,
    required this.unreadCount,
    required this.status,
  });

  final String id;
  final String customerName;
  final String customerPhone;
  final String lastMessage;
  final String lastMessageTime;
  final int unreadCount;
  final String status;

  factory ChatSummary.fromJson(Map<String, dynamic> json) {
    return ChatSummary(
      id: '${json['id']}',
      customerName: jsonString(json['customerName'], 'Customer'),
      customerPhone: jsonString(json['customerPhone']),
      lastMessage: jsonString(json['lastMessage']),
      lastMessageTime: jsonString(json['lastMessageTime']),
      unreadCount: (json['unreadCount'] as num?)?.toInt() ?? 0,
      status: jsonString(json['status'], 'active'),
    );
  }
}

class ChatMessage {
  const ChatMessage({
    required this.id,
    required this.content,
    required this.sender,
    required this.timestamp,
    this.status,
  });

  final String id;
  final String content;
  final String sender;
  final String timestamp;
  final String? status;

  bool get isIncoming => sender == 'customer';

  bool get isFailed => status == 'failed';

  factory ChatMessage.fromJson(Map<String, dynamic> json) {
    return ChatMessage(
      id: '${json['id']}',
      content: jsonString(json['content']),
      sender: jsonString(json['sender'], 'customer'),
      timestamp: jsonString(json['timestamp']),
      status: jsonStringOrNull(json['status']),
    );
  }
}

class SendMessageResult {
  const SendMessageResult({
    required this.whatsappSent,
    this.whatsappError,
    this.message,
  });

  final bool whatsappSent;
  final String? whatsappError;
  final String? message;
}
