import 'dart:async';
import 'package:flutter/material.dart';
import '../controllers/auth_controller.dart';
import '../controllers/cliente_controller.dart';
import '../models/cliente.dart';
import '../services/api_client.dart';
import '../services/cliente_service.dart';
import '../widgets/adaptive_layout.dart';

class ClientesScreen extends StatefulWidget {
  const ClientesScreen({super.key, required this.auth, this.controller});
  final AuthController auth;
  final ClienteController? controller;
  @override
  State<ClientesScreen> createState() => _ClientesScreenState();
}

class _ClientesScreenState extends State<ClientesScreen> {
  late final ClienteController controller;
  final search = TextEditingController();
  Timer? debounce;
  @override
  void initState() {
    super.initState();
    controller =
        widget.controller ??
        ClienteController(
          ClienteService(ApiClient()),
          onUnauthorized: _expired,
        );
    _load();
  }

  Future<void> _load() async {
    final shop = widget.auth.activeReposteria;
    final token = widget.auth.accessToken;
    if (shop != null && token != null) {
      await controller.load(reposteriaId: shop.id, token: token);
    }
  }

  Future<void> _expired() async {
    controller.clear();
    await widget.auth.expireSession();
    if (mounted) Navigator.popUntil(context, (r) => r.isFirst);
  }

