import 'package:flutter/foundation.dart';
import '../models/cliente.dart';
import '../services/api_exception.dart';
import '../services/cliente_service.dart';

enum DataStatus { initial, loading, success, error }

class ClienteController extends ChangeNotifier {
  ClienteController(this._service, {required this.onUnauthorized});
  final ClienteService _service;
  final AsyncCallback onUnauthorized;
  DataStatus status = DataStatus.initial;
  List<Cliente> clientes = const [];
  String search = '';
  String? error;
  bool saving = false;
  int? _tenant;
  String? _token;
  int _version = 0;

  Future<void> load({required int reposteriaId, required String token}) async {
    _tenant = reposteriaId;
    _token = token;
    final version = ++_version;
    clientes = const [];
    error = null;
    status = DataStatus.loading;
    notifyListeners();
    try {
      final page = await _service.listar(
        reposteriaId: reposteriaId,
        token: token,
        search: search,
      );
      if (version != _version) return;
      clientes = page.items;
      status = DataStatus.success;
    } on ApiException catch (e) {
      if (version == _version) await _fail(e);
    }
    if (version == _version) notifyListeners();
  }

  Future<void> setSearch(String value) async {
    search = value.trim();
    if (_tenant != null && _token != null) {
      await load(reposteriaId: _tenant!, token: _token!);
    }
  }

  Future<void> retry() async {
    if (_tenant != null && _token != null) {
      await load(reposteriaId: _tenant!, token: _token!);
    }
  }

  Future<Cliente?> detalle(int clienteId) async {
    if (_tenant == null || _token == null) return null;
    try {
      return await _service.detalle(
        reposteriaId: _tenant!,
        clienteId: clienteId,
        token: _token!,
      );
    } on ApiException catch (e) {
      await _fail(e);
      notifyListeners();
      return null;
    }
  }

  Future<bool> save(Map<String, dynamic> data, {int? clienteId}) async {
    if (_tenant == null || _token == null) return false;
    saving = true;
    error = null;
    notifyListeners();
    try {
      if (clienteId == null) {
        await _service.crear(
          reposteriaId: _tenant!,
          token: _token!,
          data: data,
        );
      } else {
        await _service.editar(
          reposteriaId: _tenant!,
          clienteId: clienteId,
          token: _token!,
          data: data,
        );
      }
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
    clientes = const [];
    search = '';
    error = null;
    status = DataStatus.initial;
    notifyListeners();
  }

  Future<void> _fail(ApiException e) async {
    error = e.statusCode == 403
        ? 'No tienes permiso para administrar clientes.'
        : e.statusCode == 404
        ? 'El cliente no existe.'
        : e.statusCode == 422
        ? _validation(e)
        : e.message;
    status = DataStatus.error;
    if (e.statusCode == 401) await onUnauthorized();
  }

  String _validation(ApiException e) {
    if (e.errors.isEmpty) return e.message;
    final first = e.errors.values.first;
    return first is List && first.isNotEmpty ? '${first.first}' : e.message;
  }
}
