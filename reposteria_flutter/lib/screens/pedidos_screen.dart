import 'package:flutter/material.dart';
import '../controllers/auth_controller.dart';
import '../controllers/cliente_controller.dart';
import '../controllers/pedido_controller.dart';
import '../models/cliente.dart';
import '../models/pedido.dart';
import '../models/producto.dart';
import '../services/api_client.dart';
import '../services/catalogo_service.dart';
import '../services/cliente_service.dart';
import '../services/pedido_service.dart';
import '../widgets/adaptive_layout.dart';

class PedidosScreen extends StatefulWidget {
  const PedidosScreen({super.key, required this.auth, this.controller});
  final AuthController auth;
  final PedidoController? controller;
  @override
  State<PedidosScreen> createState() => _PedidosScreenState();
}

class _PedidosScreenState extends State<PedidosScreen> {
  static const estados = [
    'pendiente',
    'confirmado',
    'en_produccion',
    'listo',
    'entregado',
    'cancelado',
  ];
  late final PedidoController controller;
  List<Cliente> clientes = const [];
  List<Producto> productos = const [];
  bool get canEdit => const [
    'admin',
    'vendedor',
    'superadmin',
  ].contains(widget.auth.user?.role);

  @override
  void initState() {
    super.initState();
    controller =
        widget.controller ??
        PedidoController(PedidoService(ApiClient()), onUnauthorized: _expired);
    _load();
  }

  Future<void> _load() async {
    final shop = widget.auth.activeReposteria;
    final token = widget.auth.accessToken;
    if (shop == null || token == null) return;
    await controller.load(reposteriaId: shop.id, token: token);
    try {
      final results = await Future.wait([
        ClienteService(ApiClient()).listar(reposteriaId: shop.id, token: token),
        CatalogoService(
          ApiClient(),
        ).productos(reposteriaId: shop.id, token: token, perPage: 100),
      ]);
      if (mounted) {
        setState(() {
          clientes = (results[0] as PaginaClientes).items;
          productos = (results[1] as PaginaProductos).productos;
        });
      }
    } catch (_) {}
  }

  Future<void> _expired() async {
    controller.clear();
    await widget.auth.expireSession();
    if (mounted) Navigator.popUntil(context, (r) => r.isFirst);
  }

