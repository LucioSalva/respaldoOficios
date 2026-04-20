# Tests — Respaldo de Oficios

Scripts CLI de verificación (PHP y SQL). No usan PHPUnit ni dependencias
externas; basta con el intérprete PHP 8.2 del contenedor o del host.

## Cómo ejecutar

Desde la raíz del proyecto (Windows PowerShell o bash), con la BD accesible
con las credenciales del `.env`:

```bash
# PHP puro (sin BD): prueba la función pura de formato de folio
php tests/test_folio_formatear.php

# Con BD: prueba de UNIQUE sobre folio_tesoreria (read-only, sólo SELECT)
php tests/test_folio_unique.php

# Con BD: prueba de salvaguarda "último GOD activo" (read-only)
php tests/test_ultimo_god.php

# Con BD: prueba de configuración CSRF (construye un token y verifica hash_equals)
php tests/test_csrf_token.php
```

Todos los tests imprimen `OK` al final si pasan, o abortan con exit code 1
y mensaje descriptivo si fallan.
