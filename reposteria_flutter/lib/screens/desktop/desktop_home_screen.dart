import 'package:flutter/material.dart';
import '../../controllers/auth_controller.dart';
import '../../models/reposteria.dart';
import '../catalogo_screen.dart';
import '../clientes_screen.dart';
import '../pedidos_screen.dart';

class DesktopHomeScreen extends StatefulWidget {
  const DesktopHomeScreen({super.key, required this.controller});
  final AuthController controller;
  @override
  State<DesktopHomeScreen> createState() => _DesktopHomeScreenState();
}

class _DesktopHomeScreenState extends State<DesktopHomeScreen> {
  int selected = 0;
  bool get clientsAllowed => const [
    'admin',
    'vendedor',
    'superadmin',
  ].contains(widget.controller.user?.role);
  @override
  Widget build(BuildContext context) {
    final tenant = widget.controller.activeReposteria?.id;
    final pages = <Widget>[
      _welcome(),
      KeyedSubtree(
        key: ValueKey('catalog-$tenant'),
        child: CatalogoScreen(authController: widget.controller),
      ),
      if (clientsAllowed)
        KeyedSubtree(
          key: ValueKey('clients-$tenant'),
          child: ClientesScreen(auth: widget.controller),
        ),
      KeyedSubtree(
        key: ValueKey('orders-$tenant'),
        child: PedidosScreen(auth: widget.controller),
      ),
    ];
    final destinations = <NavigationRailDestination>[
      const NavigationRailDestination(
        icon: Icon(Icons.home_outlined),
        selectedIcon: Icon(Icons.home),
        label: Text('Inicio'),
      ),
      const NavigationRailDestination(
        icon: Icon(Icons.menu_book_outlined),
        selectedIcon: Icon(Icons.menu_book),
        label: Text('Catálogo'),
      ),
      if (clientsAllowed)
        const NavigationRailDestination(
          icon: Icon(Icons.people_outline),
          selectedIcon: Icon(Icons.people),
          label: Text('Clientes'),
        ),
      const NavigationRailDestination(
        icon: Icon(Icons.receipt_long_outlined),
        selectedIcon: Icon(Icons.receipt_long),
        label: Text('Pedidos'),
      ),
    ];
    return Scaffold(
      body: Row(
        children: [
          NavigationRail(
            extended: true,
            selectedIndex: selected,
            onDestinationSelected: (i) => setState(() => selected = i),
            destinations: destinations,
          ),
          const VerticalDivider(width: 1),
          Expanded(child: pages[selected]),
        ],
      ),
    );
  }

  Widget _welcome() {
    final user = widget.controller.user!;
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.controller.activeReposteria?.nombre ?? 'Repostería'),
        actions: [
          IconButton(
            onPressed: widget.controller.logout,
            icon: const Icon(Icons.logout),
            tooltip: 'Cerrar sesión',
          ),
        ],
      ),
      body: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Hola, ${user.name}',
              style: Theme.of(context).textTheme.headlineMedium,
            ),
            Text('${user.email} · ${user.role}'),
            const SizedBox(height: 28),
            SizedBox(
              width: 420,
              child: DropdownButtonFormField<Reposteria>(
                initialValue: widget.controller.activeReposteria,
                decoration: const InputDecoration(
                  labelText: 'Repostería activa',
                  border: OutlineInputBorder(),
                ),
                items: user.reposterias
                    .map(
                      (r) => DropdownMenuItem(value: r, child: Text(r.nombre)),
                    )
                    .toList(),
                onChanged: (r) {
                  if (r != null) widget.controller.selectReposteria(r);
                },
              ),
            ),
            const SizedBox(height: 32),
            const Text('Selecciona un módulo desde el menú lateral.'),
          ],
        ),
      ),
    );
  }
}
