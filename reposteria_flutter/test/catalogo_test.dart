import 'dart:async';
import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:reposteria_flutter/controllers/catalogo_controller.dart';
import 'package:reposteria_flutter/models/categoria.dart';
import 'package:reposteria_flutter/models/producto.dart';
import 'package:reposteria_flutter/models/producto_variante.dart';
import 'package:reposteria_flutter/services/api_client.dart';
import 'package:reposteria_flutter/services/catalogo_service.dart';

const categoriaJson =
    '{"id":3,"nombre":"Tortas","descripcion":null,"activo":true}';
const promocionJson =
    '{"id":7,"nombre":"Promo","tipo":"porcentaje","valor":"10.00","descuento":"2.55","fecha_inicio":"2026-01-01T00:00:00.000000Z","fecha_fin":"2026-12-31T23:59:59.000000Z"}';
const productoBase =
    '"id":5,"categoria_id":3,"nombre":"Chocolate","descripcion":"Deliciosa","precio":"25.50","precio_final":"22.95","imagen":null,"personalizable":true,"maneja_stock":true,"stock":4,"tiene_variantes":false';
const varianteJson =
    '{"id":9,"nombre":"Grande","precio":"30.00","precio_final":"27.00","stock":2,"promocion":$promocionJson}';
const productoJson = '{$productoBase,"promocion":$promocionJson}';

http.Response jsonResponse(String body, [int status = 200]) =>
    http.Response(body, status, headers: {'content-type': 'application/json'});

String pageResponse({
  String data = '[$productoJson]',
  int current = 1,
  int last = 1,
  int total = 1,
}) =>
    '{"data":$data,"meta":{"current_page":$current,"last_page":$last,"total":$total}}';

CatalogoService serviceWith(
  Future<http.Response> Function(http.Request) handler,
) => CatalogoService(
  ApiClient(client: MockClient(handler), baseUrl: 'http://test'),
);

