class PromocionPrecio {
  const PromocionPrecio({
    required this.id,
    required this.nombre,
    required this.tipo,
    required this.valor,
    required this.descuento,
    required this.fechaInicio,
    required this.fechaFin,
  });

  final int id;
  final String nombre;
  final String tipo;
  final String valor;
  final String descuento;
  final DateTime fechaInicio;
  final DateTime fechaFin;

  factory PromocionPrecio.fromJson(Map<String, dynamic> json) =>
      PromocionPrecio(
        id: json['id'] as int,
        nombre: json['nombre'] as String,
        tipo: json['tipo'] as String,
        valor: json['valor'] as String,
        descuento: json['descuento'] as String,
        fechaInicio: DateTime.parse(json['fecha_inicio'] as String),
        fechaFin: DateTime.parse(json['fecha_fin'] as String),
      );
}