  @override
  void dispose() {
    if (widget.controller == null) controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: controller,
    builder: (_, _) =>
        AdaptiveLayout(mobile: (_) => _mobile(), desktop: (_) => _desktop()),
  );
  Widget _mobile() => Scaffold(
    appBar: AppBar(title: const Text('Pedidos')),
    floatingActionButton: canEdit
        ? FloatingActionButton.extended(
            onPressed: () => _create(),
            icon: const Icon(Icons.add),
            label: const Text('Nuevo'),
          )
        : null,
    body: Column(
      children: [
        _filters(),
        Expanded(child: _content(true)),
      ],
    ),
  );
  Widget _desktop() => Scaffold(
    body: Row(
      children: [
        NavigationRail(
          selectedIndex: 1,
          onDestinationSelected: (i) {
            if (i == 0) Navigator.pop(context);
          },
          labelType: NavigationRailLabelType.all,
          destinations: const [
            NavigationRailDestination(
              icon: Icon(Icons.home_outlined),
              label: Text('Inicio'),
            ),
            NavigationRailDestination(
              icon: Icon(Icons.receipt_long),
              label: Text('Pedidos'),
            ),
          ],
        ),
        const VerticalDivider(width: 1),
        Expanded(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.all(24),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        'Pedidos · ${widget.auth.activeReposteria?.nombre ?? ''}',
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                    ),
                    if (canEdit)
                      FilledButton.icon(
                        onPressed: () => _create(),
                        icon: const Icon(Icons.add),
                        label: const Text('Nuevo pedido'),
                      ),
                  ],
                ),
              ),
              _filters(),
              Expanded(child: _content(false)),
            ],
          ),
        ),
      ],
    ),
  );
  Widget _filters() => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
    child: Row(
      children: [
        Expanded(
          child: DropdownButtonFormField<String>(
            initialValue: controller.estado,
            decoration: const InputDecoration(
              labelText: 'Estado',
              border: OutlineInputBorder(),
            ),
            items: [
              const DropdownMenuItem(value: null, child: Text('Todos')),
              ...estados.map(
                (e) => DropdownMenuItem(value: e, child: Text(_label(e))),
              ),
            ],
            onChanged: controller.setEstado,
          ),
        ),
        const SizedBox(width: 12),
        if (clientes.isNotEmpty)
          Expanded(
            child: DropdownButtonFormField<int?>(
              initialValue: controller.clienteId,
              decoration: const InputDecoration(
                labelText: 'Cliente',
                border: OutlineInputBorder(),
              ),
              items: [
                const DropdownMenuItem(value: null, child: Text('Todos')),
                ...clientes.map(
                  (c) => DropdownMenuItem(value: c.id, child: Text(c.nombre)),
                ),
              ],
              onChanged: controller.setCliente,
            ),
          ),
        const SizedBox(width: 12),
        IconButton(
          onPressed: controller.retry,
          icon: const Icon(Icons.refresh),
          tooltip: 'Actualizar',
        ),
      ],
    ),
  );
  Widget _content(bool cards) {
    if (controller.status == DataStatus.loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (controller.status == DataStatus.error) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(controller.error ?? 'No se pudo cargar.'),
            FilledButton.tonal(
              onPressed: controller.retry,
              child: const Text('Reintentar'),
            ),
          ],
        ),
      );
    }
    if (controller.pedidos.isEmpty) {
      return const Center(child: Text('No hay pedidos.'));
    }
    if (cards) {
      return ListView.builder(
        padding: const EdgeInsets.fromLTRB(12, 0, 12, 96),
        itemCount: controller.pedidos.length,
        itemBuilder: (_, i) => _card(controller.pedidos[i]),
      );
    }
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: SizedBox(
        width: double.infinity,
        child: DataTable(
          columns: const [
            DataColumn(label: Text('N.º')),
            DataColumn(label: Text('Fecha')),
            DataColumn(label: Text('Cliente')),
            DataColumn(label: Text('Estado')),
            DataColumn(label: Text('Total')),
            DataColumn(label: Text('Acción')),
          ],
          rows: controller.pedidos
              .map(
                (p) => DataRow(
                  cells: [
                    DataCell(Text('${p.id}')),
                    DataCell(Text(_date(p.fechaPedido))),
                    DataCell(Text(p.cliente?.nombre ?? 'Sin cliente')),
                    DataCell(Text(_label(p.estado))),
                    DataCell(Text('Bs ${p.total}')),
                    DataCell(
                      IconButton(
                        onPressed: () => _open(p),
                        icon: const Icon(Icons.open_in_new),
                      ),
                    ),
                  ],
                ),
              )
              .toList(),
        ),
      ),
    );
  }

  Widget _card(Pedido p) => Card(
    child: ListTile(
      title: Text('Pedido #${p.id} · Bs ${p.total}'),
      subtitle: Text(
        '${p.cliente?.nombre ?? 'Sin cliente'}\n${_date(p.fechaPedido)}',
      ),
      isThreeLine: true,
      trailing: Chip(label: Text(_label(p.estado))),
      onTap: () => _open(p),
    ),
  );

  Future<void> _open(Pedido summary) async {
    final full = await controller.detalle(summary.id);
    if (full == null || !mounted) return;
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => PedidoDetalleScreen(
          auth: widget.auth,
          controller: controller,
          pedido: full,
          clientes: clientes,
          productos: productos,
        ),
      ),
    );
    await controller.retry();
  }

  Future<void> _create() async {
    if (productos.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No hay productos disponibles.')),
      );
      return;
    }
    int? clienteId;
    Producto producto = await _productDetail(productos.first);
    if (!mounted) return;
    int? varianteId;
    final qty = TextEditingController(text: '1');
    final notes = TextEditingController();
    final key = GlobalKey<FormState>();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setLocal) => AlertDialog(
          title: const Text('Nuevo pedido'),
          content: SizedBox(
            width: 650,
            child: Form(
              key: key,
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    DropdownButtonFormField<int?>(
                      initialValue: clienteId,
                      decoration: const InputDecoration(
                        labelText: 'Cliente (opcional)',
                      ),
                      items: [
                        const DropdownMenuItem(
                          value: null,
                          child: Text('Sin cliente'),
                        ),
                        ...clientes.map(
                          (c) => DropdownMenuItem(
                            value: c.id,
                            child: Text(c.nombre),
                          ),
                        ),
                      ],
                      onChanged: (v) => clienteId = v,
                    ),
                    DropdownButtonFormField<Producto>(
                      initialValue: productos.first,
                      decoration: const InputDecoration(labelText: 'Producto'),
                      items: productos
                          .map(
                            (p) => DropdownMenuItem(
                              value: p,
                              child: Text(p.nombre),
                            ),
                          )
                          .toList(),
                      onChanged: (v) async {
                        if (v == null) return;
                        final full = await _productDetail(v);
                        setLocal(() {
                          producto = full;
                          varianteId = null;
                        });
                      },
                    ),
                    if (producto.variantes.isNotEmpty)
                      DropdownButtonFormField<int?>(
                        initialValue: varianteId,
                        decoration: const InputDecoration(
                          labelText: 'Variante',
                        ),
                        items: [
                          const DropdownMenuItem(
                            value: null,
                            child: Text('Sin variante'),
                          ),
                          ...producto.variantes.map(
                            (v) => DropdownMenuItem(
                              value: v.id,
                              child: Text(v.nombre),
                            ),
                          ),
                        ],
                        onChanged: (v) => varianteId = v,
                      ),
                    TextFormField(
                      controller: qty,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(labelText: 'Cantidad'),
                      validator: (v) => (int.tryParse(v ?? '') ?? 0) < 1
                          ? 'Cantidad inválida'
                          : null,
                    ),
                    TextField(
                      controller: notes,
                      maxLines: 2,
                      decoration: const InputDecoration(
                        labelText: 'Observaciones',
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancelar'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, key.currentState!.validate()),
              child: const Text('Crear'),
            ),
          ],
        ),
      ),
    );
    if (ok == true) {
      await controller.crear(
        clienteId: clienteId,
        observaciones: notes.text.trim().isEmpty ? null : notes.text.trim(),
        detalles: [
          {
            'producto_id': producto.id,
            'producto_variante_id': varianteId,
            'cantidad': int.parse(qty.text),
          },
        ],
      );
    }
    qty.dispose();
    notes.dispose();
  }

  Future<Producto> _productDetail(Producto product) async {
    final shop = widget.auth.activeReposteria;
    final token = widget.auth.accessToken;
    if (shop == null || token == null || !product.tieneVariantes) {
      return product;
    }
    try {
      return await CatalogoService(
        ApiClient(),
      ).producto(reposteriaId: shop.id, productoId: product.id, token: token);
    } catch (_) {
      return product;
    }
  }

  String _label(String v) => v.replaceAll('_', ' ');
  String _date(DateTime d) =>
      '${d.day.toString().padLeft(2, '0')}/${d.month.toString().padLeft(2, '0')}/${d.year}';
}

