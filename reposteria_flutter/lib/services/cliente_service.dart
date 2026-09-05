import '../models/cliente.dart';
import 'api_client.dart';

class PaginaClientes {
  const PaginaClientes(this.items, this.currentPage, this.lastPage);
  final List<Cliente> items;
  final int currentPage;
  final int lastPage;
  bool get hasMore => currentPage < lastPage;
}

class ClienteService {
  const ClienteService(this._api);
  final ApiClient _api;

  Future<PaginaClientes> listar({
    required int reposteriaId,
    required String token,
    String search = '',
    int page = 1,
  }) async {
    final uri = Uri(
      path: '/api/v1/reposterias/$reposteriaId/clientes',
      queryParameters: {
        'page': '$page',
        'per_page': '15',
        if (search.trim().isNotEmpty) 'search': search.trim(),
      },
    );
    final json = await _api.get(uri.toString(), token: token);
    final meta = json['meta'] as Map<String, dynamic>;
    return PaginaClientes(
      (json['data'] as List)
          .map((e) => Cliente.fromJson(e as Map<String, dynamic>))
          .toList(growable: false),
      meta['current_page'] as int,
      meta['last_page'] as int,
    );
  }

  Future<Cliente> detalle({
    required int reposteriaId,
    required int clienteId,
    required String token,
  }) async {
    final json = await _api.get(
      '/api/v1/reposterias/$reposteriaId/clientes/$clienteId',
      token: token,
    );
    return Cliente.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<Cliente> crear({
    required int reposteriaId,
    required String token,
    required Map<String, dynamic> data,
  }) async {
    final json = await _api.post(
      '/api/v1/reposterias/$reposteriaId/clientes',
      token: token,
      body: data,
    );
    return Cliente.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<Cliente> editar({
    required int reposteriaId,
    required int clienteId,
    required String token,
    required Map<String, dynamic> data,
  }) async {
    final json = await _api.patch(
      '/api/v1/reposterias/$reposteriaId/clientes/$clienteId',
      token: token,
      body: data,
    );
    return Cliente.fromJson(json['data'] as Map<String, dynamic>);
  }
}
