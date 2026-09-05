import 'package:flutter/material.dart';

import '../models/categoria.dart';
import '../models/producto.dart';
import '../models/producto_variante.dart';
import '../services/api_client.dart';
import '../services/api_exception.dart';
import '../services/catalogo_service.dart';
import '../widgets/producto_card.dart';

class ProductoDetalleScreen extends StatefulWidget {
  const ProductoDetalleScreen({
    super.key,
    required this.productoId,
    required this.reposteriaId,
    required this.token,
    required this.categorias,
    required this.onUnauthorized,
    this.service,
  });

  final int productoId;
  final int reposteriaId;
  final String token;
  final List<Categoria> categorias;
  final Future<void> Function() onUnauthorized;
  final CatalogoService? service;

  @override
  State<ProductoDetalleScreen> createState() => _ProductoDetalleScreenState();
}

class _ProductoDetalleScreenState extends State<ProductoDetalleScreen> {
  late final CatalogoService _service;
  Producto? _producto;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _service = widget.service ?? CatalogoService(ApiClient());
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final producto = await _service.producto(
        reposteriaId: widget.reposteriaId,
        productoId: widget.productoId,
        token: widget.token,
      );
      if (mounted) setState(() => _producto = producto);
    } on ApiException catch (exception) {
      if (exception.statusCode == 401) {
        await widget.onUnauthorized();
        return;
      }
      if (mounted) {
        setState(
          () => _error = switch (exception.statusCode) {
            403 => 'No tienes acceso a este producto.',
            404 => 'El producto ya no existe o no está disponible.',
            422 => 'La solicitud del producto no es válida.',
            _ => exception.message,
          },
        );
      }
    }
    if (mounted) setState(() => _loading = false);
  }

  String? get _categoriaNombre {
    final id = _producto?.categoriaId;
    for (final categoria in widget.categorias) {
      if (categoria.id == id) return categoria.nombre;
    }
    return null;
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Detalle del producto')),
    body: _loading
        ? const Center(child: CircularProgressIndicator())
        : _error != null
        ? Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(_error!, textAlign: TextAlign.center),
                  const SizedBox(height: 12),
                  FilledButton.tonalIcon(
                    onPressed: _load,
                    icon: const Icon(Icons.refresh),
                    label: const Text('Reintentar'),
                  ),
                ],
              ),
            ),
          )
        : _body(context, _producto!),
  );

  Widget _body(BuildContext context, Producto producto) => LayoutBuilder(
    builder: (context, constraints) {
      final image = SizedBox(
        height: constraints.maxWidth >= 760 ? 420 : 280,
        child: ProductoImage(imagen: producto.imagen),
      );
      final information = Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              producto.nombre,
              style: Theme.of(context).textTheme.headlineMedium,
            ),
            if (_categoriaNombre case final name?)
              Text(name, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            if (producto.descripcion case final description?) Text(description),
            const SizedBox(height: 16),
            _Price(
              precio: producto.precio,
              precioFinal: producto.precioFinal,
              promocion: producto.promocion?.nombre,
            ),
            const SizedBox(height: 8),
            Text(producto.disponible ? 'Disponible' : 'Sin stock'),
            if (producto.manejaStock) Text('Stock: ${producto.stock}'),
            if (producto.personalizable)
              const Padding(
                padding: EdgeInsets.only(top: 8),
                child: Chip(label: Text('Personalizable')),
              ),
            if (producto.variantes.isNotEmpty) ...[
              const SizedBox(height: 24),
              Text('Variantes', style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 8),
              ...producto.variantes.map(
                (variante) => _VarianteTile(variante: variante),
              ),
            ],
          ],
        ),
      );
      if (constraints.maxWidth >= 760) {
        return SingleChildScrollView(
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 1100),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(child: image),
                  Expanded(child: information),
                ],
              ),
            ),
          ),
        );
      }
      return ListView(children: [image, information]);
    },
  );
}

class _Price extends StatelessWidget {
  const _Price({
    required this.precio,
    required this.precioFinal,
    required this.promocion,
  });
  final String precio;
  final String precioFinal;
  final String? promocion;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      if (promocion != null)
        Text(
          'Bs $precio',
          style: const TextStyle(decoration: TextDecoration.lineThrough),
        ),
      Text(
        'Bs $precioFinal',
        style: Theme.of(context).textTheme.headlineSmall?.copyWith(
          color: Theme.of(context).colorScheme.primary,
          fontWeight: FontWeight.bold,
        ),
      ),
      if (promocion != null) Text(promocion!),
    ],
  );
}

class _VarianteTile extends StatelessWidget {
  const _VarianteTile({required this.variante});
  final ProductoVariante variante;

  @override
  Widget build(BuildContext context) => Card(
    child: ListTile(
      title: Text(variante.nombre),
      subtitle: variante.promocion == null
          ? null
          : Text(variante.promocion!.nombre),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (variante.promocion != null)
            Text(
              'Bs ${variante.precio}',
              style: const TextStyle(decoration: TextDecoration.lineThrough),
            ),
          Text(
            'Bs ${variante.precioFinal}',
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          Text(
            'Stock: ${variante.stock}',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    ),
  );
}
