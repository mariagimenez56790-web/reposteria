import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:reposteria_flutter/controllers/cliente_controller.dart';
import 'package:reposteria_flutter/controllers/venta_controller.dart';
import 'package:reposteria_flutter/models/venta.dart';
import 'package:reposteria_flutter/services/api_client.dart';
import 'package:reposteria_flutter/services/api_exception.dart';
import 'package:reposteria_flutter/services/venta_service.dart';
import 'package:reposteria_flutter/widgets/adaptive_layout.dart';

const pagoJson =
    '{"id":3,"metodo":"qr","monto":"40.00","fecha_pago":"2026-09-05T12:00:00.000000Z","referencia":"QR-1","observaciones":null}';
const detalleJson =
    '{"id":2,"producto_id":5,"producto_variante_id":8,"nombre_producto":"Torta","nombre_variante":"Grande","cantidad":2,"precio_unitario":"50.00","subtotal":"100.00"}';
const ventaJson =
    '{"id":7,"cliente":null,"pedido":{"id":9,"estado":"listo"},"estado":"parcial","fecha_venta":"2026-09-05T10:00:00.000000Z","subtotal":"100.00","descuento":"10.00","total":"90.00","monto_pagado":"40.00","saldo":"50.00","observaciones":null,"detalles":[$detalleJson],"pagos":[$pagoJson]}';
String page([String data = ventaJson]) =>
    '{"data":[$data],"meta":{"current_page":1,"last_page":1,"total":1}}';
http.Response response(String body, [int status = 200]) =>
    http.Response(body, status, headers: {'content-type': 'application/json'});
VentaService service(Future<http.Response> Function(http.Request) fn) =>
    VentaService(ApiClient(client: MockClient(fn), baseUrl: 'http://test'));

