# Respaldo de Oficios

Sistema interno de gestión y seguimiento de oficios institucionales para la Tesorería Municipal — STIyC (Subdirección de Tecnologías de la Información y Comunicaciones).

---

## Descripción

Aplicación web interna (no expuesta a internet) para registrar, dar seguimiento y archivar oficios recibidos y emitidos por la Tesorería. Incluye:

- Registro de oficios EXTERNOS (numeración 1..9999, por año), INTERNOS (10000..19999, global) y de CONOCIMIENTO (20000..2999999, global).
- Folio de tesorería generado automáticamente con formato `TM/ECA/STIyC/XXXX/YYYY` (LPAD a 4 dígitos solo cuando el número es menor a 10000; a partir de 10000 se imprime tal cual).
- Reserva atómica del siguiente folio con `pg_advisory_xact_lock` en transacción (`FolioService::generarNuevoFolio`).
- Seguimiento de estados (Recibido → En Proceso → Contestado → Archivado) y registro de movimientos.
- Subida y descarga de evidencias PDF (archivos fuera del webroot, nombres en disco generados con `random_bytes`).
- Importación masiva desde Excel a tablas de *staging* con los scripts Python en `tools/`.
- Gestión de usuarios con roles (GOD, Admin, User) y bitácora de auditoría.
- Catálogos de dependencias, estados, tipos de evidencia y tipos de oficio.

---

## Requisitos

- Docker Desktop 20+ con Docker Compose v2 (solo se contenedoriza la app PHP).
- PostgreSQL 17.7 disponible en el host (la app se conecta vía `host.docker.internal`).
- Puertos disponibles: **8090** (web) y el puerto de Postgres del host (por defecto 5432).
- Para los importadores Python: Python 3.10+, `pip install openpyxl pandas psycopg2-binary`.

---

## Pasos para Levantar el Sistema

### 1. Copiar variables de entorno

```bash
cp .env.example .env
```

Editar `.env` con los valores reales del ambiente:

- `APP_ENV` → `production` en despliegue real (fuerza `APP_DEBUG=false` sin importar lo que digan las variables).
- `APP_DEBUG` → `false` en producción, `true` solo en desarrollo local.
- `APP_SECRET` → cadena aleatoria larga (mínimo 64 caracteres).
- `DB_HOST` → `host.docker.internal` si Postgres corre en el host Windows; `db` si agregas un servicio Postgres al compose.
- `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` → credenciales reales del Postgres del host.

### 2. Crear base de datos y aplicar migraciones

Con Postgres 17.7 corriendo en el host:

```bash
# Crear usuario y base de datos (ajusta nombres)
psql -U postgres -c "CREATE ROLE oficios_user LOGIN PASSWORD 'cambia-esto';"
psql -U postgres -c "CREATE DATABASE respaldo_oficios OWNER oficios_user;"

# Aplicar el schema y los seeds en orden
psql -U oficios_user -d respaldo_oficios -f sql/01_schema.sql
psql -U oficios_user -d respaldo_oficios -f sql/02_seed.sql
psql -U oficios_user -d respaldo_oficios -f sql/03_import_excel.sql
# ... y todas las migraciones adicionales en orden numérico
psql -U oficios_user -d respaldo_oficios -f sql/09_fix_folio_y_trazabilidad.sql
psql -U oficios_user -d respaldo_oficios -f sql/10_fix_constraints_y_uniques.sql
```

### 3. Construir e iniciar el contenedor de la aplicación

```bash
docker compose up --build -d
```

La primera vez descarga la imagen base y construye la imagen PHP (2-5 minutos).

### 4. Verificar que todo funciona

```bash
docker compose ps
docker compose logs app
```

### 5. Acceder al sistema

Abrir en el navegador: **http://localhost:8090**

---

## Primer Acceso (crear el usuario GOD)

El seed del repositorio **NO** crea usuarios con contraseñas públicas. Los usuarios demo (`admin` / `usuario`) quedan marcados como inactivos o son eliminados por la migración `sql/10_fix_constraints_y_uniques.sql`.

Para crear el primer usuario GOD real ejecuta directamente en Postgres (con la contraseña que tú eligas):

```bash
# Genera el hash bcrypt desde PHP dentro del contenedor
docker compose exec app php -r "echo password_hash('TuPasswordReal123!', PASSWORD_BCRYPT, ['cost'=>12]), PHP_EOL;"

# Inserta el usuario GOD (reemplaza el hash devuelto arriba)
psql -U oficios_user -d respaldo_oficios <<SQL
INSERT INTO usuarios (nombre, username, email, password_hash, rol_id, activo)
VALUES ('Nombre Apellido', 'miusuario', 'micorreo@dominio', '<HASH_GENERADO>', 1, TRUE)
ON CONFLICT (username) DO NOTHING;
SQL
```

Requisitos de contraseña del sistema (aplicados por `Validator::passwordComplex`):

