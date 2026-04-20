<?php
/**
 * test_folio_unique.php
 *
 * READ-ONLY. Verifica sobre la BD real que:
 *   - No existen folios_tesoreria duplicados en oficios.
 *   - Existe el UNIQUE INDEX uq_oficios_folio_tesoreria (creado en
 *     sql/09_fix_folio_y_trazabilidad.sql).
 *
 * Salida 0 = OK. Salida 1 = violación detectada.
 */

declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$name = getenv('DB_NAME') ?: 'respaldo_oficios';
$user = getenv('DB_USER') ?: 'oficios_user';
$pass = getenv('DB_PASS') ?: '';

$dsn = "pgsql:host=$host;port=$port;dbname=$name";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "No se pudo conectar a la BD: " . $e->getMessage() . "\n");
    exit(1);
}

// 1) Comprobar que no hay duplicados
$dupes = $pdo->query(
    "SELECT folio_tesoreria, COUNT(*) AS n
       FROM oficios
      WHERE folio_tesoreria IS NOT NULL
      GROUP BY folio_tesoreria
     HAVING COUNT(*) > 1
      ORDER BY n DESC, folio_tesoreria"
)->fetchAll(PDO::FETCH_ASSOC);

if ($dupes) {
    echo "FAIL — se encontraron folios_tesoreria duplicados:\n";
    foreach ($dupes as $d) {
        echo sprintf("  %s  (%d ocurrencias)\n", $d['folio_tesoreria'], (int)$d['n']);
    }
    exit(1);
}
echo "ok   sin duplicados de folio_tesoreria\n";

// 2) Comprobar índice UNIQUE
$idx = $pdo->query(
    "SELECT indexname FROM pg_indexes
      WHERE schemaname = 'public'
        AND tablename  = 'oficios'
        AND indexname  = 'uq_oficios_folio_tesoreria'"
)->fetchColumn();

if (!$idx) {
    echo "FAIL — no existe el índice UNIQUE uq_oficios_folio_tesoreria.\n";
    echo "      Aplica sql/09_fix_folio_y_trazabilidad.sql.\n";
    exit(1);
}
echo "ok   índice UNIQUE presente: $idx\n";

// 3) Invariante EXTERNO => dependencia_id NOT NULL
$row = $pdo->query(
    "SELECT COUNT(*) FROM oficios o
       JOIN tipos_oficio t ON t.id = o.tipo_oficio_id
      WHERE t.clave = 'EXTERNO' AND o.dependencia_id IS NULL"
)->fetchColumn();
if ((int)$row !== 0) {
    echo "FAIL — $row oficios EXTERNOS sin dependencia_id (C4).\n";
    exit(1);
}
echo "ok   todos los EXTERNOS tienen dependencia_id\n";

// 4) Invariante INTERNO => al menos un movimiento inicial
$row = $pdo->query(
    "SELECT COUNT(*) FROM oficios o
       JOIN tipos_oficio t ON t.id = o.tipo_oficio_id
      WHERE t.clave = 'INTERNO'
        AND NOT EXISTS (SELECT 1 FROM movimientos_oficio m WHERE m.oficio_id = o.id)"
)->fetchColumn();
if ((int)$row !== 0) {
    echo "FAIL — $row oficios INTERNOS sin movimiento inicial (C4).\n";
    exit(1);
}
echo "ok   todos los INTERNOS tienen movimiento inicial\n";

echo "\nOK — invariantes de folio y trazabilidad verificadas.\n";
exit(0);