class PedidoDetalleScreen extends StatefulWidget {
  const PedidoDetalleScreen({
    super.key,
    required this.auth,
    required this.controller,
    required this.pedido,
    required this.clientes,
    required this.productos,
  });
  final AuthController auth;
  final PedidoController controller;
  final Pedido pedido;
  final List<Cliente> clientes;
  final List<Producto> productos;
  @override
  State<PedidoDetalleScreen> createState() => _PedidoDetalleScreenState();
}

class _PedidoDetalleScreenState extends State<PedidoDetalleScreen> {
  late Pedido pedido;
  bool get canEdit =>
      pedido.editable &&
      const [
        'admin',
        'vendedor',
        'superadmin',
      ].contains(widget.auth.user?.role);
  @override
  void initState() {
    super.initState();
    pedido = widget.pedido;
  }

  Future<void> refresh() async {
    final p = await widget.controller.detalle(pedido.id);
    if (p != null && mounted) setState(() => pedido = p);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Pedido #${pedido.id}')),
      floatingActionButton: canEdit
          ? FloatingActionButton(onPressed: _add, child: const Icon(Icons.add))
          : null,
      body: AdaptiveLayout(
        mobile: (_) => _body(false),
        desktop: (_) => _body(true),
      ),
    );
  }

  Widget _body(bool desktop) {
    final header = Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Estado: ${pedido.estado}'),
            Text('Cliente: ${pedido.cliente?.nombre ?? 'Sin cliente'}'),
            Text(
              'Total: Bs ${pedido.total}',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
            if (pedido.observaciones != null) Text(pedido.observaciones!),
            if (canEdit)
              TextButton.icon(
                onPressed: _editHeader,
                icon: const Icon(Icons.edit),
                label: const Text('Editar cabecera'),
              ),
            ...widget.controller
                .transiciones(pedido, widget.auth.user?.role)
                .map(
                  (s) => Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: FilledButton.tonal(
                      onPressed: () async {
                        if (await widget.controller.cambiarEstado(
                          pedido.id,
                          s,
                        )) {
                          await refresh();
                        }
                      },
                      child: Text('Cambiar a ${s.replaceAll('_', ' ')}'),
                    ),
                  ),
                ),
          ],
        ),
      ),
    );
    final details = ListView(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      children: pedido.detalles
          .map(
            (d) => Card(
              child: ListTile(
                title: Text(
                  d.nombreVariante == null
                      ? d.nombreProducto
                      : '${d.nombreProducto} · ${d.nombreVariante}',
                ),
                subtitle: Text('${d.cantidad} × Bs ${d.precioUnitario}'),
                trailing: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text('Bs ${d.subtotal}'),
                    if (canEdit)
                      IconButton(
                        onPressed: () => _quantity(d),
                        icon: const Icon(Icons.edit),
                      ),
                    if (canEdit)
                      IconButton(
                        onPressed: () async {
                          if (await widget.controller.eliminarDetalle(
                            pedido.id,
                            d.id,
                          )) {
                            await refresh();
                          }
                        },
                        icon: const Icon(Icons.delete_outline),
                      ),
                  ],
                ),
              ),
            ),
          )
          .toList(),
    );
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: desktop
          ? Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(width: 320, child: header),
                const SizedBox(width: 16),
                Expanded(child: details),
              ],
            )
          : Column(children: [header, details]),
    );
  }

  Future<void> _editHeader() async {
    final notes = TextEditingController(text: pedido.observaciones);
    int? client = pedido.cliente?.id;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Editar pedido'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            DropdownButtonFormField<int?>(
              initialValue: client,
              items: [
                const DropdownMenuItem(value: null, child: Text('Sin cliente')),
                ...widget.clientes.map(
                  (c) => DropdownMenuItem(value: c.id, child: Text(c.nombre)),
                ),
              ],
              onChanged: (v) => client = v,
            ),
            TextField(
              controller: notes,
              decoration: const InputDecoration(labelText: 'Observaciones'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Guardar'),
          ),
        ],
      ),
    );
    if (ok == true &&
        await widget.controller.editar(
          pedido.id,
          clienteId: client,
          observaciones: notes.text,
        )) {
      await refresh();
    }
    notes.dispose();
  }

  Future<void> _add() async {
    if (widget.productos.isEmpty) return;
    Producto product = await _productDetail(widget.productos.first);
    if (!mounted) return;
    int? variant;
    final qty = TextEditingController(text: '1');
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setLocal) => AlertDialog(
          title: const Text('Agregar detalle'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<Producto>(
                initialValue: product,
                items: widget.productos
                    .map(
                      (p) => DropdownMenuItem(value: p, child: Text(p.nombre)),
                    )
                    .toList(),
                onChanged: (v) async {
                  if (v == null) return;
                  final full = await _productDetail(v);
                  setLocal(() {
                    product = full;
                    variant = null;
                  });
                },
              ),
              if (product.variantes.isNotEmpty)
                DropdownButtonFormField<int?>(
                  initialValue: variant,
                  items: [
                    const DropdownMenuItem(
                      value: null,
                      child: Text('Sin variante'),
                    ),
                    ...product.variantes.map(
                      (v) =>
                          DropdownMenuItem(value: v.id, child: Text(v.nombre)),
                    ),
                  ],
                  onChanged: (v) => variant = v,
                ),
              TextField(
                controller: qty,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Cantidad'),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancelar'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Agregar'),
            ),
          ],
        ),
      ),
    );
    if (ok == true &&
        await widget.controller.agregarDetalle(
          pedido.id,
          productoId: product.id,
          varianteId: variant,
          cantidad: int.tryParse(qty.text) ?? 1,
        )) {
      await refresh();
    }
    qty.dispose();
  }

  Future<Producto> _productDetail(Producto product) async {
    final shop = widget.auth.activeReposteria;
    final token = widget.auth.accessToken;
    if (shop == null || token == null || !product.tieneVariantes) {
      return product;
    }
    try {
      return await CatalogoService(
        ApiClient(),
      ).producto(reposteriaId: shop.id, productoId: product.id, token: token);
    } catch (_) {
      return product;
    }
  }

  Future<void> _quantity(PedidoDetalle d) async {
    final q = TextEditingController(text: '${d.cantidad}');
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Modificar cantidad'),
        content: TextField(controller: q, keyboardType: TextInputType.number),
        actions: [
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Guardar'),
          ),
        ],
      ),
    );
    if (ok == true &&
        await widget.controller.editarDetalle(
          pedido.id,
          d.id,
          int.tryParse(q.text) ?? d.cantidad,
        )) {
      await refresh();
    }
    q.dispose();
  }
}
