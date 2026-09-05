class Categoria {
  const Categoria({
    required this.id,
    required this.nombre,
    required this.descripcion,
    required this.activo,
  });

  final int id;
  final String nombre;
  final String? descripcion;
  final bool activo;

  factory Categoria.fromJson(Map<String, dynamic> json) => Categoria(
    id: json['id'] as int,
    nombre: json['nombre'] as String,
    descripcion: json['descripcion'] as String?,
    activo: json['activo'] as bool,
  );
}
