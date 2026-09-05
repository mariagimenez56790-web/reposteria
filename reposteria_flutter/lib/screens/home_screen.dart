import 'package:flutter/material.dart';
import '../controllers/auth_controller.dart';
import '../widgets/adaptive_layout.dart';
import 'desktop/desktop_home_screen.dart';
import 'mobile/mobile_home_screen.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key, required this.controller});
  final AuthController controller;
  @override
  Widget build(BuildContext context) => AdaptiveLayout(
    mobile: (_) => MobileHomeScreen(controller: controller),
    desktop: (_) => DesktopHomeScreen(controller: controller),
  );
}
