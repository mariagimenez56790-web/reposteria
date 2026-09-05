import 'package:flutter/foundation.dart';
import '../models/pedido.dart';
import '../services/api_exception.dart';
import '../services/pedido_service.dart';
import 'cliente_controller.dart';

class PedidoController extends ChangeNotifier {
  PedidoController(this._service, {required this.onUnauthorized});
  final PedidoService _service;
  final AsyncCallback onUnauthorized;
  DataStatus status = DataStatus.initial;
  List<Pedido> pedidos = const [];
  String? estado;
  int? clienteId;
  String? error;
  bool saving = false;
  int? _tenant;
  String? _token;
  int _version = 0;

  Future<void> load({required int reposteriaId, required String token}) async {
    _tenant = reposteriaId;
    _token = token;
    final version = ++_version;
    pedidos = const [];
    error = null;
    status = DataStatus.loading;
    notifyListeners();
    try {
      final page = await _service.listar(
        reposteriaId: reposteriaId,
        token: token,
        estado: estado,
        clienteId: clienteId,
      );
      if (version != _version) return;
      pedidos = page.items;
      status = DataStatus.success;
    } on ApiException catch (e) {
      if (version == _version) await _fail(e);
    }
    if (version == _version) notifyListeners();
  }

  Future<void> setEstado(String? value) async {
    estado = value;
    await retry();
  }

  Future<void> setCliente(int? value) async {
    clienteId = value;
    await retry();
  }

  Future<void> retry() async {
    if (_tenant != null && _token != null) {
      await load(reposteriaId: _tenant!, token: _token!);
    }
  }

  Future<Pedido?> detalle(int id) async {
    if (_tenant == null || _token == null) return null;
    try {
      return await _service.detalle(
        reposteriaId: _tenant!,
        pedidoId: id,
        token: _token!,
      );
    } on ApiException catch (e) {
      await _fail(e);
      notifyListeners();
      return null;
    }
  }

  Future<bool> crear({
    int? clienteId,
    DateTime? fechaEntrega,
    String? observaciones,
    required List<Map<String, dynamic>> detalles,
  }) async => _mutate(
    () => _service.crear(
      reposteriaId: _tenant!,
      token: _token!,
      clienteId: clienteId,
      fechaEntrega: fechaEntrega,
      observaciones: observaciones,
      detalles: detalles,
    ),
  );

  Future<bool> editar(
    int pedidoId, {
    int? clienteId,
    DateTime? fechaEntrega,
    String? observaciones,
  }) async => _mutate(
    () => _service.editar(
      reposteriaId: _tenant!,
      pedidoId: pedidoId,
      token: _token!,
      clienteId: clienteId,
      fechaEntrega: fechaEntrega,
      observaciones: observaciones,
    ),
  );

  Future<bool> agregarDetalle(
    int pedidoId, {
    required int productoId,
    int? varianteId,
    required int cantidad,
  }) async => _mutate(
    () => _service.agregarDetalle(
      reposteriaId: _tenant!,
      pedidoId: pedidoId,
      token: _token!,
      productoId: productoId,
      varianteId: varianteId,
      cantidad: cantidad,
    ),
  );

  Future<bool> editarDetalle(int pedidoId, int detalleId, int cantidad) async =>
      _mutate(
        () => _service.editarDetalle(
          reposteriaId: _tenant!,
          pedidoId: pedidoId,
          detalleId: detalleId,
          token: _token!,
          cantidad: cantidad,
        ),
      );

  Future<bool> eliminarDetalle(int pedidoId, int detalleId) async => _mutate(
    () => _service.eliminarDetalle(
      reposteriaId: _tenant!,
      pedidoId: pedidoId,
      detalleId: detalleId,
      token: _token!,
    ),
  );

  Future<bool> cambiarEstado(int pedidoId, String value) async => _mutate(
    () => _service.cambiarEstado(
      reposteriaId: _tenant!,
      pedidoId: pedidoId,
      token: _token!,
      estado: value,
    ),
  );

  List<String> transiciones(Pedido pedido, String? role) {
    final allowed = switch (pedido.estado) {
      'pendiente' => ['confirmado', 'cancelado'],
      'confirmado' => ['en_produccion', 'cancelado'],
      'en_produccion' => ['listo'],
      'listo' => ['entregado'],
      _ => <String>[],
    };
    if (role == 'admin' || role == 'superadmin') return allowed;
    if (role == 'vendedor') {
      return allowed
          .where((e) => e == 'confirmado' || e == 'cancelado')
          .toList();
    }
    if (role == 'produccion') {
      return allowed
          .where((e) => e == 'en_produccion' || e == 'listo')
          .toList();
    }
    return const [];
  }

  Future<bool> _mutate(Future<Object?> Function() action) async {
    if (_tenant == null || _token == null) return false;
    saving = true;
    error = null;
    notifyListeners();
    try {
      await action();
      saving = false;
      await retry();
      return true;
    } on ApiException catch (e) {
      await _fail(e);
      saving = false;
      notifyListeners();
      return false;
    }
  }

  void clear() {
    ++_version;
    _tenant = null;
    _token = null;
    pedidos = const [];
    estado = null;
    clienteId = null;
    error = null;
    status = DataStatus.initial;
    notifyListeners();
  }

  Future<void> _fail(ApiException e) async {
    error = e.statusCode == 403
        ? 'No tienes permiso para esta acción.'
        : e.statusCode == 404
        ? 'El pedido no existe.'
        : e.statusCode == 422
        ? (e.errors.values.firstOrNull is List
              ? '${(e.errors.values.first as List).first}'
              : e.message)
        : e.message;
    status = DataStatus.error;
    if (e.statusCode == 401) await onUnauthorized();
  }
}
