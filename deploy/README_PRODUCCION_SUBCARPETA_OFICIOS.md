# Despliegue en produccion - Subcarpeta /oficios

Documento operativo para desplegar **Respaldo de Oficios** (Tesoreria
Municipal STIyC) en un servidor donde Apache ya hospeda otras aplicaciones.
La app NO usa hostname propio (no `oficios.test`), NO es la raiz del host,
NO es VirtualHost dedicado y NO debe romper otras apps existentes.

## Modelo de despliegue

- La app vive en disco bajo: `C:/laragon/www/oficios`.
- Se sirve via **Apache Alias**: la URL `/oficios` se mapea al directorio
  `C:/laragon/www/oficios/src/public`.
- Convive con cualquier otro VirtualHost o aplicacion que ya use el puerto 8080.
- No tiene DocumentRoot propio: solo el `<Directory>` del Alias.

## Variables de entorno

Archivo `.env` (raiz del repo):

```
APP_URL=http://187.174.221.162:8080
APP_BASE_PATH=/oficios
```

- `APP_URL` **NO** lleva `/oficios`. Es solo el origen.
- `APP_BASE_PATH` es la subcarpeta. Sin slash final.
- La URL completa de cualquier endpoint = `APP_URL + APP_BASE_PATH + ruta`.

Ejemplo: la ruta interna `/dashboard` se sirve en
`http://187.174.221.162:8080/oficios/dashboard`.

## URLs esperadas

Correctas:

- `http://187.174.221.162:8080/oficios` -> redirige a dashboard
- `http://187.174.221.162:8080/oficios/login`
- `http://187.174.221.162:8080/oficios/dashboard`
- `http://187.174.221.162:8080/oficios/oficios` (modulo Oficios)
- `http://187.174.221.162:8080/oficios/oficios/123` (detalle de oficio 123)
- `http://187.174.221.162:8080/oficios/assets/css/app.css`

INCORRECTAS (NO debe haberlas):

- `http://187.174.221.162:8080/login` (sin `/oficios`) -> 404
- `http://oficios.test:8080/...` (hostname propio, ya no aplica)
- `http://localhost:8090/...` (era el modo Docker antiguo, deprecado)
- `http://187.174.221.162:8080/oficios/oficios/oficios/...` (doble prefijo,
  significaria un bug en `url_path()` o en el Router)

## Configuracion Apache Alias

Archivo: `deploy/apache-oficios-subpath.conf`. Snippet listo para copiar:

```apache
Alias /oficios "C:/laragon/www/oficios/src/public"

<Directory "C:/laragon/www/oficios/src/public">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php
</Directory>

<DirectoryMatch "C:/laragon/www/oficios/(sql|storage|backups|tests|tools|deploy|docker|vendor|paquete_produccion)">
    Require all denied
</DirectoryMatch>

<FilesMatch "\.(env|sql|log|sh|md|yml|yaml|json|ini|bak|swp|git|rar|zip)$">
    Require all denied
</FilesMatch>
```

Colocacion sugerida:

- `C:/laragon/etc/apache2/sites-enabled/apache-oficios-subpath.conf`
- O bien hacer `Include` desde `httpd.conf`.

Modulos requeridos en `httpd.conf`:

- `LoadModule rewrite_module modules/mod_rewrite.so`
- `LoadModule alias_module modules/mod_alias.so`
- `LoadModule headers_module modules/mod_headers.so`

Tambien debe haber `Listen 8080`.

## Pasos en el servidor

1. Subir/actualizar el repo a `C:/laragon/www/oficios`.
2. Ejecutar:
   ```powershell
   powershell -ExecutionPolicy Bypass -File C:/laragon/www/oficios/deploy/setup-subpath-oficios-production.ps1
   ```
   Esto crea/actualiza `.env` (preservando `APP_SECRET` y `DB_PASS` si ya
   existen), valida estructura y muestra la configuracion Alias a copiar.
3. Copiar `deploy/apache-oficios-subpath.conf` a
   `C:/laragon/etc/apache2/sites-enabled/`.
4. Confirmar `Listen 8080` en `httpd.conf`.
5. Reiniciar Apache (Laragon -> Reload, o `httpd -k restart`).
6. Probar:
   - `curl -I http://187.174.221.162:8080/oficios/login` -> 200 OK
   - `curl -I http://187.174.221.162:8080/oficios/.env` -> 403/404
7. Verificar que las otras apps del servidor sigan funcionando exactamente
   igual (no se toco ningun VirtualHost ni DocumentRoot global).

## Logs

- Apache:
  - `C:/laragon/logs/apache_error.log`
  - `C:/laragon/logs/apache_access.log`
- Aplicacion (PHP `error_log`):
  - `C:/laragon/www/oficios/src/storage/logs/php_errors.log`

## Troubleshooting

- `404` en `/oficios/login`: revisa `AllowOverride All` en el `<Directory>`
  del Alias y que el modulo `rewrite_module` este cargado.
- Login deja al usuario sin sesion: revisa que la cookie de sesion no este
  fijada en otro path. La cookie por defecto se sirve en `/`, lo cual sigue
  funcionando bajo `/oficios`.
- Assets cargan con 404: significa que `asset_url()` o el Alias estan mal.
  La URL final debe ser `/oficios/assets/css/app.css`.
- Pagina blanca: revisar `src/storage/logs/php_errors.log`.

## Como verificar que otras apps siguen vivas

Probar las URLs originales de las otras apps en el mismo Apache. Como este
despliegue solo agrega un `Alias` nuevo y NO toca DocumentRoot/VirtualHosts,
no deberia afectar nada.

## Permisos

- `uploads/` debe ser escribible por el usuario de Apache.
- `src/storage/logs/` debe ser escribible por el usuario de Apache.

## Resumen

- VirtualHost: NO.
- Hostname propio: NO.
- DocumentRoot global modificado: NO.
- Mecanismo: Apache `Alias /oficios` -> `src/public`.
- Variables clave: `APP_URL`, `APP_BASE_PATH`.
- Helpers: `base_path()`, `url_path()`, `app_url()`, `asset_url()`,
  `redirect_to()`, `window.appPath()`.
