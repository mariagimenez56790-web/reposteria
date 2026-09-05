import 'package:flutter/material.dart';
import '../controllers/auth_controller.dart';
import '../models/reposteria.dart';
import 'catalogo_screen.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key, required this.controller});
  final AuthController controller;

  @override
  Widget build(BuildContext context) {
    final user = controller.user!;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Repostería'),
        actions: [
          IconButton(
            onPressed: controller.logout,
            tooltip: 'Cerrar sesión',
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Text(
            'Hola, ${user.name}',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          Text('${user.email} · ${user.role ?? 'sin rol'}'),
          const SizedBox(height: 32),
          Text(
            'Repostería activa',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          if (user.reposterias.isEmpty)
            const Card(
              child: Padding(
                padding: EdgeInsets.all(16),
                child: Text('No tienes reposterías aprobadas disponibles.'),
              ),
            )
          else
            DropdownButtonFormField<Reposteria>(
              initialValue: controller.activeReposteria,
              decoration: const InputDecoration(border: OutlineInputBorder()),
              items: user.reposterias
                  .map(
                    (item) =>
                        DropdownMenuItem(value: item, child: Text(item.nombre)),
                  )
                  .toList(growable: false),
              onChanged: (value) {
                if (value != null) controller.selectReposteria(value);
              },
            ),
          const SizedBox(height: 24),
          if (controller.activeReposteria case final active?)
            Card(
              child: ListTile(
                leading: const Icon(Icons.storefront),
                title: Text(active.nombre),
                subtitle: Text('Estado: ${active.estado}'),
              ),
            ),
          if (controller.activeReposteria != null) ...[
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => CatalogoScreen(authController: controller),
                ),
              ),
              icon: const Icon(Icons.menu_book_outlined),
              label: const Text('Ver catálogo'),
            ),
          ],
        ],
      ),
    );
  }
}
