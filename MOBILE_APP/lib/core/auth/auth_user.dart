class AuthUser {
  const AuthUser({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.role,
    this.companyId,
    this.companyName,
  });

  final String id;
  final String name;
  final String email;
  final String? phone;
  final String? role;
  final String? companyId;
  final String? companyName;

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    final company = json['company'];
    final nestedName = company is Map ? company['name']?.toString() : null;
    final nestedId = company is Map ? company['id']?.toString() : null;
    return AuthUser(
      id: '${json['id']}',
      name: json['name']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      phone: json['phone']?.toString(),
      role: json['role']?.toString(),
      companyId: json['companyId']?.toString() ?? nestedId,
      companyName: json['companyName']?.toString() ?? nestedName,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'email': email,
        'phone': phone,
        'role': role,
        'companyId': companyId,
        'companyName': companyName,
      };

  /// Platform admin access (matches Laravel `User::isAdmin()` — role `admin`).
  bool get isPlatformAdmin => role == 'admin';

  bool get hasCompany =>
      companyId != null && companyId!.trim().isNotEmpty;

  /// Platform admin without a company tenant — company APIs will 403.
  bool get isPlatformAdminOnly => isPlatformAdmin && !hasCompany;
}
