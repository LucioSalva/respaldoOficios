# Respaldo de Oficios - Produccion PHP puro

Documento oficial de despliegue tras la reorganizacion estructural a PHP puro
tradicional (public/ en raiz + app/ con codigo).

## 1. URL de produccion

- Correcta: `http://187.174.221.162:8080/oficios`
- Incorrectas (no aplican):
  - `http://oficios.test:8080`
  - `http://localhost:8090`
  - Cualquier ruta a `src/public` (la carpeta `src/` ya no existe)

## 2. Estructura fisica

```
C:/laragon/www/oficios/
├── public/                      <-- DocumentRoot del Alias /oficios
│   ├── index.php                (front controller)
│   ├── .htaccess                (RewriteBase /oficios/, headers de seguridad)
│   └── assets/
│       ├── css/app.css
│       ├── js/app.js
│       ├── img/
│       ├── scss/app.scss        (fuente opcional, no usada en runtime)
│       └── vendor/bootstrap/    (CSS/JS local, opcional - ver README ahi)
├── app/                         <-- codigo de la app (fuera del webroot)
│   ├── config/
│   │   ├── paths.php            (BASE_PATH, APP_PATH, VIEWS_PATH, etc.)
│   │   ├── config.php           (lee .env, define APP_*, DB_*, etc.)
│   │   ├── database.php         (wrapper compat -> core/Database.php)
│   │   └── session.php          (configurar_sesion segura)
│   ├── core/
│   │   ├── Database.php         (clase singleton PDO)
│   │   ├── Session.php          (helpers OO sobre $_SESSION)
│   │   ├── Response.php         (json, redirect, file_download)
│   │   ├── Router.php
│   │   ├── Controller.php
│   │   ├── Auth.php
│   │   ├── ErrorHandler.php
│   │   ├── Validator.php
│   │   ├── FolioService.php
│   │   └── VacacionesService.php
│   ├── controllers/             (11 controladores)
│   ├── models/                  (3 modelos)
│   ├── views/                   (auth, dashboard, oficios, ... layouts)
│   └── helpers/
│       ├── url_helper.php       (base_path, url_path, app_url, asset_url, redirect_to)
│       ├── auth_helper.php      (current_user, is_logged_in, has_role, ...)
│       ├── csrf_helper.php      (csrf_token, csrf_field, csrf_validate)
│       └── upload_helper.php    (validar MIME real, sanitizar nombre, generar fisico)
├── uploads/                     <-- archivos subidos (NO web)
├── storage/
│   ├── logs/                    (php_errors.log, app.log)
│   └── cache/
├── sql/                         (migraciones numeradas)
├── deploy/
│   ├── apache-alias-oficios.conf      (config Apache OFICIAL)
│   ├── setup-produccion-php-puro.ps1  (script idempotente)
│   └── README_PRODUCCION_PHP_PURO.md  (este archivo)
├── backups/                     (zip de src/ pre-migracion + .bak)
├── .env                         (NO se sube a git, NO se expone)
└── README.md
```

## 3. Variables de entorno (.env)

```
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=respaldooficios
DB_USER=postgres
DB_PASS=admin                # ajustar si la clave real difiere

APP_ENV=production
APP_DEBUG=false              # APP_DEBUG se FUERZA a false en produccion

APP_URL=http://187.174.221.162:8080
APP_BASE_PATH=/oficios

APP_SECRET=...               # secreto institucional, no rotarlo sin coordinar

SESSION_LIFETIME=7200
SESSION_NAME=respaldo_oficios_sess

CSRF_TOKEN_NAME=csrf_token

UPLOAD_MAX_SIZE=10485760
UPLOAD_DIR=C:/laragon/www/oficios/uploads

APP_TIMEZONE=America/Mexico_City
```

## 4. Apache - snippet listo para Include

```apache
Alias /oficios "C:/laragon/www/oficios/public"

<Directory "C:/laragon/www/oficios/public">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php
</Directory>

<DirectoryMatch "C:/laragon/www/oficios/(app|sql|storage|uploads|deploy|backups|tests|tools|docker|vendor|paquete_produccion)">
    Require all denied
</DirectoryMatch>

<FilesMatch "\.(env|sql|log|sh|md|yml|yaml|json|ini|bak|swp|git|rar|zip)$">
    Require all denied
</FilesMatch>
```

(El archivo completo con headers de seguridad esta en `apache-alias-oficios.conf`.)

## 5. Pasos manuales en el servidor

1. Sincronizar el repositorio en `C:/laragon/www/oficios/` (git pull o copia).
2. Verificar que existen las carpetas `public/`, `app/`, `storage/`, `uploads/`.
3. Verificar `.env` con la clave real de Postgres en `DB_PASS`.
4. Ejecutar como Administrador en PowerShell:
   ```
   cd C:\laragon\www\oficios\deploy
   .\setup-produccion-php-puro.ps1
   ```
5. Reiniciar Apache desde Laragon (o `httpd -k restart`).
6. Probar:
   - `http://187.174.221.162:8080/oficios` -> redirige a login.
   - Login con usuario god -> dashboard.
   - Navegar oficios, vacaciones, incidencias.
7. Validar que NO se accede a:
   - `http://187.174.221.162:8080/oficios/.env` (debe dar 403).
   - `http://187.174.221.162:8080/oficios/app/config/config.php` (debe dar 403).

## 6. Prueba rapida de Postgres

```
psql -h 127.0.0.1 -U postgres -d respaldooficios -c "SELECT count(*) FROM oficios;"
```

Si responde un numero, la cadena de conexion en `app/core/Database.php` lee
correctamente el `.env`.

## 7. Logs en produccion

- PHP errors: `storage/logs/php_errors.log`
- App handler: `storage/logs/app.log` (incidentes con id y stack)

`APP_DEBUG=false` impide que cualquier stack trace se filtre al cliente: el
usuario solo ve la pagina 500 con el id del incidente.

## 8. Rollback

Si algo falla tras el despliegue:

1. Restaurar el archivo de Apache desde `*.bak.<timestamp>` en
   `C:/laragon/etc/apache2/sites-enabled/`.
2. Reiniciar Apache.
3. La aplicacion previa queda accesible (mantenemos los configs antiguos
   `apache-oficios-subpath.conf` y `auto.oficios.conf` como historicos en
   `deploy/`, ya no oficiales).

## 9. Pruebas rapidas (smoke)

- `GET /oficios` -> 302 redirige a `/oficios/login`.
- `GET /oficios/login` -> 200 con form (incluye token CSRF oculto).
- Activos estaticos: `/oficios/assets/css/app.css` -> 200.
- `GET /oficios/app/config/config.php` -> 403 (bloqueado por DirectoryMatch).
- `GET /oficios/.env` -> 403 (bloqueado por FilesMatch).
