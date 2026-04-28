# Despliegue en Laragon - Respaldo de Oficios (Puerto 8080)

Guia operacional corta para el servidor Windows + Laragon donde corre la aplicacion.

---

## URL correcta

- http://oficios.test:8080
- http://187.174.221.162:8080

## URLs que NO se deben usar

- http://localhost:8080/oficios/        (rompe rutas absolutas)
- http://187.174.221.162:8080/oficios/  (rompe rutas absolutas)
- Cualquier URL que termine en `oficios.index.php` o que apunte a `localhost:8090`.

---

## Estructura de despliegue

```
C:/laragon/www/oficios/
    src/public/        <-- DocumentRoot del VHost
        index.php
        .htaccess
        assets/
    src/...
    sql/
    storage/logs/
    uploads/           <-- creado por el script
    deploy/
        auto.oficios.conf
        setup-laragon-oficios.ps1
    .env               <-- generado/actualizado por el script
```

DocumentRoot oficial: `C:/laragon/www/oficios/src/public`.

---

## Despliegue rapido (recomendado)

1. Copiar la carpeta del proyecto a `C:/laragon/www/oficios/`.
2. Abrir PowerShell como **Administrador**.
3. Ejecutar:
   ```powershell
   cd C:/laragon/www/oficios/deploy
   powershell -ExecutionPolicy Bypass -File .\setup-laragon-oficios.ps1
   ```
4. Activar en Laragon: menu Apache -> Modules -> `rewrite_module` y `headers_module`.
5. Agregar al archivo `C:/Windows/System32/drivers/etc/hosts` (como administrador):
   ```
   127.0.0.1   oficios.test
   ```
6. Reiniciar Apache: Laragon -> Stop All -> Start All.
7. Probar:
   - http://oficios.test:8080
   - http://187.174.221.162:8080

---

## Como reiniciar Apache

- Tray icon de Laragon -> **Stop All** -> esperar 2 seg -> **Start All**.
- O bien el boton verde **Reload** en la ventana principal.

---

## Donde estan los logs

Laragon puede tener dos ubicaciones, segun version:

- `C:/laragon/etc/apache2/logs/oficios-error.log`
- `C:/laragon/etc/apache2/logs/oficios-access.log`

Y en algunas instalaciones:

- `C:/laragon/logs/apache_error.log`

Logs PHP propios de la app:

- `C:/laragon/www/oficios/storage/logs/php_errors.log`

---

## Probar conexion a PostgreSQL

Desde PowerShell:

```powershell
psql -h 127.0.0.1 -U postgres -d respaldooficios -c "SELECT COUNT(*) FROM oficios;"
```

Debe regresar la cantidad de oficios cargados (en este servidor: 238).

Si pide password y no lo aceptas, revisa `C:/laragon/www/oficios/.env` -> `DB_PASS` y la config de Postgres (`pg_hba.conf`).

---

## Validar uploads

```powershell
Test-Path C:/laragon/www/oficios/uploads
```

Debe imprimir `True`. Si no existe, el script lo crea. Apache (proceso `httpd.exe`) debe poder escribir en esa ruta. En Laragon, normalmente lo permite por default.

Probar adjuntando una evidencia desde la UI -> ver `C:/laragon/www/oficios/uploads/<oficio_id>/`.

---

## VirtualHost instalado

Archivo: `C:/laragon/etc/apache2/sites-enabled/auto.oficios.conf`

Si quieres editarlo manualmente, usa la plantilla de `deploy/auto.oficios.conf`.

---

## Troubleshooting comun

| Sintoma                                         | Causa probable                                | Solucion                                                   |
|-------------------------------------------------|-----------------------------------------------|------------------------------------------------------------|
| 404 al entrar por `oficios.test:8080`           | hosts no editado o Apache no recargo          | Revisar hosts, reiniciar Apache.                           |
| 500 al cargar cualquier pagina                  | `.env` sin DB_PASS o Postgres caido           | Revisar `storage/logs/php_errors.log`.                     |
| CSS/JS rotos                                    | DocumentRoot mal apuntado                     | El VHost debe apuntar a `src/public`.                      |
| Listen 8080 no funciona                         | Puerto ocupado por otro servicio              | Liberar puerto 8080 o cambiar mapeo (no recomendado).      |
| Aparece "Forbidden" en `/sql` o `/storage`      | Es lo esperado por seguridad.                 | (No es un error.)                                          |

---

## Re-ejecutar el script

`setup-laragon-oficios.ps1` es **idempotente**: puedes correrlo varias veces.

- Si `.env` ya existe, conserva `APP_SECRET` y `DB_PASS`.
- Si el VHost ya existe, lo respalda con timestamp y lo sobrescribe.
- Si `Listen 8080` ya esta en `httpd.conf`, no agrega duplicados.

Cada modificacion deja un archivo `*.bak.YYYYMMDD-HHMMSS` al lado del original.
