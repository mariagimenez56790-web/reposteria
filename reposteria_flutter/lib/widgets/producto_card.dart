import 'package:flutter/material.dart';
import '../models/producto.dart';

class ProductoCard extends StatelessWidget {
  const ProductoCard({super.key, required this.producto, required this.onTap});
  final Producto producto;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Card(
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(child: ProductoImage(imagen: producto.imagen)),
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  producto.nombre,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 6),
                if (producto.promocion != null) ...[
                  Text(
                    'Bs ${producto.precio}',
                    style: const TextStyle(
                      decoration: TextDecoration.lineThrough,
                    ),
                  ),
                  Text(
                    'Bs ${producto.precioFinal}',
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.primary,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  Text(
                    producto.promocion!.nombre,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ] else
                  Text(
                    'Bs ${producto.precioFinal}',
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                const SizedBox(height: 4),
                Text(
                  producto.disponible ? 'Disponible' : 'Sin stock',
                  style: TextStyle(
                    color: producto.disponible
                        ? Colors.green.shade700
                        : Theme.of(context).colorScheme.error,
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

class ProductoImage extends StatelessWidget {
  const ProductoImage({super.key, required this.imagen});
  final String? imagen;

  @override
  Widget build(BuildContext context) {
    final uri = imagen == null ? null : Uri.tryParse(imagen!);
    final usable =
        uri != null &&
        uri.hasScheme &&
        (uri.scheme == 'http' || uri.scheme == 'https');
    if (!usable) {
      return Container(
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
        child: const Center(child: Icon(Icons.cake_outlined, size: 52)),
      );
    }
    return Image.network(
      imagen!,
      fit: BoxFit.cover,
      errorBuilder: (_, _, _) => Container(
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
        child: const Center(child: Icon(Icons.broken_image_outlined, size: 44)),
      ),
    );
  }
}
