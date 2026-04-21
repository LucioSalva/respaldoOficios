# Despliegue en Laragon (Puerto 8080)

Guia paso a paso para poner en produccion "Respaldo de Oficios" dentro de Laragon.

---

## 1. Requisitos previos

| Componente     | Version minima         | Notas                                   |
|----------------|------------------------|-----------------------------------------|
| Laragon Full   | 6.x                    | Apache + PHP 8.2                        |
| PHP            | 8.2                    | Con extensiones: pdo_pgsql, pgsql, zip, openssl, mbstring, opcache |
| PostgreSQL     | 14 o superior          | Puede ser el de Laragon o standalone    |
| Navegador      | Chrome / Edge / Firefox|                                         |

---

## 2. Extraer el proyecto

1. Cerrar Laragon (tray icon -> Stop All).
2. Extraer el `.zip` dentro de:
   ```
   C:\laragon\www\respaldoOficios\
   ```
3. Verificar que la estructura sea:
   ```
   C:\laragon\www\respaldoOficios\
       src\
       sql\
       uploads\
       storage\
       deploy\
       .env.example
       index.php
       .htaccess
       README.md
   ```

---

## 3. Configurar PHP 8.2 en Laragon

1. Menu Laragon -> **PHP** -> **Version** -> seleccionar **8.2**.
2. Menu Laragon -> **PHP** -> **Extensions** -> activar:
   - `pdo_pgsql`
   - `pgsql`
   - `zip`
   - `openssl`
   - `mbstring`
   - `opcache`
3. Menu Laragon -> **PHP** -> **php.ini** -> confirmar / ajustar:
   ```ini
   upload_max_filesize = 12M
   post_max_size       = 13M
   max_execution_time  = 60
   memory_limit        = 128M
   session.cookie_httponly = 1
   session.cookie_samesite = Strict
   session.use_strict_mode = 1
   expose_php          = Off
   date.timezone       = America/Mexico_City
   ```

---

## 4. Configurar Apache en puerto 8080

1. Menu Laragon -> **Apache** -> **httpd.conf**.
2. Buscar la linea `Listen 80` y cambiarla a:
   ```
   Listen 8080
   ```
3. Buscar `ServerName localhost:80` y cambiarla a:
   ```
   ServerName localhost:8080
   ```
4. Guardar.
5. Copiar el VirtualHost preconfigurado:
   - Origen: `C:\laragon\www\respaldoOficios\deploy\respaldoOficios.conf`
   - Destino: `C:\laragon\etc\apache2\sites-enabled\auto.respaldoOficios.conf`
6. Editar el hosts de Windows (`C:\Windows\System32\drivers\etc\hosts`) agregando:
   ```
   127.0.0.1   respaldo-oficios.test
   ```

---

## 5. Crear la base de datos PostgreSQL

Abrir una terminal de PostgreSQL (`psql`) como superusuario:

```sql
CREATE DATABASE respaldooficios
    WITH ENCODING 'UTF8' LC_COLLATE='es_MX.UTF-8' LC_CTYPE='es_MX.UTF-8' TEMPLATE=template0;
```

Luego aplicar las migraciones en orden (desde la carpeta `sql\`):

```bash
psql -U postgres -d respaldooficios -f sql/01_schema.sql
psql -U postgres -d respaldooficios -f sql/02_seed.sql
psql -U postgres -d respaldooficios -f sql/03_import_excel.sql
psql -U postgres -d respaldooficios -f sql/04_internos.sql
psql -U postgres -d respaldooficios -f sql/05_personal.sql
psql -U postgres -d respaldooficios -f sql/06_vacaciones.sql
psql -U postgres -d respaldooficios -f sql/07_incidencias.sql
psql -U postgres -d respaldooficios -f sql/08_conocimiento.sql
psql -U postgres -d respaldooficios -f sql/09_fix_folio_y_trazabilidad.sql
psql -U postgres -d respaldooficios -f sql/10_fix_constraints_y_uniques.sql
psql -U postgres -d respaldooficios -f sql/11_vacaciones_fix_sindicalizados_2026.sql
psql -U postgres -d respaldooficios -f sql/12_folio_pendiente.sql
```

> Si la instalacion de Postgres **NO tiene** la coleccion `es_MX.UTF-8` (por ejemplo, Postgres nativo en Windows), ejecutar en su lugar:
> ```sql
> CREATE DATABASE respaldooficios WITH ENCODING 'UTF8';
> ```

---

## 6. Configurar variables de entorno `.env`

1. Copiar el archivo `deploy\.env.production.example` a la raiz del proyecto:
   ```
   C:\laragon\www\respaldoOficios\.env
   ```
2. Editar y ajustar:
   - `DB_PASS` -> tu contrasena real de Postgres.
   - `APP_SECRET` -> generar una cadena aleatoria de 64 caracteres minimo.
3. Guardar.

> NUNCA subir el `.env` a repositorios. Ya esta en `.gitignore`.

---

## 7. Permisos de carpetas

Asegurar que Apache pueda escribir en:
```
C:\laragon\www\respaldoOficios\uploads\
C:\laragon\www\respaldoOficios\storage\logs\
```
En Windows basta con que sean carpetas del usuario actual; Laragon las usa sin problema.

---

## 8. Arrancar Laragon y probar

1. Boton verde **Start All** en Laragon.
2. Abrir navegador:
   ```
   http://respaldo-oficios.test:8080
   ```
3. Login inicial (por defecto del seed `02_seed.sql`): revisar el usuario `god` creado.

Si algo falla:
- Revisar `C:\laragon\www\respaldoOficios\storage\logs\php_errors.log`
- Revisar logs Apache en `C:\laragon\etc\apache2\logs\respaldoOficios-error.log`
- Confirmar que Postgres acepta conexiones en `127.0.0.1:5432`.

---

## 9. Opcion alternativa - Acceso sin dominio .test

Si prefieres acceder por `http://localhost:8080/respaldoOficios/` (sin dominio .test):
1. No usar el VirtualHost (paso 4.5).
2. El `index.php` + `.htaccess` de la raiz redirigen al front controller `src/public/index.php`.
3. **Advertencia**: los assets (`/assets/css/app.css`, etc.) estan referenciados con ruta absoluta, por lo que la opcion del dominio `.test` es la recomendada. Si usas la opcion `localhost:8080/respaldoOficios/` tendras que editar las vistas `src/views/layouts/main.php` y `src/views/layouts/auth.php` para usar rutas relativas, o configurar `Alias /assets "C:/laragon/www/respaldoOficios/src/public/assets"` en Apache.

---

## 10. Actualizaciones posteriores

Para actualizar la aplicacion:
1. Detener Apache (Laragon -> Stop All).
2. Reemplazar la carpeta `src/` con la nueva version.
3. Aplicar nuevas migraciones SQL si las hubiera.
4. Reiniciar Apache.

El `.env`, `uploads/` y `storage/` se conservan entre actualizaciones.
