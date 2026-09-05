import 'package:flutter/material.dart';
import '../../controllers/auth_controller.dart';
import '../../models/reposteria.dart';
import '../catalogo_screen.dart';
import '../clientes_screen.dart';
import '../pedidos_screen.dart';

class MobileHomeScreen extends StatelessWidget {
  const MobileHomeScreen({super.key, required this.controller});
  final AuthController controller;
  bool get clientsAllowed =>
      const ['admin', 'vendedor', 'superadmin'].contains(controller.user?.role);
  void open(BuildContext context, Widget page) =>
      Navigator.push(context, MaterialPageRoute(builder: (_) => page));
  @override
  Widget build(BuildContext context) {
    final user = controller.user!;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Repostería'),
        actions: [
          IconButton(
            onPressed: controller.logout,
            icon: const Icon(Icons.logout),
            tooltip: 'Cerrar sesión',
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            'Hola, ${user.name}',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          Text(user.email),
          const SizedBox(height: 20),
          DropdownButtonFormField<Reposteria>(
            initialValue: controller.activeReposteria,
            decoration: const InputDecoration(
              labelText: 'Repostería activa',
              border: OutlineInputBorder(),
            ),
            items: user.reposterias
                .map((r) => DropdownMenuItem(value: r, child: Text(r.nombre)))
                .toList(),
            onChanged: (r) {
              if (r != null) controller.selectReposteria(r);
            },
          ),
          const SizedBox(height: 20),
          if (controller.activeReposteria != null) ...[
            _tile(
              Icons.menu_book,
              'Catálogo',
              'Explorar productos',
              () => open(context, CatalogoScreen(authController: controller)),
            ),
            if (clientsAllowed)
              _tile(
                Icons.people,
                'Clientes',
                'Crear y editar clientes',
                () => open(context, ClientesScreen(auth: controller)),
              ),
            _tile(
              Icons.receipt_long,
              'Pedidos',
              'Gestionar pedidos',
              () => open(context, PedidosScreen(auth: controller)),
            ),
          ],
        ],
      ),
      bottomNavigationBar: controller.activeReposteria == null
          ? null
          : BottomAppBar(
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceAround,
                children: [
                  IconButton(
                    onPressed: () => open(
                      context,
                      CatalogoScreen(authController: controller),
                    ),
                    icon: const Icon(Icons.menu_book),
                    tooltip: 'Catálogo',
                  ),
                  if (clientsAllowed)
                    IconButton(
                      onPressed: () =>
                          open(context, ClientesScreen(auth: controller)),
                      icon: const Icon(Icons.people),
                      tooltip: 'Clientes',
                    ),
                  IconButton(
                    onPressed: () =>
                        open(context, PedidosScreen(auth: controller)),
                    icon: const Icon(Icons.receipt_long),
                    tooltip: 'Pedidos',
                  ),
                ],
              ),
            ),
    );
  }

  Widget _tile(
    IconData icon,
    String title,
    String subtitle,
    VoidCallback tap,
  ) => Card(
    child: ListTile(
      minVerticalPadding: 18,
      leading: Icon(icon, size: 32),
      title: Text(title),
      subtitle: Text(subtitle),
      trailing: const Icon(Icons.chevron_right),
      onTap: tap,
    ),
  );
}
