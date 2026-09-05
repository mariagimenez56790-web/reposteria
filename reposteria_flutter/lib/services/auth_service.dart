import '../models/user.dart';
import 'api_client.dart';

class LoginResult {
  const LoginResult({required this.token, required this.user});
  final String token;
  final User user;
}

class AuthService {
  const AuthService(this._api);
  final ApiClient _api;

  Future<LoginResult> login(String email, String password) async {
    final response = await _api.post(
      '/api/login',
      body: {
        'email': email.trim(),
        'password': password,
        'device_name': 'reposteria_flutter',
      },
    );
    final data = response['data'] as Map<String, dynamic>;
    return LoginResult(
      token: data['token'] as String,
      user: User.fromJson(data['user'] as Map<String, dynamic>),
    );
  }

  Future<User> me(String token) async {
    final response = await _api.get('/api/me', token: token);
    return User.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<void> logout(String token) async =>
      _api.post('/api/logout', token: token);
}
