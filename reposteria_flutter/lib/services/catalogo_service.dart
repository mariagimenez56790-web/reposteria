import '../models/categoria.dart';
import '../models/producto.dart';
import 'api_client.dart';

class PaginaProductos {
  const PaginaProductos({
    required this.productos,
    required this.paginaActual,
    required this.ultimaPagina,
    required this.total,
  });

  final List<Producto> productos;
  final int paginaActual;
  final int ultimaPagina;
  final int total;

  bool get tieneMas => paginaActual < ultimaPagina;
}

class CatalogoService {
  const CatalogoService(this._api);
  final ApiClient _api;

  Future<List<Categoria>> categorias({
    required int reposteriaId,
    required String token,
  }) async {
    final response = await _api.get(
      '/api/v1/reposterias/$reposteriaId/categorias',
      token: token,
    );
    return (response['data'] as List<dynamic>)
        .map((item) => Categoria.fromJson(item as Map<String, dynamic>))
        .toList(growable: false);
  }

  Future<PaginaProductos> productos({
    required int reposteriaId,
    required String token,
    int page = 1,
    int perPage = 12,
    String search = '',
    int? categoriaId,
  }) async {
    final query = <String, String>{
      'page': '$page',
      'per_page': '$perPage',
      if (search.trim().isNotEmpty) 'search': search.trim(),
      if (categoriaId != null) 'categoria_id': '$categoriaId',
    };
    final uri = Uri(
      path: '/api/v1/reposterias/$reposteriaId/productos',
      queryParameters: query,
    );
    final response = await _api.get(uri.toString(), token: token);
    final meta = response['meta'] as Map<String, dynamic>;
    return PaginaProductos(
      productos: (response['data'] as List<dynamic>)
          .map((item) => Producto.fromJson(item as Map<String, dynamic>))
          .toList(growable: false),
      paginaActual: meta['current_page'] as int,
      ultimaPagina: meta['last_page'] as int,
      total: meta['total'] as int,
    );
  }

  Future<Producto> producto({
    required int reposteriaId,
    required int productoId,
    required String token,
  }) async {
    final response = await _api.get(
      '/api/v1/reposterias/$reposteriaId/productos/$productoId',
      token: token,
    );
    return Producto.fromJson(response['data'] as Map<String, dynamic>);
  }
}
