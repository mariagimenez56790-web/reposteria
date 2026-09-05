# Repostería Flutter

Cliente Flutter de la API Laravel del proyecto Repostería.

## Ejecutar la integración local

Primero inicia Laravel desde `reposteria-api`:

```powershell
php artisan serve
```

En el emulador Android, la URL predeterminada ya apunta al host mediante
`http://10.0.2.2:8000`. Para otro destino, define la URL al compilar:

```powershell
flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000
```

En Windows o web local normalmente se usa:

```powershell
flutter run -d windows --dart-define=API_BASE_URL=http://127.0.0.1:8000
```

Usa HTTPS en producción. El tráfico HTTP sin cifrar solo está habilitado en la
compilación Android de depuración.

## Alcance actual

- Login con `POST /api/login`.
- Token Sanctum guardado con almacenamiento seguro.
- Restauración de sesión mediante `GET /api/me` y Bearer token.
- Recuperación del usuario y sus reposterías aprobadas.
- Selección persistente de repostería activa.
- Logout remoto y limpieza local de la sesión.
- Catálogo real de la repostería activa: categorías, búsqueda, filtro,
  paginación, precios promocionales, detalle y variantes.

Las imágenes se muestran cuando `imagen` contiene una URL HTTP/HTTPS absoluta.
Los valores nulos o rutas sin URL pública utilizan un placeholder; esta etapa no
modifica Laravel ni implementa cargas de archivos.

## Presentación móvil y PC/web

El breakpoint responsive es **840 px**. Por debajo se usa navegación móvil,
tarjetas, formularios verticales y acciones táctiles. Desde 840 px se usa una
navegación lateral estable, tablas, paneles y formularios con mayor densidad.
Ambas presentaciones comparten sesión, controladores, servicios y modelos.

Los módulos disponibles actualmente son catálogo, clientes y pedidos. Los
pedidos permiten creación y edición mientras estén pendientes, gestión de sus
detalles y transiciones autorizadas por el rol. Precios, promociones,
subtotales y total siempre son calculados por Laravel.

También está disponible el módulo de ventas para `admin` y `vendedor`: venta
directa, conversión desde pedidos listos o entregados, pagos y consulta del
detalle financiero. La anulación de ventas y eliminación de pagos solo se
muestran a `admin`/`superadmin`. Todos los montos visualizados provienen de la
API y se conservan como cadenas decimales.

Para validar:

```powershell
flutter analyze
flutter test
```