void main() {
  group('modelos venta y pago', () {
    test('parsea venta y estados', () {
      final v = Venta.fromJson(jsonDecode(ventaJson) as Map<String, dynamic>);
      expect(v.estado, 'parcial');
      expect(v.pedido?.id, 9);
      expect(v.anulada, isFalse);
    });
    test('conserva todos los montos como string', () {
      final v = Venta.fromJson(jsonDecode(ventaJson) as Map<String, dynamic>);
      expect(v.subtotal, '100.00');
      expect(v.descuento, '10.00');
      expect(v.total, '90.00');
      expect(v.saldo, '50.00');
    });
    test('parsea detalle histórico', () {
      final v = Venta.fromJson(jsonDecode(ventaJson) as Map<String, dynamic>);
      expect(v.detalles.single.nombreVariante, 'Grande');
      expect(v.detalles.single.precioUnitario, '50.00');
    });
    test('parsea método y fecha de pago', () {
      final p = Pago.fromJson(jsonDecode(pagoJson) as Map<String, dynamic>);
      expect(p.metodo, 'qr');
      expect(p.monto, '40.00');
      expect(p.fechaPago.isUtc, isTrue);
    });
  });
  group('VentaService', () {
    test('lista con tenant Bearer y filtros reales', () async {
      final s = service((r) async {
        expect(r.url.path, contains('/reposterias/12/ventas'));
        expect(r.headers['authorization'], 'Bearer t');
        expect(r.url.queryParameters['estado'], 'parcial');
        expect(r.url.queryParameters['cliente_id'], '4');
        expect(r.url.queryParameters['pedido_id'], '9');
        return response(page());
      });
      expect(
        (await s.listar(
          reposteriaId: 12,
          token: 't',
          estado: 'parcial',
          clienteId: 4,
          pedidoId: 9,
        )).items.single.id,
        7,
      );
    });
    test('detalle usa ruta real', () async {
      final s = service((r) async {
        expect(r.url.path, '/api/v1/reposterias/2/ventas/7');
        return response('{"data":$ventaJson}');
      });
      expect((await s.detalle(reposteriaId: 2, ventaId: 7, token: 't')).id, 7);
    });
    test('venta directa no envía valores financieros calculados', () async {
      final s = service((r) async {
        final b = jsonDecode(r.body) as Map<String, dynamic>;
        expect(r.method, 'POST');
        expect(b['detalles'], [
          {'producto_id': 5, 'cantidad': 2},
        ]);
        expect(b.containsKey('total'), isFalse);
        return response('{"data":$ventaJson}', 201);
      });
      await s.crearDirecta(
        reposteriaId: 2,
        token: 't',
        detalles: [
          {'producto_id': 5, 'cantidad': 2},
        ],
      );
    });
    test('venta desde pedido usa endpoint singular', () async {
      final s = service((r) async {
        expect(r.url.path, '/api/v1/reposterias/2/pedidos/9/venta');
        return response('{"data":$ventaJson}', 201);
      });
      await s.desdePedido(reposteriaId: 2, pedidoId: 9, token: 't');
    });
    test('pago usa método monto referencia y endpoint real', () async {
      final s = service((r) async {
        expect(r.url.path, endsWith('/ventas/7/pagos'));
        expect(jsonDecode(r.body), {
          'metodo': 'qr',
          'monto': '40.00',
          'referencia': 'QR-1',
          'observaciones': null,
        });
        return response('{"data":$pagoJson}', 201);
      });
      expect(
        (await s.pagar(
          reposteriaId: 2,
          ventaId: 7,
          token: 't',
          metodo: 'qr',
          monto: '40.00',
          referencia: 'QR-1',
        )).metodo,
        'qr',
      );
    });
    test('anulación usa endpoint explícito', () async {
      final s = service((r) async {
        expect(r.url.path, endsWith('/ventas/7/anular'));
        return response('{"data":$ventaJson}');
      });
      await s.anular(reposteriaId: 2, ventaId: 7, token: 't');
    });
    test('eliminación de pago usa DELETE', () async {
      final s = service((r) async {
        expect(r.method, 'DELETE');
        expect(r.url.path, endsWith('/ventas/7/pagos/3'));
        return response('', 204);
      });
      await s.eliminarPago(reposteriaId: 2, ventaId: 7, pagoId: 3, token: 't');
    });
    test('sobrepago 422 conserva mensaje Laravel', () async {
      final s = service(
        (_) async => response(
          '{"message":"Datos inválidos","errors":{"monto":["El pago no puede superar el saldo."]}}',
          422,
        ),
      );
      expect(
        () => s.pagar(
          reposteriaId: 2,
          ventaId: 7,
          token: 't',
          metodo: 'qr',
          monto: '99.00',
        ),
        throwsA(
          isA<ApiException>().having((e) => e.errors['monto'], 'monto', [
            'El pago no puede superar el saldo.',
          ]),
        ),
      );
    });
  });
  group('VentaController', () {
    test('publica loading y éxito', () async {
      final pending = Completer<http.Response>();
      final c = VentaController(
        service((_) => pending.future),
        onUnauthorized: () async {},
      );
      final future = c.load(reposteriaId: 2, token: 't');
      expect(c.status, DataStatus.loading);
      pending.complete(response(page()));
      await future;
      expect(c.status, DataStatus.success);
      expect(c.ventas, hasLength(1));
    });
    test('cambio tenant limpia inmediatamente datos y detalle', () async {
      final second = Completer<http.Response>();
      final s = service((r) async {
        if (r.url.path.contains('/reposterias/2/')) return second.future;
        if (r.url.path.endsWith('/ventas/7')) {
          return response('{"data":$ventaJson}');
        }
        return response(page());
      });
      final c = VentaController(s, onUnauthorized: () async {});
      await c.load(reposteriaId: 1, token: 't');
      await c.detalle(7);
      expect(c.selected, isNotNull);
      final future = c.load(reposteriaId: 2, token: 't');
      expect(c.ventas, isEmpty);
      expect(c.selected, isNull);
      second.complete(response(page()));
      await future;
    });
    test('401 expira sesión', () async {
      var expired = false;
      final c = VentaController(
        service((_) async => response('{"message":"Unauthenticated"}', 401)),
        onUnauthorized: () async => expired = true,
      );
      await c.load(reposteriaId: 2, token: 't');
      expect(expired, isTrue);
    });
    test('pago recarga el estado y saldo desde el detalle', () async {
      var detailCalls = 0;
      final paid = ventaJson
          .replaceFirst('"estado":"parcial"', '"estado":"pagada"')
          .replaceFirst('"monto_pagado":"40.00"', '"monto_pagado":"90.00"')
          .replaceFirst('"saldo":"50.00"', '"saldo":"0.00"');
      final c = VentaController(
        service((r) async {
          if (r.url.path.endsWith('/pagos') && r.method == 'POST') {
            return response('{"data":$pagoJson}', 201);
          }
          if (r.url.path.endsWith('/ventas/7')) {
            detailCalls++;
            return response('{"data":$paid}');
          }
          return response(page(paid));
        }),
        onUnauthorized: () async {},
      );
      await c.load(reposteriaId: 2, token: 't');
      expect(await c.pagar(7, metodo: 'qr', monto: '50.00'), isTrue);
      expect(c.selected?.estado, 'pagada');
      expect(c.selected?.saldo, '0.00');
      expect(detailCalls, 1);
    });
    test('anulación exitosa se refresca desde backend', () async {
      final cancelled = ventaJson.replaceFirst(
        '"estado":"parcial"',
        '"estado":"anulada"',
      );
      final c = VentaController(
        service((r) async {
          if (r.url.path.endsWith('/anular')) {
            return response('{"data":$cancelled}');
          }
          if (r.url.path.endsWith('/ventas/7')) {
            return response('{"data":$cancelled}');
          }
          return response(page(cancelled));
        }),
        onUnauthorized: () async {},
      );
      await c.load(reposteriaId: 2, token: 't');
      expect(await c.anular(7), isTrue);
      expect(c.selected?.anulada, isTrue);
    });
    test('permisos distinguen operación y administración', () {
      final c = VentaController(
        service((_) async => response('{}')),
        onUnauthorized: () async {},
      );
      expect(c.canOperate('vendedor'), isTrue);
      expect(c.canAdmin('vendedor'), isFalse);
      expect(c.canAdmin('admin'), isTrue);
      expect(c.canOperate('produccion'), isFalse);
    });
  });
  group('layout ventas', () {
    testWidgets('móvil y desktop conservan la misma lógica compartida', (
      tester,
    ) async {
      var shared = 'Venta #7';
      tester.view.physicalSize = const Size(400, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      await tester.pumpWidget(
        MaterialApp(
          home: AdaptiveLayout(
            mobile: (_) => Text(shared),
            desktop: (_) => Text(shared),
          ),
        ),
      );
      expect(find.byKey(const Key('mobile-layout')), findsOneWidget);
      expect(find.text('Venta #7'), findsOneWidget);
      tester.view.physicalSize = const Size(1000, 800);
      await tester.pump();
      expect(find.byKey(const Key('desktop-layout')), findsOneWidget);
      expect(find.text('Venta #7'), findsOneWidget);
    });
  });
}