void main() {
  group('modelos del catálogo', () {
    test('parsea categoría', () {
      final categoria = Categoria.fromJson({
        'id': 3,
        'nombre': 'Tortas',
        'descripcion': null,
        'activo': true,
      });
      expect(categoria.nombre, 'Tortas');
      expect(categoria.activo, isTrue);
    });

    test('parsea producto y conserva precio string', () {
      final producto = Producto.fromJson(_decode(productoJson));
      expect(producto.precio, '25.50');
      expect(producto.precioFinal, '22.95');
      expect(producto.personalizable, isTrue);
    });

    test('parsea promoción calculada por Laravel', () {
      final producto = Producto.fromJson(_decode(productoJson));
      expect(producto.promocion?.nombre, 'Promo');
      expect(producto.promocion?.descuento, '2.55');
    });

    test('producto sin variantes produce lista vacía', () {
      expect(Producto.fromJson(_decode(productoJson)).variantes, isEmpty);
    });

    test('parsea variante con precio y promoción', () {
      final variante = ProductoVariante.fromJson(_decode(varianteJson));
      expect(variante.precio, '30.00');
      expect(variante.precioFinal, '27.00');
      expect(variante.promocion?.tipo, 'porcentaje');
    });

    test('producto con variantes las conserva', () {
      final producto = Producto.fromJson(
        _decode('{$productoBase,"promocion":null,"variantes":[$varianteJson]}'),
      );
      expect(producto.variantes, hasLength(1));
      expect(producto.variantes.single.nombre, 'Grande');
    });
  });

  group('servicio y controlador', () {
    test('lista vacía es éxito y no error', () async {
      final service = serviceWith(
        (request) async => jsonResponse(pageResponse(data: '[]', total: 0)),
      );
      final page = await service.productos(reposteriaId: 2, token: 't');
      expect(page.productos, isEmpty);
    });

    test('estado loading se publica mientras carga', () async {
      final pending = Completer<http.Response>();
      final controller = CatalogoController(
        serviceWith((_) => pending.future),
        onUnauthorized: () async {},
      );
      final future = controller.load(reposteriaId: 2, token: 't');
      expect(controller.status, CatalogoStatus.loading);
      pending.complete(
        jsonResponse(
          '{"data":[],"meta":{"current_page":1,"last_page":1,"total":0}}',
        ),
      );
      await future;
    });

    test('error 403 queda visible mediante estado y mensaje', () async {
      final controller = CatalogoController(
        serviceWith((_) async => jsonResponse('{"message":"Forbidden"}', 403)),
        onUnauthorized: () async {},
      );
      await controller.load(reposteriaId: 2, token: 't');
      expect(controller.status, CatalogoStatus.error);
      expect(controller.error, contains('No tienes acceso'));
    });

    test('búsqueda se envía a Laravel', () async {
      String? search;
      final service = serviceWith((request) async {
        search = request.url.queryParameters['search'];
        return jsonResponse(pageResponse(data: '[]', total: 0));
      });
      await service.productos(
        reposteriaId: 2,
        token: 't',
        search: ' chocolate ',
      );
      expect(search, 'chocolate');
    });

    test('filtro de categoría se envía a Laravel', () async {
      String? categoria;
      final service = serviceWith((request) async {
        categoria = request.url.queryParameters['categoria_id'];
        return jsonResponse(pageResponse(data: '[]', total: 0));
      });
      await service.productos(reposteriaId: 2, token: 't', categoriaId: 3);
      expect(categoria, '3');
    });

    test('combina búsqueda y categoría en la misma petición', () async {
      final service = serviceWith((request) async {
        expect(request.url.queryParameters['search'], 'chocolate');
        expect(request.url.queryParameters['categoria_id'], '3');
        return jsonResponse(pageResponse(data: '[]', total: 0));
      });
      await service.productos(
        reposteriaId: 2,
        token: 't',
        search: 'chocolate',
        categoriaId: 3,
      );
    });

    test('usa la repostería activa recibida y Bearer token', () async {
      final service = serviceWith((request) async {
        expect(request.url.path, contains('/reposterias/42/productos'));
        expect(request.headers['authorization'], 'Bearer token');
        return jsonResponse(pageResponse(data: '[]', total: 0));
      });
      await service.productos(reposteriaId: 42, token: 'token');
    });

    test('cambio de repostería limpia datos antes de responder', () async {
      final second = Completer<http.Response>();
      final service = serviceWith((request) async {
        if (request.url.path.contains('/reposterias/2/')) return second.future;
        return request.url.path.endsWith('/categorias')
            ? jsonResponse('{"data":[$categoriaJson]}')
            : jsonResponse(pageResponse());
      });
      final controller = CatalogoController(
        service,
        onUnauthorized: () async {},
      );
      await controller.load(reposteriaId: 1, token: 't');
      expect(controller.productos, isNotEmpty);
      final changing = controller.load(reposteriaId: 2, token: 't');
      expect(controller.productos, isEmpty);
      second.complete(
        jsonResponse(
          '{"data":[],"meta":{"current_page":1,"last_page":1,"total":0}}',
        ),
      );
      await changing;
    });

    test('detalle consulta producto dentro del tenant', () async {
      final service = serviceWith((request) async {
        expect(request.url.path, '/api/v1/reposterias/8/productos/5');
        return jsonResponse(
          '{"data":{$productoBase,"promocion":null,"variantes":[]}}',
        );
      });
      final product = await service.producto(
        reposteriaId: 8,
        productoId: 5,
        token: 't',
      );
      expect(product.id, 5);
    });

    test('cargar más agrega la siguiente página', () async {
      final service = serviceWith((request) async {
        if (request.url.path.endsWith('/categorias')) {
          return jsonResponse('{"data":[]}');
        }
        final page = request.url.queryParameters['page'];
        return jsonResponse(
          pageResponse(current: int.parse(page ?? '1'), last: 2, total: 2),
        );
      });
      final controller = CatalogoController(
        service,
        onUnauthorized: () async {},
      );
      await controller.load(reposteriaId: 1, token: 't');
      await controller.loadMore();
      expect(controller.productos, hasLength(2));
    });

    test('401 notifica expiración de sesión', () async {
      var expired = false;
      final controller = CatalogoController(
        serviceWith(
          (_) async => jsonResponse('{"message":"Unauthenticated."}', 401),
        ),
        onUnauthorized: () async => expired = true,
      );
      await controller.load(reposteriaId: 1, token: 't');
      expect(expired, isTrue);
    });
  });
}

Map<String, dynamic> _decode(String source) =>
    jsonDecode(source) as Map<String, dynamic>;