- Mínimo 8 caracteres.
- Al menos una letra mayúscula, una minúscula y un dígito.

Luego podrás iniciar sesión en `/login` con tu usuario **o** con tu correo electrónico.

### Política de bloqueo por intentos fallidos

- Máximo **5 intentos fallidos** por *username*/correo o por IP en una ventana rodante de **10 minutos**.
- Los intentos se registran en la tabla `login_intentos` (creada por la migración 10) y quedan también en `bitacora`.

---

## Cómo Cambiar la Contraseña de un Usuario

1. Accede con una cuenta GOD o Admin.
2. Ve a **Usuarios** en el menú y selecciona “Editar” en el usuario deseado.
3. Captura la nueva contraseña (cumpliendo los requisitos de complejidad) y guarda.

Para la rotación de contraseña del propio usuario logueado, la misma pantalla de Editar está disponible para administradores. Un flujo de auto-servicio (usuario cambiando su propia contraseña sin pasar por un admin) no está habilitado en esta versión.

---

## Estructura del Proyecto

```
respaldoOficios/
├── docker-compose.yml                 # Orquestación Docker (solo servicio app)
├── Dockerfile                         # Imagen PHP 8.2 + Apache
├── .env                               # Variables de entorno (no commitear)
├── .env.example                       # Plantilla de variables
├── sql/
│   ├── 01_schema.sql                  # Schema PostgreSQL base
│   ├── 02_seed.sql                    # Catálogos y usuarios demo (demo desactivados)
│   ├── 03_import_excel.sql            # Tablas staging iniciales
│   ├── 04 .. 08 *.sql                 # Migraciones incrementales
│   ├── 09_fix_folio_y_trazabilidad.sql# Corrige folio_tesoreria, reconstruye vistas,
│   │                                  # garantiza movimiento inicial y dependencia en EXTERNOS
│   └── 10_fix_constraints_y_uniques.sql # UNIQUE parciales de personal, login_intentos,
│                                        # limpieza de seeds 'admin'/'usuario'
├── src/
│   ├── config/                        # Configuración (BD, sesión, constantes)
│   ├── core/                          # Router, Controller, Auth, Validator, FolioService, ErrorHandler
│   ├── controllers/                   # Controladores por módulo
│   ├── views/                         # Vistas PHP por módulo
│   └── public/
│       ├── index.php                  # Front controller
│       ├── .htaccess                  # Rewrite + deny directo a .php
│       └── assets/
│           ├── css/app.css            # Estilos compilados
│           ├── scss/app.scss          # Fuente SCSS (opcional)
│           └── js/app.js              # JavaScript vanilla
├── storage/
│   └── logs/                          # Logs de la aplicación (app.log)
├── uploads/                           # PDFs subidos (fuera del webroot)
├── tests/                             # Scripts CLI de verificación (PHP/SQL)
└── tools/
    ├── importar_personal.py
    ├── importar_vacaciones.py
    ├── importar_internos.py
    ├── importar_incidencias.py
    ├── importar_oficios_conocimiento.py
    └── diagnose_uploads.py            # Reporte de archivos huérfanos en uploads/
```

---

## Cómo Importar los Archivos Excel Históricos

Los importadores viven en `tools/` y trabajan contra tablas de *staging* (`import_*_raw`). El ciclo típico es:

1. Subir el Excel desde la pantalla **Importar datos** del sistema. El archivo se guarda en `uploads/imports/` con nombre seguro.
2. Ejecutar el importador Python en modo `--staging` para cargar filas crudas.
3. Revisar errores/filas pendientes desde la UI.
4. Ejecutar `--importar` para consolidar a las tablas finales.

```bash
# Personal (BASE VACACIONES 2026.xlsx / BASE GENERAL.xlsx)
python tools/importar_personal.py --staging
python tools/importar_personal.py --importar

# Vacaciones (3 hojas: VACACIONES, VACACIONES 2025, VACACIONES SINDICALIZADOS 2026)
python tools/importar_vacaciones.py --staging
python tools/importar_vacaciones.py --importar

# Folios Interno Sub (reproceso)
python tools/importar_internos.py --staging
python tools/importar_internos.py --importar

# Incidencias
python tools/importar_incidencias.py --staging
python tools/importar_incidencias.py --importar

# Oficios de Conocimiento
python tools/importar_conocimiento.py --staging
python tools/importar_conocimiento.py --importar
```

---

## Compilación de SCSS (opcional)

El proyecto sirve `src/public/assets/css/app.css` directamente. El archivo `app.scss` se mantiene como fuente editable. Si quieres regenerar el CSS:

```bash
# Requiere dart-sass (https://sass-lang.com/install)
sass src/public/assets/scss/app.scss:src/public/assets/css/app.css --style=compressed
```

---

## Salud del Sistema (Healthcheck)

El contenedor expone el índice HTTP. Un check manual:

```bash
curl -fsS http://localhost:8090/login | head -n 20
```

