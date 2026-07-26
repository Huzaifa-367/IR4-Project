final class AuthUser {
  const AuthUser({
    required this.id,
    required this.uuid,
    required this.name,
    required this.email,
    required this.mustChangePassword,
    required this.permissions,
  });

  final int id;
  final String uuid;
  final String name;
  final String email;
  final bool mustChangePassword;
  final List<String> permissions;

  bool get canViewEquipment => permissions.contains('view-equipment');

  bool get canCheckout => permissions.contains('update-equipment');

  factory AuthUser.fromJson(
    Map<String, dynamic> user,
    List<dynamic> permissions,
  ) {
    return AuthUser(
      id: user['id'] as int,
      uuid: user['uuid'] as String,
      name: user['name'] as String,
      email: user['email'] as String,
      mustChangePassword: user['must_change_password'] as bool? ?? false,
      permissions: permissions.map((dynamic item) => item.toString()).toList(),
    );
  }
}

final class LoginResult {
  const LoginResult({
    required this.token,
    required this.user,
  });

  final String token;
  final AuthUser user;
}