  @override
  void dispose() {
    debounce?.cancel();
    search.dispose();
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
    appBar: AppBar(title: const Text('Clientes')),
    floatingActionButton: FloatingActionButton.extended(
      onPressed: () => _form(),
      icon: const Icon(Icons.add),
      label: const Text('Nuevo'),
    ),
    body: Column(
      children: [
        _search(),
        Expanded(child: _content(cards: true)),
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
          destinations: const [
            NavigationRailDestination(
              icon: Icon(Icons.home_outlined),
              selectedIcon: Icon(Icons.home),
              label: Text('Inicio'),
            ),
            NavigationRailDestination(
              icon: Icon(Icons.people_outline),
              selectedIcon: Icon(Icons.people),
              label: Text('Clientes'),
            ),
          ],
          labelType: NavigationRailLabelType.all,
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
                        'Clientes · ${widget.auth.activeReposteria?.nombre ?? ''}',
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                    ),
                    FilledButton.icon(
                      onPressed: () => _form(),
                      icon: const Icon(Icons.add),
                      label: const Text('Nuevo cliente'),
                    ),
                  ],
                ),
              ),
              _search(),
              Expanded(child: _content(cards: false)),
            ],
          ),
        ),
      ],
    ),
  );

  Widget _search() => Padding(
    padding: const EdgeInsets.all(16),
    child: TextField(
      controller: search,
      maxLength: 100,
      decoration: const InputDecoration(
        prefixIcon: Icon(Icons.search),
        hintText: 'Buscar cliente...',
        border: OutlineInputBorder(),
        counterText: '',
      ),
      onChanged: (value) {
        debounce?.cancel();
        debounce = Timer(
          const Duration(milliseconds: 400),
          () => controller.setSearch(value),
        );
      },
    ),
  );

  Widget _content({required bool cards}) {
    if (controller.status == DataStatus.loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (controller.status == DataStatus.error) {
      return _ErrorView(controller.error, controller.retry);
    }
    if (controller.clientes.isEmpty) {
      return const Center(child: Text('No hay clientes registrados.'));
    }
    if (cards) {
      return ListView.builder(
        padding: const EdgeInsets.fromLTRB(12, 0, 12, 96),
        itemCount: controller.clientes.length,
        itemBuilder: (_, i) {
          final c = controller.clientes[i];
          return Card(
            child: ListTile(
              leading: const CircleAvatar(child: Icon(Icons.person)),
              title: Text(c.nombre),
              subtitle: Text(
                [c.telefono, c.email].whereType<String>().join(' · '),
              ),
              onTap: () async {
                final detail = await controller.detalle(c.id);
                if (detail != null) await _form(detail);
              },
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
            DataColumn(label: Text('Nombre')),
            DataColumn(label: Text('Teléfono')),
            DataColumn(label: Text('Email')),
            DataColumn(label: Text('Dirección')),
            DataColumn(label: Text('Acciones')),
          ],
          rows: controller.clientes
              .map(
                (c) => DataRow(
                  cells: [
                    DataCell(Text(c.nombre)),
                    DataCell(Text(c.telefono ?? '—')),
                    DataCell(Text(c.email ?? '—')),
                    DataCell(Text(c.direccion ?? '—')),
                    DataCell(
                      IconButton(
                        icon: const Icon(Icons.edit_outlined),
                        onPressed: () async {
                          final detail = await controller.detalle(c.id);
                          if (detail != null) await _form(detail);
                        },
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

  Future<void> _form([Cliente? cliente]) async {
    final formKey = GlobalKey<FormState>();
    final nombre = TextEditingController(text: cliente?.nombre);
    final telefono = TextEditingController(text: cliente?.telefono);
    final email = TextEditingController(text: cliente?.email);
    final direccion = TextEditingController(text: cliente?.direccion);
    final notas = TextEditingController(text: cliente?.notas);
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(cliente == null ? 'Nuevo cliente' : 'Editar cliente'),
        content: SizedBox(
          width: 650,
          child: Form(
            key: formKey,
            child: LayoutBuilder(
              builder: (_, box) {
                final fields = [
                  _field(nombre, 'Nombre', required: true),
                  _field(telefono, 'Teléfono'),
                  _field(email, 'Email', email: true),
                  _field(direccion, 'Dirección'),
                  _field(notas, 'Notas', lines: 3),
                ];
                return box.maxWidth < 560
                    ? SingleChildScrollView(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: fields,
                        ),
                      )
                    : SingleChildScrollView(
                        child: Wrap(
                          spacing: 12,
                          runSpacing: 12,
                          children: fields
                              .map(
                                (f) => SizedBox(
                                  width: f == fields.last ? 552 : 270,
                                  child: f,
                                ),
                              )
                              .toList(),
                        ),
                      );
              },
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            onPressed: controller.saving
                ? null
                : () async {
                    if (!formKey.currentState!.validate()) return;
                    final ok = await controller.save({
                      'nombre': nombre.text.trim(),
                      'telefono': _null(telefono.text),
                      'email': _null(email.text),
                      'direccion': _null(direccion.text),
                      'notas': _null(notas.text),
                    }, clienteId: cliente?.id);
                    if (ok && dialogContext.mounted) {
                      Navigator.pop(dialogContext);
                    }
                  },
            child: const Text('Guardar'),
          ),
        ],
      ),
    );
    nombre.dispose();
    telefono.dispose();
    email.dispose();
    direccion.dispose();
    notas.dispose();
  }

  Widget _field(
    TextEditingController c,
    String label, {
    bool required = false,
    bool email = false,
    int lines = 1,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: TextFormField(
      controller: c,
      maxLines: lines,
      decoration: InputDecoration(
        labelText: label,
        border: const OutlineInputBorder(),
      ),
      validator: (v) {
        if (required && (v == null || v.trim().isEmpty)) {
          return 'Campo requerido';
        }
        if (email && v != null && v.isNotEmpty && !v.contains('@')) {
          return 'Email inválido';
        }
        return null;
      },
    ),
  );
  String? _null(String value) => value.trim().isEmpty ? null : value.trim();
}

class _ErrorView extends StatelessWidget {
  const _ErrorView(this.message, this.retry);
  final String? message;
  final VoidCallback retry;
  @override
  Widget build(BuildContext context) => Center(
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(message ?? 'No se pudo cargar.'),
        const SizedBox(height: 12),
        FilledButton.tonal(onPressed: retry, child: const Text('Reintentar')),
      ],
    ),
  );
}
