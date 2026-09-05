class Cliente {
  const Cliente({
    required this.id,
    required this.nombre,
    this.telefono,
    this.email,
    this.direccion,
    this.notas,
    required this.activo,
  });
  final int id;
  final String nombre;
  final String? telefono;
  final String? email;
  final String? direccion;
  final String? notas;
  final bool activo;

  factory Cliente.fromJson(Map<String, dynamic> json) => Cliente(
    id: json['id'] as int,
    nombre: json['nombre'] as String,
    telefono: json['telefono'] as String?,
    email: json['email'] as String?,
    direccion: json['direccion'] as String?,
    notas: json['notas'] as String?,
    activo: json['activo'] as bool,
  );
}
