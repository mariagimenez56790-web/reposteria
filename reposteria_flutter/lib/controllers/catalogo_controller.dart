import 'package:flutter/foundation.dart';

import '../models/categoria.dart';
import '../models/producto.dart';
import '../services/api_exception.dart';
import '../services/catalogo_service.dart';

enum CatalogoStatus { initial, loading, success, error }

class CatalogoController extends ChangeNotifier {
  CatalogoController(this._service, {required this.onUnauthorized});

  final CatalogoService _service;
  final AsyncCallback onUnauthorized;

  CatalogoStatus status = CatalogoStatus.initial;
  List<Categoria> categorias = const [];
  List<Producto> productos = const [];
  int? categoriaId;
  String search = '';
  String? error;
  bool loadingMore = false;
  int _pagina = 1;
  bool _tieneMas = false;
  int? _reposteriaId;
  String? _token;
  int _requestVersion = 0;

  bool get tieneMas => _tieneMas;

  Future<void> load({required int reposteriaId, required String token}) async {
    _reposteriaId = reposteriaId;
    _token = token;
    final version = ++_requestVersion;
    _clearData();
    status = CatalogoStatus.loading;
    notifyListeners();
    try {
      final results = await Future.wait([
        _service.categorias(reposteriaId: reposteriaId, token: token),
        _service.productos(reposteriaId: reposteriaId, token: token),
      ]);
      if (version != _requestVersion) return;
      categorias = results[0] as List<Categoria>;
      final pagina = results[1] as PaginaProductos;
      productos = pagina.productos;
      _pagina = pagina.paginaActual;
      _tieneMas = pagina.tieneMas;
      status = CatalogoStatus.success;
    } on ApiException catch (exception) {
      if (version != _requestVersion) return;
      await _handleError(exception);
    }
    if (version == _requestVersion) notifyListeners();
  }

  Future<void> retry() async {
    if (_reposteriaId != null && _token != null) {
      await load(reposteriaId: _reposteriaId!, token: _token!);
    }
  }

  Future<void> setSearch(String value) async {
    search = value.trim();
    await _reloadProducts();
  }

  Future<void> setCategoria(int? value) async {
    categoriaId = value;
    await _reloadProducts();
  }

  Future<void> loadMore() async {
    if (!_tieneMas || loadingMore || _reposteriaId == null || _token == null) {
      return;
    }
    final version = _requestVersion;
    loadingMore = true;
    notifyListeners();
    try {
      final pagina = await _service.productos(
        reposteriaId: _reposteriaId!,
        token: _token!,
        page: _pagina + 1,
        search: search,
        categoriaId: categoriaId,
      );
      if (version != _requestVersion) return;
      productos = [...productos, ...pagina.productos];
      _pagina = pagina.paginaActual;
      _tieneMas = pagina.tieneMas;
    } on ApiException catch (exception) {
      if (version == _requestVersion) await _handleError(exception);
    } finally {
      if (version == _requestVersion) {
        loadingMore = false;
        notifyListeners();
      }
    }
  }

  void clear() {
    ++_requestVersion;
    _reposteriaId = null;
    _token = null;
    _clearData();
    status = CatalogoStatus.initial;
    notifyListeners();
  }

  Future<void> _reloadProducts() async {
    if (_reposteriaId == null || _token == null) return;
    final version = ++_requestVersion;
    productos = const [];
    error = null;
    status = CatalogoStatus.loading;
    notifyListeners();
    try {
      final pagina = await _service.productos(
        reposteriaId: _reposteriaId!,
        token: _token!,
        search: search,
        categoriaId: categoriaId,
      );
      if (version != _requestVersion) return;
      productos = pagina.productos;
      _pagina = pagina.paginaActual;
      _tieneMas = pagina.tieneMas;
      status = CatalogoStatus.success;
    } on ApiException catch (exception) {
      if (version != _requestVersion) return;
      await _handleError(exception);
    }
    if (version == _requestVersion) notifyListeners();
  }

  Future<void> _handleError(ApiException exception) async {
    error = switch (exception.statusCode) {
      403 => 'No tienes acceso al catálogo de esta repostería.',
      404 => 'El recurso solicitado ya no está disponible.',
      422 => 'Los filtros enviados no son válidos.',
      _ => exception.message,
    };
    status = CatalogoStatus.error;
    if (exception.statusCode == 401) await onUnauthorized();
  }

  void _clearData() {
    categorias = const [];
    productos = const [];
    categoriaId = null;
    search = '';
    error = null;
    loadingMore = false;
    _pagina = 1;
    _tieneMas = false;
  }
}
