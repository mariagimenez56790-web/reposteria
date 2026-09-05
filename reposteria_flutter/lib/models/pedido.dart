import 'cliente.dart';

class PedidoDetalle {
  const PedidoDetalle({
    required this.id,
    required this.productoId,
    this.productoVarianteId,
    required this.nombreProducto,
    this.nombreVariante,
    required this.cantidad,
    required this.precioUnitario,
    required this.subtotal,
  });
  final int id;
  final int productoId;
  final int? productoVarianteId;
  final String nombreProducto;
  final String? nombreVariante;
  final int cantidad;
  final String precioUnitario;
  final String subtotal;
  factory PedidoDetalle.fromJson(Map<String, dynamic> json) => PedidoDetalle(
    id: json['id'] as int,
    productoId: json['producto_id'] as int,
    productoVarianteId: json['producto_variante_id'] as int?,
    nombreProducto: json['nombre_producto'] as String,
    nombreVariante: json['nombre_variante'] as String?,
    cantidad: json['cantidad'] as int,
    precioUnitario: json['precio_unitario'] as String,
    subtotal: json['subtotal'] as String,
  );
}

class Pedido {
  const Pedido({
    required this.id,
    this.cliente,
    required this.estado,
    required this.fechaPedido,
    this.fechaEntrega,
    this.observaciones,
    required this.total,
    required this.detalles,
  });
  final int id;
  final Cliente? cliente;
  final String estado;
  final DateTime fechaPedido;
  final DateTime? fechaEntrega;
  final String? observaciones;
  final String total;
  final List<PedidoDetalle> detalles;
  bool get editable => estado == 'pendiente';
  factory Pedido.fromJson(Map<String, dynamic> json) => Pedido(
    id: json['id'] as int,
    cliente: json['cliente'] == null
        ? null
        : Cliente.fromJson(json['cliente'] as Map<String, dynamic>),
    estado: json['estado'] as String,
    fechaPedido: DateTime.parse(json['fecha_pedido'] as String),
    fechaEntrega: json['fecha_entrega'] == null
        ? null
        : DateTime.parse(json['fecha_entrega'] as String),
    observaciones: json['observaciones'] as String?,
    total: json['total'] as String,
    detalles: (json['detalles'] as List<dynamic>? ?? const [])
        .map((e) => PedidoDetalle.fromJson(e as Map<String, dynamic>))
        .toList(growable: false),
  );
}
