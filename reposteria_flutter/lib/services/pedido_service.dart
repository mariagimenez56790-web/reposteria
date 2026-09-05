import '../models/pedido.dart';
import 'api_client.dart';

class PaginaPedidos {
  const PaginaPedidos(this.items, this.currentPage, this.lastPage);
  final List<Pedido> items;
  final int currentPage;
  final int lastPage;
  bool get hasMore => currentPage < lastPage;
}

class PedidoService {
  const PedidoService(this._api);
  final ApiClient _api;
  String _base(int reposteriaId) => '/api/v1/reposterias/$reposteriaId/pedidos';

  Future<PaginaPedidos> listar({
    required int reposteriaId,
    required String token,
    String? estado,
    int? clienteId,
    DateTime? desde,
    DateTime? hasta,
    int page = 1,
  }) async {
    String date(DateTime value) =>
        '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
    final uri = Uri(
      path: _base(reposteriaId),
      queryParameters: {
        'page': '$page',
        'per_page': '15',
        'estado': ?estado,
        if (clienteId != null) 'cliente_id': '$clienteId',
        if (desde != null) 'fecha_desde': date(desde),
        if (hasta != null) 'fecha_hasta': date(hasta),
      },
    );
    final json = await _api.get(uri.toString(), token: token);
    final meta = json['meta'] as Map<String, dynamic>;
    return PaginaPedidos(
      (json['data'] as List)
          .map((e) => Pedido.fromJson(e as Map<String, dynamic>))
          .toList(growable: false),
      meta['current_page'] as int,
      meta['last_page'] as int,
    );
  }

  Future<Pedido> detalle({
    required int reposteriaId,
    required int pedidoId,
    required String token,
  }) async {
    final json = await _api.get(
      '${_base(reposteriaId)}/$pedidoId',
      token: token,
    );
    return Pedido.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<Pedido> crear({
    required int reposteriaId,
    required String token,
    int? clienteId,
    DateTime? fechaEntrega,
    String? observaciones,
    required List<Map<String, dynamic>> detalles,
  }) async {
    final json = await _api.post(
      _base(reposteriaId),
      token: token,
      body: {
        'cliente_id': clienteId,
        'fecha_entrega': fechaEntrega?.toIso8601String(),
        'observaciones': observaciones,
        'detalles': detalles,
      },
    );
    return Pedido.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<Pedido> editar({
    required int reposteriaId,
    required int pedidoId,
    required String token,
    int? clienteId,
    DateTime? fechaEntrega,
    String? observaciones,
  }) async {
    final json = await _api.patch(
      '${_base(reposteriaId)}/$pedidoId',
      token: token,
      body: {
        'cliente_id': clienteId,
        'fecha_entrega': fechaEntrega?.toIso8601String(),
        'observaciones': observaciones,
      },
    );
    return Pedido.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<PedidoDetalle> agregarDetalle({
    required int reposteriaId,
    required int pedidoId,
    required String token,
    required int productoId,
    int? varianteId,
    required int cantidad,
  }) async {
    final json = await _api.post(
      '${_base(reposteriaId)}/$pedidoId/detalles',
      token: token,
      body: {
        'producto_id': productoId,
        'producto_variante_id': varianteId,
        'cantidad': cantidad,
      },
    );
    return PedidoDetalle.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<PedidoDetalle> editarDetalle({
    required int reposteriaId,
    required int pedidoId,
    required int detalleId,
    required String token,
    required int cantidad,
  }) async {
    final json = await _api.patch(
      '${_base(reposteriaId)}/$pedidoId/detalles/$detalleId',
      token: token,
      body: {'cantidad': cantidad},
    );
    return PedidoDetalle.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<void> eliminarDetalle({
    required int reposteriaId,
    required int pedidoId,
    required int detalleId,
    required String token,
  }) => _api.delete(
    '${_base(reposteriaId)}/$pedidoId/detalles/$detalleId',
    token: token,
  );

  Future<Pedido> cambiarEstado({
    required int reposteriaId,
    required int pedidoId,
    required String token,
    required String estado,
  }) async {
    final json = await _api.post(
      '${_base(reposteriaId)}/$pedidoId/estado',
      token: token,
      body: {'estado': estado},
    );
    return Pedido.fromJson(json['data'] as Map<String, dynamic>);
  }
}