Para Postgres (ya que corre fuera del compose):

```bash
pg_isready -h localhost -p 5432 -U oficios_user
```

---

## Backup de la Base de Datos

```bash
pg_dump -U oficios_user -h localhost respaldo_oficios > backup_$(date +%Y%m%d_%H%M).sql
```

Restaurar:

```bash
psql -U oficios_user -h localhost -d respaldo_oficios < backup_YYYYMMDD_HHMM.sql
```

---

## Gestión del Sistema

```bash
docker compose logs -f app                 # Logs de PHP/Apache
docker compose down                        # Detener
docker compose down -v                     # Detener + eliminar volúmenes (no borra Postgres del host)
```

El código en `src/` se monta como volumen: los cambios son inmediatos sin rebuild.

---

## Decisiones de Arquitectura

1. **PHP puro sin framework**. Prioridad de simplicidad y mantenibilidad para el equipo técnico interno.
2. **Router simple con regex**. Suficiente para el volumen de rutas del sistema.
3. **PDO con prepared statements** en todos los queries. Sin concatenación de SQL.
4. **Folio de tesorería como columna generada en Postgres** (`GENERATED ALWAYS AS STORED`) con expresión `CASE` que NO trunca: LPAD a 4 dígitos solo cuando `numero_folio < 10000`.
5. **Reserva atómica del número de folio** vía `FolioService::generarNuevoFolio`, que toma `pg_advisory_xact_lock` dentro de la transacción antes del `SELECT MAX + INSERT`.
6. **Uploads fuera del webroot**. `/uploads` no es servido por Apache; los archivos se entregan por controlador con validación de acceso y path traversal.
7. **Nombres de archivo seguros en disco**. `uniqid + random_bytes`. El nombre original queda en BD.
8. **Sesiones seguras**. Cookie `HttpOnly`, `SameSite=Strict`, `session_regenerate_id` en login y limpieza completa en logout.
9. **CSRF en todos los formularios POST**. Token en sesión, verificado con `hash_equals`.
10. **Bitácora de auditoría**. Toda acción relevante queda registrada con usuario, IP y datos JSON.
11. **Rate-limit de login** en tabla `login_intentos` (5 fallos / 10 min por username o IP).
12. **Manejador global de errores** (`ErrorHandler`): en producción renderiza 500 amigable con un ID de incidente; el stack trace se escribe a `storage/logs/app.log` y la BD (`bitacora`).

---

## Supuestos Documentados (del análisis del Excel)

1. **Folio de tesorería**. Variantes encontradas (`TM/ECA/ST/YC/...`, `TM/STIyC/...`, `TM/SDTICS/...`) se estandarizan en `TM/ECA/STIyC/XXXX/YYYY`. Folios históricos con formato distinto se guardan en `folio_direccion` o en staging.
2. **Dependencias**. La columna “NOMBRE DIRECCIÓN” tiene ~100 variantes por errores tipográficos. El seed normaliza las más frecuentes; lo desconocido se resuelve contra “NO IDENTIFICADA / PENDIENTE DE REVISIÓN”.
3. **Estados**. Se mapean las cadenas del Excel a estados normalizados. Los nombres de personas (“TURNADO A MONICA”, etc.) se conservan en `realizo`.
4. **REALIZÓ / CAPTURÓ**. Se guardan históricamente como texto libre. Los nuevos oficios usan `usuarios`.
5. **Unnamed: 13**. Cuatro notas textuales del Excel se llevan a `raw_observaciones`.
6. **Fechas**. Las fechas del Excel son `datetime` sin anomalías graves.
7. **Sin duplicados**. El Excel no tiene filas 100% idénticas.
8. **Folio interno tesorería**. Valores mixtos (2 a 4 dígitos) se guardan como referencia en `folio_minutario`.

---

## Checklist de Funcionalidades

- [x] Login seguro con CSRF + rate-limit por usuario/IP.
- [x] Login por usuario o correo.
- [x] Logout con invalidación de cookie y destrucción de sesión.
- [x] Dashboard con estadísticas.
- [x] Alta de oficio con preview de folio en tiempo real.
- [x] Autonumeración atómica de folios (EXTERNO por año, INTERNO/CONOCIMIENTO global).
- [x] Listado de oficios con filtros y paginación.
- [x] Detalle, edición y movimientos con historial.
- [x] Subida y descarga de evidencias PDF con protección path traversal.
- [x] Visualización inline de PDF.
- [x] CRUD de usuarios con salvaguarda del último GOD activo.
- [x] Catálogos de dependencias, estados y tipos de evidencia.
- [x] Importación Excel vía staging + UI de estado.
- [x] Bitácora de auditoría.
- [x] Manejador global de errores con ID de incidente.
- [x] Configuración hardening `APP_DEBUG=false` forzado en producción.

---

## Soporte

Sistema de uso interno. Para soporte técnico, contactar al administrador del sistema.
