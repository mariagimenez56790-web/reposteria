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

Para validar:

```powershell
flutter analyze
flutter test
```
