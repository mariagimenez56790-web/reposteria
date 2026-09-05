import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:reposteria_flutter/controllers/pedido_controller.dart';
import 'package:reposteria_flutter/models/cliente.dart';
import 'package:reposteria_flutter/models/pedido.dart';
import 'package:reposteria_flutter/services/api_client.dart';
import 'package:reposteria_flutter/services/api_exception.dart';
import 'package:reposteria_flutter/services/cliente_service.dart';
import 'package:reposteria_flutter/services/pedido_service.dart';
import 'package:reposteria_flutter/widgets/adaptive_layout.dart';

const clienteJson =
    '{"id":4,"nombre":"Ana","telefono":"700","email":"ana@test.com","direccion":"Centro","notas":null,"activo":true}';
const detalleJson =
    '{"id":8,"producto_id":5,"producto_variante_id":null,"nombre_producto":"Torta","nombre_variante":null,"cantidad":2,"precio_unitario":"25.50","subtotal":"51.00"}';
const pedidoJson =
    '{"id":9,"cliente":$clienteJson,"estado":"pendiente","fecha_pedido":"2026-09-05T10:00:00.000000Z","fecha_entrega":null,"observaciones":"Sin azúcar","total":"51.00","detalles":[$detalleJson]}';
String page(String data) =>
    '{"data":[$data],"meta":{"current_page":1,"last_page":1,"total":1}}';

ClienteService clients(Future<http.Response> Function(http.Request) fn) =>
    ClienteService(ApiClient(client: MockClient(fn), baseUrl: 'http://test'));
PedidoService orders(Future<http.Response> Function(http.Request) fn) =>
    PedidoService(ApiClient(client: MockClient(fn), baseUrl: 'http://test'));
http.Response response(String body, [int status = 200]) =>
    http.Response(body, status, headers: {'content-type': 'application/json'});

