import 'package:flutter/material.dart';
import '../controllers/auth_controller.dart';
import '../controllers/cliente_controller.dart';
import '../controllers/venta_controller.dart';
import '../models/cliente.dart';
import '../models/pedido.dart';
import '../models/producto.dart';
import '../models/venta.dart';
import '../services/api_client.dart';
import '../services/catalogo_service.dart';
import '../services/cliente_service.dart';
import '../services/pedido_service.dart';
import '../services/venta_service.dart';
import '../widgets/adaptive_layout.dart';

class VentasScreen extends StatefulWidget {
  const VentasScreen({super.key, required this.auth, this.controller});
  final AuthController auth;
  final VentaController? controller;
  @override
  State<VentasScreen> createState() => _VentasScreenState();
}

class _VentasScreenState extends State<VentasScreen> {
  static const estados = ['pendiente', 'parcial', 'pagada', 'anulada'];
  late final VentaController controller;
  List<Cliente> clients = const [];
  List<Producto> products = const [];
  List<Pedido> orders = const [];
  @override
  void initState() {
    super.initState();
    controller =
        widget.controller ??
        VentaController(VentaService(ApiClient()), onUnauthorized: _expired);
    _load();
  }

  Future<void> _load() async {
    final shop = widget.auth.activeReposteria;
    final token = widget.auth.accessToken;
    if (shop == null || token == null) return;
    await controller.load(reposteriaId: shop.id, token: token);
    try {
      final r = await Future.wait([
        ClienteService(ApiClient()).listar(reposteriaId: shop.id, token: token),
        CatalogoService(
          ApiClient(),
        ).productos(reposteriaId: shop.id, token: token, perPage: 100),
        PedidoService(
          ApiClient(),
        ).listar(reposteriaId: shop.id, token: token, perPage: 100),
      ]);
      if (mounted) {
        setState(() {
          clients = (r[0] as PaginaClientes).items;
          products = (r[1] as PaginaProductos).productos;
          orders = (r[2] as PaginaPedidos).items
              .where((p) => p.estado == 'listo' || p.estado == 'entregado')
              .toList();
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
    appBar: AppBar(
      title: const Text('Ventas'),
      actions: [
        PopupMenuButton<String>(
          onSelected: (v) {
            if (v == 'direct') {
              _direct();
            } else {
              _fromOrder();
            }
          },
          itemBuilder: (_) => const [
            PopupMenuItem(value: 'direct', child: Text('Venta directa')),
            PopupMenuItem(value: 'order', child: Text('Desde pedido')),
          ],
        ),
      ],
    ),
    floatingActionButton: FloatingActionButton.extended(
      onPressed: _direct,
      icon: const Icon(Icons.add),
      label: const Text('Vender'),
    ),
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
              icon: Icon(Icons.point_of_sale),
              label: Text('Ventas'),
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
                        'Ventas · ${widget.auth.activeReposteria?.nombre ?? ''}',
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                    ),
                    OutlinedButton.icon(
                      onPressed: _fromOrder,
                      icon: const Icon(Icons.receipt_long),
                      label: const Text('Desde pedido'),
                    ),
                    const SizedBox(width: 8),
                    FilledButton.icon(
                      onPressed: _direct,
                      icon: const Icon(Icons.add),
                      label: const Text('Venta directa'),
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
    padding: const EdgeInsets.all(12),
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
              ...estados.map((e) => DropdownMenuItem(value: e, child: Text(e))),
            ],
            onChanged: controller.setEstado,
          ),
        ),
        if (clients.isNotEmpty) ...[
          const SizedBox(width: 10),
          Expanded(
            child: DropdownButtonFormField<int?>(
              initialValue: controller.clienteId,
              decoration: const InputDecoration(
                labelText: 'Cliente',
                border: OutlineInputBorder(),
              ),
              items: [
                const DropdownMenuItem(value: null, child: Text('Todos')),
                ...clients.map(
                  (c) => DropdownMenuItem(value: c.id, child: Text(c.nombre)),
                ),
              ],
              onChanged: controller.setCliente,
            ),
          ),
        ],
        IconButton(
          onPressed: controller.retry,
          icon: const Icon(Icons.refresh),
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
    if (controller.ventas.isEmpty) {
      return const Center(child: Text('No hay ventas registradas.'));
    }
    if (cards) {
      return ListView.builder(
        padding: const EdgeInsets.fromLTRB(12, 0, 12, 90),
        itemCount: controller.ventas.length,
        itemBuilder: (_, i) {
          final v = controller.ventas[i];
          return Card(
            color: v.anulada
                ? Theme.of(context).colorScheme.errorContainer
                : null,
            child: ListTile(
              title: Text('Venta #${v.id} · Bs ${v.total}'),
              subtitle: Text(
                '${v.cliente?.nombre ?? 'Sin cliente'}\nPagado Bs ${v.montoPagado} · Saldo Bs ${v.saldo}',
              ),
              isThreeLine: true,
              trailing: Chip(label: Text(v.estado)),
              onTap: () => _open(v),
            ),
          );
        },
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
            DataColumn(label: Text('Pagado')),
            DataColumn(label: Text('Saldo')),
            DataColumn(label: Text('')),
          ],
          rows: controller.ventas
              .map(
                (v) => DataRow(
                  cells: [
                    DataCell(Text('${v.id}')),
                    DataCell(Text(_date(v.fechaVenta))),
                    DataCell(Text(v.cliente?.nombre ?? 'Sin cliente')),
                    DataCell(Text(v.estado)),
                    DataCell(Text('Bs ${v.total}')),
                    DataCell(Text('Bs ${v.montoPagado}')),
                    DataCell(Text('Bs ${v.saldo}')),
                    DataCell(
                      IconButton(
                        onPressed: () => _open(v),
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

  Future<void> _open(Venta summary) async {
    final detail = await controller.detalle(summary.id);
    if (detail == null || !mounted) return;
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => VentaDetalleScreen(
          auth: widget.auth,
          controller: controller,
          venta: detail,
        ),
      ),
    );
    await controller.retry();
  }

  Future<Producto> _full(Producto p) async {
    final shop = widget.auth.activeReposteria;
    final token = widget.auth.accessToken;
    if (shop == null || token == null || !p.tieneVariantes) return p;
    try {
      return await CatalogoService(
        ApiClient(),
      ).producto(reposteriaId: shop.id, productoId: p.id, token: token);
    } catch (_) {
      return p;
    }
  }

  Future<void> _direct() async {
    if (products.isEmpty) return;
    int? client;
    String discount = '0.00';
    String? notes;
    final lines = <Map<String, dynamic>>[];
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setLocal) => AlertDialog(
          title: const Text('Venta directa'),
          content: SizedBox(
            width: 720,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  DropdownButtonFormField<int?>(
                    initialValue: client,
                    decoration: const InputDecoration(labelText: 'Cliente'),
                    items: [
                      const DropdownMenuItem(
                        value: null,
                        child: Text('Sin cliente'),
                      ),
                      ...clients.map(
                        (c) => DropdownMenuItem(
                          value: c.id,
                          child: Text(c.nombre),
                        ),
                      ),
                    ],
                    onChanged: (v) => client = v,
                  ),
                  TextFormField(
                    initialValue: discount,
                    decoration: const InputDecoration(labelText: 'Descuento'),
                    onChanged: (v) => discount = v,
                  ),
                  TextFormField(
                    decoration: const InputDecoration(
                      labelText: 'Observaciones',
                    ),
                    onChanged: (v) => notes = v,
                  ),
                  const Divider(),
                  ...lines.map(
                    (l) => ListTile(
                      title: Text(l['nombre'] as String),
                      subtitle: Text('Cantidad: ${l['cantidad']}'),
                    ),
                  ),
                  FilledButton.tonalIcon(
                    onPressed: () async {
                      final line = await _line(ctx);
                      if (line != null) setLocal(() => lines.add(line));
                    },
                    icon: const Icon(Icons.add),
                    label: const Text('Agregar producto'),
                  ),
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancelar'),
            ),
            FilledButton(
              onPressed: lines.isEmpty ? null : () => Navigator.pop(ctx, true),
              child: const Text('Confirmar venta'),
            ),
          ],
        ),
      ),
    );
    if (ok == true) {
      await controller.crearDirecta(
        clienteId: client,
        descuento: discount,
        observaciones: notes,
        detalles: lines
            .map(
              (l) => {
                'producto_id': l['producto_id'],
                'producto_variante_id': l['producto_variante_id'],
                'cantidad': l['cantidad'],
              },
            )
            .toList(),
      );
    }
  }

  Future<Map<String, dynamic>?> _line(BuildContext parent) async {
    Producto product = await _full(products.first);
    if (!parent.mounted) return null;
    int? variant;
    int qty = 1;
    return showDialog<Map<String, dynamic>>(
      context: parent,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setLocal) => AlertDialog(
          title: const Text('Producto'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<Producto>(
                initialValue: products.first,
                items: products
                    .map(
                      (p) => DropdownMenuItem(value: p, child: Text(p.nombre)),
                    )
                    .toList(),
                onChanged: (v) async {
                  if (v == null) return;
                  final full = await _full(v);
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
              TextFormField(
                initialValue: '1',
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Cantidad'),
                onChanged: (v) => qty = int.tryParse(v) ?? 0,
              ),
            ],
          ),
          actions: [
            FilledButton(
              onPressed: qty < 1
                  ? null
                  : () => Navigator.pop(ctx, {
                      'producto_id': product.id,
                      'producto_variante_id': variant,
                      'cantidad': qty,
                      'nombre': product.nombre,
                    }),
              child: const Text('Agregar'),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _fromOrder() async {
    if (orders.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('No hay pedidos listos o entregados disponibles.'),
        ),
      );
      return;
    }
    int order = orders.first.id;
    String discount = '0.00';
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Venta desde pedido'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            DropdownButtonFormField<int>(
              initialValue: order,
              items: orders
                  .map(
                    (p) => DropdownMenuItem(
                      value: p.id,
                      child: Text(
                        'Pedido #${p.id} · ${p.cliente?.nombre ?? 'Sin cliente'}',
                      ),
                    ),
                  )
                  .toList(),
              onChanged: (v) {
                if (v != null) order = v;
              },
            ),
            TextFormField(
              initialValue: discount,
              decoration: const InputDecoration(labelText: 'Descuento'),
              onChanged: (v) => discount = v,
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
            child: const Text('Crear venta'),
          ),
        ],
      ),
    );
    if (ok == true) await controller.desdePedido(order, descuento: discount);
  }

  String _date(DateTime d) =>
      '${d.day.toString().padLeft(2, '0')}/${d.month.toString().padLeft(2, '0')}/${d.year}';
}

class VentaDetalleScreen extends StatefulWidget {
  const VentaDetalleScreen({
    super.key,
    required this.auth,
    required this.controller,
    required this.venta,
  });
  final AuthController auth;
  final VentaController controller;
  final Venta venta;
  @override
  State<VentaDetalleScreen> createState() => _VentaDetalleScreenState();
}

class _VentaDetalleScreenState extends State<VentaDetalleScreen> {
  late Venta venta;
  bool get admin => widget.controller.canAdmin(widget.auth.user?.role);
  bool get operate => widget.controller.canOperate(widget.auth.user?.role);
  @override
  void initState() {
    super.initState();
    venta = widget.venta;
  }

  Future<void> refresh() async {
    final v = await widget.controller.detalle(venta.id);
    if (v != null && mounted) setState(() => venta = v);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text('Venta #${venta.id}')),
    body: AdaptiveLayout(
      mobile: (_) => _body(false),
      desktop: (_) => _body(true),
    ),
  );
  Widget _body(bool desktop) {
    final summary = Card(
      color: venta.anulada
          ? Theme.of(context).colorScheme.errorContainer
          : null,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              venta.estado.toUpperCase(),
              style: Theme.of(context).textTheme.titleLarge,
            ),
            Text('Cliente: ${venta.cliente?.nombre ?? 'Sin cliente'}'),
            Text(
              venta.pedido == null
                  ? 'Venta directa'
                  : 'Pedido #${venta.pedido!.id}',
            ),
            const Divider(),
            Text('Subtotal: Bs ${venta.subtotal}'),
            Text('Descuento: Bs ${venta.descuento}'),
            Text(
              'Total: Bs ${venta.total}',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
            Text('Pagado: Bs ${venta.montoPagado}'),
            Text('Saldo: Bs ${venta.saldo}'),
            if (operate && !venta.anulada && venta.saldo != '0.00')
              FilledButton.icon(
                onPressed: _pay,
                icon: const Icon(Icons.payments),
                label: const Text('Registrar pago'),
              ),
            if (admin && !venta.anulada)
              OutlinedButton.icon(
                onPressed: _cancel,
                icon: const Icon(Icons.cancel_outlined),
                label: const Text('Anular venta'),
              ),
          ],
        ),
      ),
    );
    final details = Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text('Detalles', style: Theme.of(context).textTheme.titleLarge),
        ...venta.detalles.map(
          (d) => Card(
            child: ListTile(
              title: Text(
                d.nombreVariante == null
                    ? d.nombreProducto
                    : '${d.nombreProducto} · ${d.nombreVariante}',
              ),
              subtitle: Text('${d.cantidad} × Bs ${d.precioUnitario}'),
              trailing: Text('Bs ${d.subtotal}'),
            ),
          ),
        ),
        const SizedBox(height: 16),
        Text('Pagos', style: Theme.of(context).textTheme.titleLarge),
        if (venta.pagos.isEmpty) const Text('Sin pagos registrados.'),
        ...venta.pagos.map(
          (p) => Card(
            child: ListTile(
              title: Text('${p.metodo} · Bs ${p.monto}'),
              subtitle: Text(p.referencia ?? ''),
              trailing: admin
                  ? IconButton(
                      onPressed: () => _deletePay(p),
                      icon: const Icon(Icons.delete_outline),
                    )
                  : null,
            ),
          ),
        ),
      ],
    );
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: desktop
          ? Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(child: details),
                const SizedBox(width: 16),
                SizedBox(width: 330, child: summary),
              ],
            )
          : Column(children: [summary, const SizedBox(height: 12), details]),
    );
  }

  Future<void> _pay() async {
    String method = 'efectivo', amount = '', reference = '', notes = '';
    final key = GlobalKey<FormState>();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Registrar pago'),
        content: Form(
          key: key,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<String>(
                initialValue: method,
                items:
                    const ['efectivo', 'transferencia', 'qr', 'tarjeta', 'otro']
                        .map((m) => DropdownMenuItem(value: m, child: Text(m)))
                        .toList(),
                onChanged: (v) {
                  if (v != null) method = v;
                },
              ),
              TextFormField(
                decoration: const InputDecoration(labelText: 'Monto'),
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                validator: (v) =>
                    (v == null || !RegExp(r'^\d+(\.\d{1,2})?$').hasMatch(v))
                    ? 'Monto inválido'
                    : null,
                onChanged: (v) => amount = v,
              ),
              TextFormField(
                decoration: const InputDecoration(labelText: 'Referencia'),
                onChanged: (v) => reference = v,
              ),
              TextFormField(
                decoration: const InputDecoration(labelText: 'Observaciones'),
                onChanged: (v) => notes = v,
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, key.currentState!.validate()),
            child: const Text('Pagar'),
          ),
        ],
      ),
    );
    if (ok == true &&
        await widget.controller.pagar(
          venta.id,
          metodo: method,
          monto: amount,
          referencia: reference.isEmpty ? null : reference,
          observaciones: notes.isEmpty ? null : notes,
        )) {
      await refresh();
    }
  }

  Future<void> _deletePay(Pago p) async {
    if (await widget.controller.eliminarPago(venta.id, p.id)) await refresh();
  }

  Future<void> _cancel() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Anular venta'),
        content: const Text(
          'La venta se conservará como historial. ¿Continuar?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('No'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Anular'),
          ),
        ],
      ),
    );
    if (ok == true && await widget.controller.anular(venta.id)) await refresh();
  }
}
