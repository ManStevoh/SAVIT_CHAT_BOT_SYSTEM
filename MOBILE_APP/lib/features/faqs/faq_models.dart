class Faq {
  const Faq({
    required this.id,
    required this.question,
    required this.answer,
    required this.category,
    required this.keywords,
    required this.isActive,
    required this.usageCount,
    required this.createdAt,
  });

  final String id;
  final String question;
  final String answer;
  final String category;
  final List<String> keywords;
  final bool isActive;
  final int usageCount;
  final String createdAt;

  factory Faq.fromJson(Map<String, dynamic> json) {
    final rawKeywords = json['keywords'];
    return Faq(
      id: '${json['id']}',
      question: json['question']?.toString() ?? '',
      answer: json['answer']?.toString() ?? '',
      category: json['category']?.toString() ?? '',
      keywords: rawKeywords is List
          ? rawKeywords
              .map((e) => e.toString())
              .where((e) => e.trim().isNotEmpty)
              .toList()
          : const [],
      isActive: json['isActive'] == true,
      usageCount: (json['usageCount'] as num?)?.toInt() ?? 0,
      createdAt: json['createdAt']?.toString() ?? '',
    );
  }

  Faq copyWith({
    String? question,
    String? answer,
    String? category,
    List<String>? keywords,
    bool? isActive,
    int? usageCount,
  }) {
    return Faq(
      id: id,
      question: question ?? this.question,
      answer: answer ?? this.answer,
      category: category ?? this.category,
      keywords: keywords ?? this.keywords,
      isActive: isActive ?? this.isActive,
      usageCount: usageCount ?? this.usageCount,
      createdAt: createdAt,
    );
  }
}
