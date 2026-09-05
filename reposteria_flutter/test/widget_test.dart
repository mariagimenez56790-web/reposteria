import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:reposteria_flutter/controllers/auth_controller.dart';
import 'package:reposteria_flutter/services/api_client.dart';
import 'package:reposteria_flutter/services/auth_service.dart';
import 'package:reposteria_flutter/services/session_storage.dart';

class MemoryStorage implements SessionStorage {
  String? token;
  int? reposteriaId;
  @override
  Future<void> clear() async {
    token = null;
    reposteriaId = null;
  }

  @override
  Future<int?> readReposteriaId() async => reposteriaId;
  @override
  Future<String?> readToken() async => token;
  @override
  Future<void> writeReposteriaId(int id) async => reposteriaId = id;
  @override
  Future<void> writeToken(String value) async => token = value;
}

const userJson =
    '{"id":1,"name":"Ana","email":"ana@example.com","role":"admin","activo":true,"reposterias":[{"id":2,"nombre":"Dulce","slug":"dulce","estado":"aprobada"}]}';

void main() {
  test('login guarda token, usuario y repostería activa', () async {
    final storage = MemoryStorage();
    final client = MockClient((request) async {
      expect(request.url.path, '/api/login');
      return http.Response('{"data":{"token":"abc","user":$userJson}}', 200);
    });
    final controller = AuthController(
      AuthService(ApiClient(client: client, baseUrl: 'http://test')),
      storage,
    );
    expect(await controller.login('ana@example.com', 'secret'), isTrue);
    expect(controller.status, AuthStatus.authenticated);
    expect(controller.user?.name, 'Ana');
    expect(controller.activeReposteria?.id, 2);
    expect(storage.token, 'abc');
  });

  test('restaura sesión consultando me con Bearer token', () async {
    final storage = MemoryStorage()..token = 'abc';
    final client = MockClient((request) async {
      expect(request.url.path, '/api/me');
      expect(request.headers['authorization'], 'Bearer abc');
      return http.Response('{"data":$userJson}', 200);
    });
    final controller = AuthController(
      AuthService(ApiClient(client: client, baseUrl: 'http://test')),
      storage,
    );
    await controller.restoreSession();
    expect(controller.status, AuthStatus.authenticated);
    expect(controller.activeReposteria?.nombre, 'Dulce');
  });

  test('logout revoca token y limpia sesión local', () async {
    final storage = MemoryStorage()..token = 'abc';
    final client = MockClient((request) async {
      if (request.url.path == '/api/me') {
        return http.Response('{"data":$userJson}', 200);
      }
      expect(request.url.path, '/api/logout');
      expect(request.headers['authorization'], 'Bearer abc');
      return http.Response('{"message":"ok"}', 200);
    });
    final controller = AuthController(
      AuthService(ApiClient(client: client, baseUrl: 'http://test')),
      storage,
    );
    await controller.restoreSession();
    await controller.logout();
    expect(controller.status, AuthStatus.unauthenticated);
    expect(storage.token, isNull);
    expect(controller.activeReposteria, isNull);
  });
}
