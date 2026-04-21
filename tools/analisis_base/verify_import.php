<?php
declare(strict_types=1);
define('IMPORT_CLI', true);
require_once '/var/www/html/config/config.php';
require_once '/var/www/html/config/database.php';

$pdo = Database::pdo();

echo "=== VERIFICACION POST-IMPORT ===\n\n";

$total = (int)$pdo->query("SELECT COUNT(*) FROM oficios")->fetchColumn();
echo "Total oficios en DB     : $total\n";

$pend = (int)$pdo->query("SELECT COUNT(*) FROM oficios WHERE numero_folio IS NULL")->fetchColumn();
echo "Con numero_folio NULL   : $pend (pendientes / folio roto)\n";

$conFolio = (int)$pdo->query("SELECT COUNT(*) FROM oficios WHERE numero_folio IS NOT NULL")->fetchColumn();
echo "Con numero_folio        : $conFolio\n\n";

echo "=== DISTRIBUCION POR ESTADO ===\n";
$q = $pdo->query("SELECT e.nombre, COUNT(*) c FROM oficios o JOIN estados_oficio e ON e.id=o.estado_id GROUP BY e.nombre ORDER BY c DESC");
foreach ($q as $r) echo "  " . str_pad($r['nombre'], 20) . " : " . $r['c'] . "\n";

echo "\n=== TOP 10 DEPENDENCIAS ===\n";
$q = $pdo->query("SELECT d.nombre, COUNT(*) c FROM oficios o JOIN dependencias d ON d.id=o.dependencia_id GROUP BY d.nombre ORDER BY c DESC LIMIT 10");
foreach ($q as $r) echo "  " . str_pad(substr($r['nombre'],0,40), 42) . ": " . $r['c'] . "\n";

echo "\n=== DEPENDENCIAS TOTALES ===\n";
$d = (int)$pdo->query("SELECT COUNT(*) FROM dependencias")->fetchColumn();
echo "Total dependencias: $d\n";

echo "\n=== 5 MUESTRAS ===\n";
$q = $pdo->query("SELECT id, numero_folio, anio_folio, SUBSTRING(asunto FROM 1 FOR 50) AS asunto, fecha_recepcion FROM oficios ORDER BY id DESC LIMIT 5");
foreach ($q as $r) {
    $f = $r['numero_folio'] ? ($r['numero_folio']."/".$r['anio_folio']) : "(pendiente)";
    echo "  #{$r['id']}  $f  {$r['fecha_recepcion']}  {$r['asunto']}\n";
}

echo "\n=== VERIFICACION OK ===\n";
