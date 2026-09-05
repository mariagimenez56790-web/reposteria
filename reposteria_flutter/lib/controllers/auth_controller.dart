import 'package:flutter/foundation.dart';

import '../models/reposteria.dart';
import '../models/user.dart';
import '../services/api_exception.dart';
import '../services/auth_service.dart';
import '../services/session_storage.dart';

enum AuthStatus { initial, loading, authenticated, unauthenticated }

class AuthController extends ChangeNotifier {
  AuthController(this._authService, this._storage);

  final AuthService _authService;
  final SessionStorage _storage;
  AuthStatus status = AuthStatus.initial;
  User? user;
  Reposteria? activeReposteria;
  String? error;
  String? _token;

  String? get accessToken => _token;

  Future<void> restoreSession() async {
    status = AuthStatus.loading;
    notifyListeners();
    _token = await _storage.readToken();
    if (_token == null) {
      status = AuthStatus.unauthenticated;
      notifyListeners();
      return;
    }
    try {
      user = await _authService.me(_token!);
      await _restoreReposteria();
      status = AuthStatus.authenticated;
    } catch (_) {
      await _clearLocalSession();
      status = AuthStatus.unauthenticated;
    }
    notifyListeners();
  }

  Future<bool> login(String email, String password) async {
    error = null;
    status = AuthStatus.loading;
    notifyListeners();
    try {
      final result = await _authService.login(email, password);
      _token = result.token;
      user = result.user;
      await _storage.writeToken(result.token);
      await _restoreReposteria();
      status = AuthStatus.authenticated;
      notifyListeners();
      return true;
    } on ApiException catch (exception) {
      error = exception.message;
    } catch (_) {
      error = 'No fue posible iniciar sesión.';
    }
    status = AuthStatus.unauthenticated;
    notifyListeners();
    return false;
  }

  Future<void> selectReposteria(Reposteria reposteria) async {
    if (!(user?.reposterias.any((item) => item.id == reposteria.id) ?? false)) {
      return;
    }
    activeReposteria = reposteria;
    await _storage.writeReposteriaId(reposteria.id);
    notifyListeners();
  }

  Future<void> logout() async {
    final token = _token;
    if (token != null) {
      try {
        await _authService.logout(token);
      } catch (_) {
        // El cierre local no depende de que el servidor esté disponible.
      }
    }
    await _clearLocalSession();
    status = AuthStatus.unauthenticated;
    notifyListeners();
  }

  Future<void> expireSession() async {
    await _clearLocalSession();
    status = AuthStatus.unauthenticated;
    notifyListeners();
  }

  Future<void> _restoreReposteria() async {
    final reposterias = user?.reposterias ?? const <Reposteria>[];
    final savedId = await _storage.readReposteriaId();
    activeReposteria = null;
    for (final reposteria in reposterias) {
      if (reposteria.id == savedId) activeReposteria = reposteria;
    }
    activeReposteria ??= reposterias.isEmpty ? null : reposterias.first;
    if (activeReposteria != null) {
      await _storage.writeReposteriaId(activeReposteria!.id);
    }
  }

  Future<void> _clearLocalSession() async {
    _token = null;
    user = null;
    activeReposteria = null;
    error = null;
    await _storage.clear();
  }
}
