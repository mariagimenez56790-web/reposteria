import 'package:flutter/material.dart';

import 'controllers/auth_controller.dart';
import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'screens/splash_screen.dart';
import 'services/api_client.dart';
import 'services/auth_service.dart';
import 'services/session_storage.dart';

class ReposteriaApp extends StatefulWidget {
  const ReposteriaApp({super.key, this.controller});
  final AuthController? controller;

  @override
  State<ReposteriaApp> createState() => _ReposteriaAppState();
}

class _ReposteriaAppState extends State<ReposteriaApp> {
  late final AuthController _controller;

  @override
  void initState() {
    super.initState();
    _controller =
        widget.controller ??
        AuthController(AuthService(ApiClient()), const SecureSessionStorage());
    _controller.restoreSession();
  }

  @override
  void dispose() {
    if (widget.controller == null) _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'Repostería',
    debugShowCheckedModeBanner: false,
    theme: ThemeData(
      colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xff9c4668)),
      useMaterial3: true,
    ),
    home: ListenableBuilder(
      listenable: _controller,
      builder: (context, _) => switch (_controller.status) {
        AuthStatus.initial || AuthStatus.loading => const SplashScreen(),
        AuthStatus.unauthenticated => LoginScreen(controller: _controller),
        AuthStatus.authenticated => HomeScreen(controller: _controller),
      },
    ),
  );
}
