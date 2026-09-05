import 'dart:async';

import 'package:flutter/material.dart';

import '../controllers/auth_controller.dart';
import '../controllers/catalogo_controller.dart';
import '../models/reposteria.dart';
import '../services/api_client.dart';
import '../services/catalogo_service.dart';
import '../widgets/producto_card.dart';
import '../widgets/adaptive_layout.dart';
import 'producto_detalle_screen.dart';

class CatalogoScreen extends StatefulWidget {
  const CatalogoScreen({
    super.key,
    required this.authController,
    this.controller,
  });
  final AuthController authController;
  final CatalogoController? controller;

  @override
  State<CatalogoScreen> createState() => _CatalogoScreenState();
}

class _CatalogoScreenState extends State<CatalogoScreen> {
  late final CatalogoController _controller;
  final _search = TextEditingController();
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _controller =
        widget.controller ??
        CatalogoController(
          CatalogoService(ApiClient()),
          onUnauthorized: _sessionExpired,
        );
    final active = widget.authController.activeReposteria;
    final token = widget.authController.accessToken;
    if (active != null && token != null) {
      _controller.load(reposteriaId: active.id, token: token);
    }
  }

  Future<void> _sessionExpired() async {
    _controller.clear();
    await widget.authController.expireSession();
    if (mounted) Navigator.of(context).popUntil((route) => route.isFirst);
  }

  Future<void> _changeReposteria(Reposteria value) async {
    _debounce?.cancel();
    _search.clear();
    _controller.clear();
    await widget.authController.selectReposteria(value);
    final token = widget.authController.accessToken;
    if (token != null) {
      await _controller.load(reposteriaId: value.id, token: token);
    }
  }

  void _onSearch(String value) {
    _debounce?.cancel();
    _debounce = Timer(
      const Duration(milliseconds: 450),
      () => _controller.setSearch(value),
    );
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    if (widget.controller == null) _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: _controller,
    builder: (context, _) => AdaptiveLayout(
      mobile: (_) => Scaffold(
        appBar: AppBar(title: const Text('Catálogo')),
        body: Column(
          children: [
            _filters(context),
            Expanded(child: _content(context)),
          ],
        ),
      ),
      desktop: (_) => Scaffold(
        appBar: AppBar(
          title: Text(
            'Catálogo · ${widget.authController.activeReposteria?.nombre ?? ''}',
          ),
        ),
        body: Row(
          children: [
            SizedBox(
              width: 310,
              child: SingleChildScrollView(child: _filters(context)),
            ),
            const VerticalDivider(width: 1),
            Expanded(child: _content(context)),
          ],
        ),
      ),
    ),
  );

  Widget _filters(BuildContext context) {
    final user = widget.authController.user!;
    return Material(
      elevation: 1,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
        child: Column(
          children: [
            DropdownButtonFormField<Reposteria>(
              initialValue: widget.authController.activeReposteria,
              decoration: const InputDecoration(
                labelText: 'Repostería',
                border: OutlineInputBorder(),
              ),
              items: user.reposterias
                  .map(
                    (item) =>
                        DropdownMenuItem(value: item, child: Text(item.nombre)),
                  )
                  .toList(growable: false),
              onChanged: (value) {
                if (value != null) _changeReposteria(value);
              },
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _search,
              maxLength: 100,
              onChanged: _onSearch,
              decoration: const InputDecoration(
                prefixIcon: Icon(Icons.search),
                hintText: 'Buscar producto...',
                border: OutlineInputBorder(),
                counterText: '',
              ),
            ),
            const SizedBox(height: 10),
            SizedBox(
              height: 42,
              child: ListView(
                scrollDirection: Axis.horizontal,
                children: [
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: const Text('Todos'),
                      selected: _controller.categoriaId == null,
                      onSelected: (_) => _controller.setCategoria(null),
                    ),
                  ),
                  ..._controller.categorias.map(
                    (categoria) => Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(
                        label: Text(categoria.nombre),
                        selected: _controller.categoriaId == categoria.id,
                        onSelected: (_) =>
                            _controller.setCategoria(categoria.id),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _content(BuildContext context) {
    if (_controller.status == CatalogoStatus.loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_controller.status == CatalogoStatus.error) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.cloud_off_outlined, size: 52),
              const SizedBox(height: 12),
              Text(
                _controller.error ?? 'No se pudo cargar el catálogo.',
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 12),
              FilledButton.tonalIcon(
                onPressed: _controller.retry,
                icon: const Icon(Icons.refresh),
                label: const Text('Reintentar'),
              ),
            ],
          ),
        ),
      );
    }
    if (_controller.productos.isEmpty) {
      return const Center(child: Text('No hay productos disponibles.'));
    }
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 1100
            ? 4
            : constraints.maxWidth >= 760
            ? 3
            : constraints.maxWidth >= 480
            ? 2
            : 1;
        return CustomScrollView(
          slivers: [
            SliverPadding(
              padding: const EdgeInsets.all(16),
              sliver: SliverGrid(
                gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: columns,
                  mainAxisSpacing: 12,
                  crossAxisSpacing: 12,
                  mainAxisExtent: 300,
                ),
                delegate: SliverChildBuilderDelegate((context, index) {
                  final producto = _controller.productos[index];
                  return ProductoCard(
                    producto: producto,
                    onTap: () {
                      final reposteria = widget.authController.activeReposteria;
                      final token = widget.authController.accessToken;
                      if (reposteria == null || token == null) return;
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => ProductoDetalleScreen(
                            productoId: producto.id,
                            reposteriaId: reposteria.id,
                            token: token,
                            categorias: _controller.categorias,
                            onUnauthorized: _sessionExpired,
                          ),
                        ),
                      );
                    },
                  );
                }, childCount: _controller.productos.length),
              ),
            ),
            if (_controller.tieneMas)
              SliverToBoxAdapter(
                child: Center(
                  child: Padding(
                    padding: const EdgeInsets.only(bottom: 24),
                    child: FilledButton.tonal(
                      onPressed: _controller.loadingMore
                          ? null
                          : _controller.loadMore,
                      child: _controller.loadingMore
                          ? const SizedBox.square(
                              dimension: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Text('Cargar más'),
                    ),
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}
