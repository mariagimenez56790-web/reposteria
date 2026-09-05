import 'package:flutter_secure_storage/flutter_secure_storage.dart';

abstract interface class SessionStorage {
  Future<String?> readToken();
  Future<void> writeToken(String token);
  Future<int?> readReposteriaId();
  Future<void> writeReposteriaId(int id);
  Future<void> clear();
}

class SecureSessionStorage implements SessionStorage {
  const SecureSessionStorage();

  static const _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );
  static const _tokenKey = 'sanctum_token';
  static const _reposteriaKey = 'active_reposteria_id';

  @override
  Future<String?> readToken() => _storage.read(key: _tokenKey);
  @override
  Future<void> writeToken(String token) =>
      _storage.write(key: _tokenKey, value: token);
  @override
  Future<int?> readReposteriaId() async =>
      int.tryParse(await _storage.read(key: _reposteriaKey) ?? '');
  @override
  Future<void> writeReposteriaId(int id) =>
      _storage.write(key: _reposteriaKey, value: '$id');
  @override
  Future<void> clear() => _storage.deleteAll();
}
