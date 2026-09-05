import '../models/venta.dart';
import 'api_client.dart';

class PaginaVentas {
  const PaginaVentas(this.items, this.currentPage, this.lastPage);
  final List<Venta> items;
  final int currentPage;
  final int lastPage;
  bool get hasMore => currentPage < lastPage;
}

class VentaService {
  const VentaService(this._api);
  final ApiClient _api;
  String _base(int tenant) => '/api/v1/reposterias/$tenant/ventas';
  Future<PaginaVentas> listar({
    required int reposteriaId,
    required String token,
    String? estado,
    int? clienteId,
    int? pedidoId,
    DateTime? desde,
    DateTime? hasta,
    int page = 1,
  }) async {
    String date(DateTime d) =>
        '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
    final uri = Uri(
      path: _base(reposteriaId),
      queryParameters: {
        'page': '$page',
        'per_page': '15',
        'estado': ?estado,
        if (clienteId != null) 'cliente_id': '$clienteId',
        if (pedidoId != null) 'pedido_id': '$pedidoId',
        if (desde != null) 'fecha_desde': date(desde),
        if (hasta != null) 'fecha_hasta': date(hasta),
      },
    );
    final j = await _api.get(uri.toString(), token: token);
    final m = j['meta'] as Map<String, dynamic>;
    return PaginaVentas(
      (j['data'] as List)
          .map((e) => Venta.fromJson(e as Map<String, dynamic>))
          .toList(growable: false),
      m['current_page'] as int,
      m['last_page'] as int,
    );
  }

  Future<Venta> detalle({
    required int reposteriaId,
    required int ventaId,
    required String token,
  }) async {
    final j = await _api.get('${_base(reposteriaId)}/$ventaId', token: token);
    return Venta.fromJson(j['data'] as Map<String, dynamic>);
  }

  Future<Venta> crearDirecta({
    required int reposteriaId,
    required String token,
    int? clienteId,
    String? descuento,
    String? observaciones,
    required List<Map<String, dynamic>> detalles,
  }) async {
    final j = await _api.post(
      _base(reposteriaId),
      token: token,
      body: {
        'cliente_id': clienteId,
        'descuento': descuento,
        'observaciones': observaciones,
        'detalles': detalles,
      },
    );
    return Venta.fromJson(j['data'] as Map<String, dynamic>);
  }

  Future<Venta> desdePedido({
    required int reposteriaId,
    required int pedidoId,
    required String token,
    String? descuento,
    String? observaciones,
  }) async {
    final j = await _api.post(
      '/api/v1/reposterias/$reposteriaId/pedidos/$pedidoId/venta',
      token: token,
      body: {'descuento': descuento, 'observaciones': observaciones},
    );
    return Venta.fromJson(j['data'] as Map<String, dynamic>);
  }

  Future<Venta> anular({
    required int reposteriaId,
    required int ventaId,
    required String token,
  }) async {
    final j = await _api.post(
      '${_base(reposteriaId)}/$ventaId/anular',
      token: token,
    );
    return Venta.fromJson(j['data'] as Map<String, dynamic>);
  }

  Future<Pago> pagar({
    required int reposteriaId,
    required int ventaId,
    required String token,
    required String metodo,
    required String monto,
    String? referencia,
    String? observaciones,
  }) async {
    final j = await _api.post(
      '${_base(reposteriaId)}/$ventaId/pagos',
      token: token,
      body: {
        'metodo': metodo,
        'monto': monto,
        'referencia': referencia,
        'observaciones': observaciones,
      },
    );
    return Pago.fromJson(j['data'] as Map<String, dynamic>);
  }

  Future<void> eliminarPago({
    required int reposteriaId,
    required int ventaId,
    required int pagoId,
    required String token,
  }) => _api.delete(
    '${_base(reposteriaId)}/$ventaId/pagos/$pagoId',
    token: token,
  );
}
