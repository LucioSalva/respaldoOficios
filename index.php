<?php
/**
 * Front-controller fallback cuando el proyecto NO se sirve desde src/public
 * directamente (por ejemplo, acceso http://localhost:8080/respaldoOficios/).
 *
 * Para uso optimo en produccion se recomienda configurar un VirtualHost
 * con DocumentRoot apuntando a src/public/ (ver deploy/respaldoOficios.conf).
 */

require __DIR__ . '/src/public/index.php';
