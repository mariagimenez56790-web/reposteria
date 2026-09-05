import 'promocion_precio.dart';

class ProductoVariante {
  const ProductoVariante({
    required this.id,
    required this.nombre,
    required this.precio,
    required this.precioFinal,
    required this.stock,
    required this.promocion,
  });

  final int id;
  final String nombre;
  final String precio;
  final String precioFinal;
  final int stock;
  final PromocionPrecio? promocion;

  factory ProductoVariante.fromJson(Map<String, dynamic> json) =>
      ProductoVariante(
        id: json['id'] as int,
        nombre: json['nombre'] as String,
        precio: json['precio'] as String,
        precioFinal: json['precio_final'] as String,
        stock: json['stock'] as int,
        promocion: json['promocion'] == null
            ? null
            : PromocionPrecio.fromJson(
                json['promocion'] as Map<String, dynamic>,
              ),
      );
}
