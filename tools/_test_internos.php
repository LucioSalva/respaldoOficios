<?php
/**
 * Smoke-test: simula inserción de un oficio INTERNO y valida la vista.
 * Uso: docker exec respaldo_oficios_app php /var/www/html/../tools/_test_internos.php
 * (o vía bind mount reubicado). Aquí lo invocamos relativo a /var/www/html.
 */
require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/config/database.php';

$pdo = Database::pdo();

$tipo_id   = (int)$pdo->query("SELECT id FROM tipos_oficio WHERE clave='INTERNO'")->fetchColumn();
$area_id   = (int)$pdo->query("SELECT id FROM areas_internas WHERE nombre ILIKE '%EGRESOS%' LIMIT 1")->fetchColumn();
$estado_id = (int)$pdo->query("SELECT id FROM estados_oficio WHERE nombre='Archivado' LIMIT 1")->fetchColumn();
echo "tipo_INTERNO=$tipo_id  area_EGRESOS=$area_id  estado=$estado_id\n";

$max_int = (int)$pdo->query("SELECT COALESCE(MAX(numero_folio),9999) FROM oficios WHERE tipo_oficio_id=$tipo_id")->fetchColumn();
if ($max_int < 10000) $max_int = 10000;
$consecutivo = $max_int + 1;

$stmt = $pdo->prepare(
    "INSERT INTO oficios
     (numero_folio, anio_folio, tipo_oficio_id, area_interna_id, estado_id,
      asunto, fecha_recepcion, folio_interno_texto, realizo, usuario_capturo_id)
     VALUES (:nf, 2026, :tid, :aid, :eid, :as, CURRENT_DATE, :fi, :rel, 3)
     RETURNING id, folio_tesoreria, folio_interno_texto"
);
$stmt->execute([
    ':nf'  => $consecutivo,
    ':tid' => $tipo_id,
    ':aid' => $area_id,
    ':eid' => $estado_id,
    ':as'  => 'PRUEBA oficio interno - test automatizado',
    ':fi'  => 'TM/STIYC/TEST/01/2026',
    ':rel' => 'QA',
]);
$r = $stmt->fetch();
echo "INSERT OK  id={$r['id']}  folio_tesoreria={$r['folio_tesoreria']}  folio_interno_texto={$r['folio_interno_texto']}\n";

$d = $pdo->query("SELECT tipo_oficio_clave, folio_display, area_interna_nombre
                    FROM v_oficios_completo WHERE id={$r['id']}")->fetch();
echo "v_oficios_completo  tipo={$d['tipo_oficio_clave']}  folio_display={$d['folio_display']}  area={$d['area_interna_nombre']}\n";

// CHECK constraint: intentar INTERNO sin area_interna_id debe fallar
try {
    $pdo->prepare(
        "INSERT INTO oficios (numero_folio, anio_folio, tipo_oficio_id, estado_id, asunto, fecha_recepcion, usuario_capturo_id)
         VALUES (99999, 2026, :tid, :eid, 'Debe fallar', CURRENT_DATE, 3)"
    )->execute([':tid' => $tipo_id, ':eid' => $estado_id]);
    echo "WARN: el CHECK NO bloqueó INTERNO sin area_interna_id\n";
} catch (PDOException $e) {
    echo "CHECK OK: INTERNO sin area_interna_id fue bloqueado correctamente.\n";
}

// Cleanup
$pdo->prepare("DELETE FROM oficios WHERE id=:id")->execute([':id' => $r['id']]);
echo "CLEANUP OK\n";