void main() {
  group('presentación adaptativa', () {
    testWidgets('ancho móvil usa layout móvil', (tester) async {
      tester.view.physicalSize = const Size(390, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      await tester.pumpWidget(
        MaterialApp(
          home: AdaptiveLayout(
            mobile: (_) => const Text('dato compartido'),
            desktop: (_) => const Text('desktop'),
          ),
        ),
      );
      expect(find.byKey(const Key('mobile-layout')), findsOneWidget);
      expect(find.text('dato compartido'), findsOneWidget);
    });

    testWidgets('ancho desktop usa layout desktop con el mismo dato', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(1200, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      await tester.pumpWidget(
        MaterialApp(
          home: AdaptiveLayout(
            mobile: (_) => const Text('mobile'),
            desktop: (_) => const Text('dato compartido'),
          ),
        ),
      );
      expect(find.byKey(const Key('desktop-layout')), findsOneWidget);
      expect(find.text('dato compartido'), findsOneWidget);
    });
  });

  group('clientes', () {
    test('parsea todos los campos reales', () {
      final c = Cliente.fromJson(
        jsonDecode(clienteJson) as Map<String, dynamic>,
      );
      expect(c.nombre, 'Ana');
      expect(c.telefono, '700');
      expect(c.activo, isTrue);
    });
    test('lista y busca dentro de la repostería activa', () async {
      final service = clients((r) async {
        expect(r.url.path, contains('/reposterias/12/clientes'));
        expect(r.url.queryParameters['search'], 'Ana');
        expect(r.headers['authorization'], 'Bearer t');
        return response(page(clienteJson));
      });
      final result = await service.listar(
        reposteriaId: 12,
        token: 't',
        search: 'Ana',
      );
      expect(result.items.single.id, 4);
    });
    test('crea cliente con POST sin inventar tenant en body', () async {
      final service = clients((r) async {
        expect(r.method, 'POST');
        expect(r.url.path, contains('/reposterias/3/clientes'));
        expect(jsonDecode(r.body), {'nombre': 'Ana'});
        return response('{"data":$clienteJson}', 201);
      });
      expect(
        (await service.crear(
          reposteriaId: 3,
          token: 't',
          data: {'nombre': 'Ana'},
        )).nombre,
        'Ana',
      );
    });
    test('edita cliente con PATCH', () async {
      final service = clients((r) async {
        expect(r.method, 'PATCH');
        expect(r.url.path, endsWith('/clientes/4'));
        return response('{"data":$clienteJson}');
      });
      expect(
        (await service.editar(
          reposteriaId: 3,
          clienteId: 4,
          token: 't',
          data: {'telefono': '700'},
        )).id,
        4,
      );
    });
    test('detalle queda aislado por tenant', () async {
      final service = clients((r) async {
        expect(r.url.path, '/api/v1/reposterias/7/clientes/4');
        return response('{"data":$clienteJson}');
      });
      expect(
        (await service.detalle(reposteriaId: 7, clienteId: 4, token: 't')).id,
        4,
      );
    });
    test('propaga errores de validación de clientes', () async {
      final service = clients(
        (_) async => response(
          '{"message":"Datos inválidos","errors":{"email":["Email inválido"]}}',
          422,
        ),
      );
      expect(
        () =>
            service.crear(reposteriaId: 3, token: 't', data: {'nombre': 'Ana'}),
        throwsA(isA<ApiException>().having((e) => e.statusCode, 'status', 422)),
      );
    });
  });

  group('pedidos', () {
    test('parsea pedido, detalle y dinero string', () {
      final p = Pedido.fromJson(jsonDecode(pedidoJson) as Map<String, dynamic>);
      expect(p.estado, 'pendiente');
      expect(p.total, '51.00');
      expect(p.detalles.single.precioUnitario, '25.50');
      expect(p.detalles.single.cantidad, 2);
    });
    test('listado envía filtros reales', () async {
      final service = orders((r) async {
        expect(r.url.queryParameters['estado'], 'confirmado');
        expect(r.url.queryParameters['cliente_id'], '4');
        return response(page(pedidoJson));
      });
      expect(
        (await service.listar(
          reposteriaId: 2,
          token: 't',
          estado: 'confirmado',
          clienteId: 4,
        )).items,
        hasLength(1),
      );
    });
    test('creación envía solo ids, cantidad y cabecera', () async {
      final service = orders((r) async {
        final body = jsonDecode(r.body) as Map<String, dynamic>;
        expect(r.method, 'POST');
        expect(body['detalles'], [
          {'producto_id': 5, 'cantidad': 2},
        ]);
        expect(body.containsKey('total'), isFalse);
        return response('{"data":$pedidoJson}', 201);
      });
      final p = await service.crear(
        reposteriaId: 2,
        token: 't',
        detalles: [
          {'producto_id': 5, 'cantidad': 2},
        ],
      );
      expect(p.total, '51.00');
    });
    test('edición pendiente usa PATCH', () async {
      final service = orders((r) async {
        expect(r.method, 'PATCH');
        expect(r.url.path, endsWith('/pedidos/9'));
        return response('{"data":$pedidoJson}');
      });
      expect(
        (await service.editar(
          reposteriaId: 2,
          pedidoId: 9,
          token: 't',
          observaciones: 'Nueva',
        )).editable,
        isTrue,
      );
    });
    test('detalle usa repostería y pedido correctos', () async {
      final service = orders((r) async {
        expect(r.url.path, '/api/v1/reposterias/6/pedidos/9');
        return response('{"data":$pedidoJson}');
      });
      expect(
        (await service.detalle(
          reposteriaId: 6,
          pedidoId: 9,
          token: 't',
        )).detalles,
        hasLength(1),
      );
    });
    test('agrega, modifica y elimina detalles por rutas reales', () async {
      var calls = 0;
      final service = orders((r) async {
        calls++;
        if (calls == 1) {
          expect(r.method, 'POST');
          expect(r.url.path, endsWith('/pedidos/9/detalles'));
          return response('{"data":$detalleJson}', 201);
        }
        if (calls == 2) {
          expect(r.method, 'PATCH');
          expect(r.url.path, endsWith('/detalles/8'));
          return response('{"data":$detalleJson}');
        }
        expect(r.method, 'DELETE');
        return response('', 204);
      });
      await service.agregarDetalle(
        reposteriaId: 2,
        pedidoId: 9,
        token: 't',
        productoId: 5,
        cantidad: 2,
      );
      await service.editarDetalle(
        reposteriaId: 2,
        pedidoId: 9,
        detalleId: 8,
        token: 't',
        cantidad: 3,
      );
      await service.eliminarDetalle(
        reposteriaId: 2,
        pedidoId: 9,
        detalleId: 8,
        token: 't',
      );
      expect(calls, 3);
    });
    test('cambio de estado usa endpoint explícito', () async {
      final service = orders((r) async {
        expect(r.url.path, endsWith('/pedidos/9/estado'));
        expect(jsonDecode(r.body), {'estado': 'confirmado'});
        return response('{"data":$pedidoJson}');
      });
      await service.cambiarEstado(
        reposteriaId: 2,
        pedidoId: 9,
        token: 't',
        estado: 'confirmado',
      );
    });
    test('permisos visuales respetan rol y transición', () {
      final controller = PedidoController(
        orders((_) async => response('{}')),
        onUnauthorized: () async {},
      );
      final p = Pedido.fromJson(jsonDecode(pedidoJson) as Map<String, dynamic>);
      expect(controller.transiciones(p, 'vendedor'), [
        'confirmado',
        'cancelado',
      ]);
      expect(controller.transiciones(p, 'produccion'), isEmpty);
      expect(controller.transiciones(p, 'cliente'), isEmpty);
    });
    test('aislamiento usa siempre repostería recibida', () async {
      final service = orders((r) async {
        expect(r.url.path, startsWith('/api/v1/reposterias/77/'));
        return response(page(pedidoJson));
      });
      await service.listar(reposteriaId: 77, token: 't');
    });
    test('propaga errores de permisos de pedidos', () async {
      final service = orders(
        (_) async => response('{"message":"Prohibido"}', 403),
      );
      expect(
        () => service.listar(reposteriaId: 2, token: 't'),
        throwsA(isA<ApiException>().having((e) => e.statusCode, 'status', 403)),
      );
    });
  });
}
