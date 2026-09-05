import 'producto_variante.dart';
import 'promocion_precio.dart';

class Producto {
  const Producto({
    required this.id,
    required this.categoriaId,
    required this.nombre,
    required this.descripcion,
    required this.precio,
    required this.precioFinal,
    required this.imagen,
    required this.personalizable,
    required this.manejaStock,
    required this.stock,
    required this.tieneVariantes,
    required this.promocion,
    required this.variantes,
  });

  final int id;
  final int? categoriaId;
  final String nombre;
  final String? descripcion;
  final String precio;
  final String precioFinal;
  final String? imagen;
  final bool personalizable;
  final bool manejaStock;
  final int stock;
  final bool tieneVariantes;
  final PromocionPrecio? promocion;
  final List<ProductoVariante> variantes;

  bool get disponible => !manejaStock || stock > 0 || tieneVariantes;

  factory Producto.fromJson(Map<String, dynamic> json) => Producto(
    id: json['id'] as int,
    categoriaId: json['categoria_id'] as int?,
    nombre: json['nombre'] as String,
    descripcion: json['descripcion'] as String?,
    precio: json['precio'] as String,
    precioFinal: json['precio_final'] as String,
    imagen: json['imagen'] as String?,
    personalizable: json['personalizable'] as bool,
    manejaStock: json['maneja_stock'] as bool,
    stock: json['stock'] as int,
    tieneVariantes: json['tiene_variantes'] as bool,
    promocion: json['promocion'] == null
        ? null
        : PromocionPrecio.fromJson(json['promocion'] as Map<String, dynamic>),
    variantes: (json['variantes'] as List<dynamic>? ?? const [])
        .map((item) => ProductoVariante.fromJson(item as Map<String, dynamic>))
        .toList(growable: false),
  );
}
