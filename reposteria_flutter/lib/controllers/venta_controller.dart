import 'package:flutter/foundation.dart';
import '../models/venta.dart';
import '../services/api_exception.dart';
import '../services/venta_service.dart';
import 'cliente_controller.dart';

class VentaController extends ChangeNotifier {
  VentaController(this._service, {required this.onUnauthorized});
  final VentaService _service;
  final AsyncCallback onUnauthorized;
  DataStatus status = DataStatus.initial;
  List<Venta> ventas = const [];
  Venta? selected;
  String? estado;
  int? clienteId;
  int? pedidoId;
  String? error;
  bool saving = false;
  int? _tenant;
  String? _token;
  int _version = 0;
  bool canOperate(String? role) =>
      const ['admin', 'vendedor', 'superadmin'].contains(role);
  bool canAdmin(String? role) => const ['admin', 'superadmin'].contains(role);

  Future<void> load({required int reposteriaId, required String token}) async {
    _tenant = reposteriaId;
    _token = token;
    final v = ++_version;
    ventas = const [];
    selected = null;
    error = null;
    status = DataStatus.loading;
    notifyListeners();
    try {
      final p = await _service.listar(
        reposteriaId: reposteriaId,
        token: token,
        estado: estado,
        clienteId: clienteId,
        pedidoId: pedidoId,
      );
      if (v != _version) return;
      ventas = p.items;
      status = DataStatus.success;
    } on ApiException catch (e) {
      if (v == _version) await _fail(e);
    }
    if (v == _version) notifyListeners();
  }

  Future<void> retry() async {
    if (_tenant != null && _token != null) {
      await load(reposteriaId: _tenant!, token: _token!);
    }
  }

  Future<void> setEstado(String? value) async {
    estado = value;
    await retry();
  }

  Future<void> setCliente(int? value) async {
    clienteId = value;
    await retry();
  }

  Future<Venta?> detalle(int id) async {
    if (_tenant == null || _token == null) return null;
    try {
      selected = await _service.detalle(
        reposteriaId: _tenant!,
        ventaId: id,
        token: _token!,
      );
      notifyListeners();
      return selected;
    } on ApiException catch (e) {
      await _fail(e);
      notifyListeners();
      return null;
    }
  }

  Future<Venta?> crearDirecta({
    int? clienteId,
    String? descuento,
    String? observaciones,
    required List<Map<String, dynamic>> detalles,
  }) => _mutation(
    () => _service.crearDirecta(
      reposteriaId: _tenant!,
      token: _token!,
      clienteId: clienteId,
      descuento: descuento,
      observaciones: observaciones,
      detalles: detalles,
    ),
  );
  Future<Venta?> desdePedido(
    int id, {
    String? descuento,
    String? observaciones,
  }) => _mutation(
    () => _service.desdePedido(
      reposteriaId: _tenant!,
      pedidoId: id,
      token: _token!,
      descuento: descuento,
      observaciones: observaciones,
    ),
  );
  Future<bool> pagar(
    int id, {
    required String metodo,
    required String monto,
    String? referencia,
    String? observaciones,
  }) async {
    final result = await _mutation(() async {
      await _service.pagar(
        reposteriaId: _tenant!,
        ventaId: id,
        token: _token!,
        metodo: metodo,
        monto: monto,
        referencia: referencia,
        observaciones: observaciones,
      );
      return _service.detalle(
        reposteriaId: _tenant!,
        ventaId: id,
        token: _token!,
      );
    });
    return result != null;
  }

  Future<bool> eliminarPago(int ventaId, int pagoId) async {
    final result = await _mutation(() async {
      await _service.eliminarPago(
        reposteriaId: _tenant!,
        ventaId: ventaId,
        pagoId: pagoId,
        token: _token!,
      );
      return _service.detalle(
        reposteriaId: _tenant!,
        ventaId: ventaId,
        token: _token!,
      );
    });
    return result != null;
  }

  Future<bool> anular(int id) async {
    final result = await _mutation(() async {
      await _service.anular(
        reposteriaId: _tenant!,
        ventaId: id,
        token: _token!,
      );
      return _service.detalle(
        reposteriaId: _tenant!,
        ventaId: id,
        token: _token!,
      );
    });
    return result != null;
  }

  Future<Venta?> _mutation(Future<Venta> Function() action) async {
    if (_tenant == null || _token == null) return null;
    saving = true;
    error = null;
    notifyListeners();
    try {
      final result = await action();
      selected = result;
      saving = false;
      await retry();
      selected = result;
      notifyListeners();
      return result;
    } on ApiException catch (e) {
      await _fail(e);
      saving = false;
      notifyListeners();
      return null;
    }
  }

  void clear() {
    ++_version;
    _tenant = null;
    _token = null;
    ventas = const [];
    selected = null;
    estado = null;
    clienteId = null;
    pedidoId = null;
    error = null;
    status = DataStatus.initial;
    notifyListeners();
  }

  Future<void> _fail(ApiException e) async {
    error = switch (e.statusCode) {
      403 => 'No tienes permiso para operar ventas.',
      404 => 'La venta, pago o recurso ya no está disponible.',
      422 => _validation(e),
      _ => e.message,
    };
    status = DataStatus.error;
    if (e.statusCode == 401) await onUnauthorized();
  }

  String _validation(ApiException e) {
    if (e.errors.isEmpty) return e.message;
    final value = e.errors.values.first;
    return value is List && value.isNotEmpty ? '${value.first}' : e.message;
  }
}
