import 'reposteria.dart';

class User {
  const User({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    required this.activo,
    required this.reposterias,
  });

  final int id;
  final String name;
  final String email;
  final String? role;
  final bool activo;
  final List<Reposteria> reposterias;

  factory User.fromJson(Map<String, dynamic> json) => User(
    id: json['id'] as int,
    name: json['name'] as String,
    email: json['email'] as String,
    role: json['role'] as String?,
    activo: json['activo'] as bool,
    reposterias: (json['reposterias'] as List<dynamic>? ?? const [])
        .map((item) => Reposteria.fromJson(item as Map<String, dynamic>))
        .toList(growable: false),
  );
}
