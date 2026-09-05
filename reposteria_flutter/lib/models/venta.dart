import 'cliente.dart';

class VentaDetalle {
  const VentaDetalle({
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
  factory VentaDetalle.fromJson(Map<String, dynamic> j) => VentaDetalle(
    id: j['id'] as int,
    productoId: j['producto_id'] as int,
    productoVarianteId: j['producto_variante_id'] as int?,
    nombreProducto: j['nombre_producto'] as String,
    nombreVariante: j['nombre_variante'] as String?,
    cantidad: j['cantidad'] as int,
    precioUnitario: j['precio_unitario'] as String,
    subtotal: j['subtotal'] as String,
  );
}

class Pago {
  const Pago({
    required this.id,
    required this.metodo,
    required this.monto,
    required this.fechaPago,
    this.referencia,
    this.observaciones,
  });
  final int id;
  final String metodo;
  final String monto;
  final DateTime fechaPago;
  final String? referencia;
  final String? observaciones;
  factory Pago.fromJson(Map<String, dynamic> j) => Pago(
    id: j['id'] as int,
    metodo: j['metodo'] as String,
    monto: j['monto'] as String,
    fechaPago: DateTime.parse(j['fecha_pago'] as String),
    referencia: j['referencia'] as String?,
    observaciones: j['observaciones'] as String?,
  );
}

class VentaPedido {
  const VentaPedido({required this.id, required this.estado});
  final int id;
  final String estado;
  factory VentaPedido.fromJson(Map<String, dynamic> j) =>
      VentaPedido(id: j['id'] as int, estado: j['estado'] as String);
}

class Venta {
  const Venta({
    required this.id,
    this.cliente,
    this.pedido,
    required this.estado,
    required this.fechaVenta,
    required this.subtotal,
    required this.descuento,
    required this.total,
    required this.montoPagado,
    required this.saldo,
    this.observaciones,
    required this.detalles,
    required this.pagos,
  });
  final int id;
  final Cliente? cliente;
  final VentaPedido? pedido;
  final String estado;
  final DateTime fechaVenta;
  final String subtotal;
  final String descuento;
  final String total;
  final String montoPagado;
  final String saldo;
  final String? observaciones;
  final List<VentaDetalle> detalles;
  final List<Pago> pagos;
  bool get anulada => estado == 'anulada';
  factory Venta.fromJson(Map<String, dynamic> j) => Venta(
    id: j['id'] as int,
    cliente: j['cliente'] == null
        ? null
        : Cliente.fromJson(j['cliente'] as Map<String, dynamic>),
    pedido: j['pedido'] == null
        ? null
        : VentaPedido.fromJson(j['pedido'] as Map<String, dynamic>),
    estado: j['estado'] as String,
    fechaVenta: DateTime.parse(j['fecha_venta'] as String),
    subtotal: j['subtotal'] as String,
    descuento: j['descuento'] as String,
    total: j['total'] as String,
    montoPagado: j['monto_pagado'] as String,
    saldo: j['saldo'] as String,
    observaciones: j['observaciones'] as String?,
    detalles: (j['detalles'] as List? ?? const [])
        .map((e) => VentaDetalle.fromJson(e as Map<String, dynamic>))
        .toList(growable: false),
    pagos: (j['pagos'] as List? ?? const [])
        .map((e) => Pago.fromJson(e as Map<String, dynamic>))
        .toList(growable: false),
  );
}
