class Reposteria {
  const Reposteria({
    required this.id,
    required this.nombre,
    required this.slug,
    required this.estado,
  });

  final int id;
  final String nombre;
  final String slug;
  final String estado;

  factory Reposteria.fromJson(Map<String, dynamic> json) => Reposteria(
    id: json['id'] as int,
    nombre: json['nombre'] as String,
    slug: json['slug'] as String,
    estado: json['estado'] as String,
  );
}
