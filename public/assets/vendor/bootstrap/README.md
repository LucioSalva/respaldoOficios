# Bootstrap 5.3.3 (vendor local)

Carpeta destinada a hospedar Bootstrap 5.3.3 servido localmente, en lugar
del CDN. Mientras los archivos no esten presentes, los layouts siguen
usando el CDN configurado en `app/views/layouts/main.php` y `app/views/layouts/auth.php`.

## Como migrar a local

1. Descargar manualmente: https://github.com/twbs/bootstrap/releases/tag/v5.3.3
2. Extraer y copiar:
   - `dist/css/bootstrap.min.css` -> `public/assets/vendor/bootstrap/css/bootstrap.min.css`
   - `dist/js/bootstrap.bundle.min.js` -> `public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js`
3. Editar `app/views/layouts/main.php` y `app/views/layouts/auth.php`:
   - Cambiar el `<link>` del CDN por:
     `<link rel="stylesheet" href="<?= asset_url('vendor/bootstrap/css/bootstrap.min.css') ?>">`
   - Cambiar el `<script>` del CDN por:
     `<script src="<?= asset_url('vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>`

No se descarga automaticamente desde el repo: la institucion no permite
trafico saliente desde el servidor de produccion.
