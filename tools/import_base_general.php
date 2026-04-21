<?php
/**
 * =============================================================================
 *  Importador: BASE GENERAL (oficios externos del Excel institucional)
 * =============================================================================
 *
 *  Lee el CSV UTF-8 corregido y carga los oficios en la tabla `oficios`.
 *
 *  Uso (desde la raiz del proyecto):
 *      php tools/import_base_general.php
 *      php tools/import_base_general.php --dry-run      (solo valida, no escribe)
 *      php tools/import_base_general.php --truncate     (borra oficios antes)
 *
 *  Reglas aplicadas:
 *    - Crea dependencias automaticamente si no existen (con clave generada).
 *    - Si numero_folio esta vacio => se inserta como PENDIENTE (NULL).
 *    - estado_nombre se mapea a estados_oficio.id.
 *    - tipo_oficio_id se fija a EXTERNO (estos son oficios recibidos de otras dependencias).
 *    - usuario_capturo_id = 1 (primer GOD; ajustar si es necesario).
 *    - Transaccional: si algo falla, rollback total.
 * =============================================================================
 */

declare(strict_types=1);

// --- Bootstrap minimo (carga solo config + DB; NO arranca Router ni sesion) ----
define('IMPORT_CLI', true);
$root = dirname(__DIR__);
require_once $root . '/src/config/config.php';
require_once $root . '/src/config/database.php';

// --- Flags ----------------------------------------------------------------
$dryRun   = in_array('--dry-run',  $argv ?? [], true);
$truncate = in_array('--truncate', $argv ?? [], true);

$csvPath = $root . '/tools/analisis_base/BASE_GENERAL_corregida.csv';
if (!is_file($csvPath)) {
    fwrite(STDERR, "ERROR: no se encontro $csvPath\n");
    fwrite(STDERR, "Genera primero el CSV corregido con:\n");
    fwrite(STDERR, "    powershell -File tools/analisis_base/corregir_base.ps1\n");
    exit(1);
}

echo "========================================================\n";
echo " IMPORTADOR BASE GENERAL\n";
echo "========================================================\n";
echo " CSV origen : $csvPath\n";
echo " Modo       : " . ($dryRun ? 'DRY-RUN (sin escribir)' : 'ESCRITURA') . "\n";
echo " Truncate   : " . ($truncate ? 'SI (se borran los oficios existentes)' : 'NO') . "\n";
echo "========================================================\n\n";

$pdo = Database::pdo();

// --- Cargar catalogos ------------------------------------------------------
$estadosMap = [];
foreach ($pdo->query("SELECT id, nombre FROM estados_oficio") as $r) {
    $estadosMap[strtoupper($r['nombre'])] = (int)$r['id'];
}
foreach (['Recibido', 'En Proceso', 'Turnado', 'Archivado', 'De Conocimiento'] as $req) {
    if (!isset($estadosMap[strtoupper($req)])) {
        fwrite(STDERR, "ERROR: no existe el estado '$req' en estados_oficio.\n");
        exit(2);
    }
}

$tipoExterno = (int)$pdo->query("SELECT id FROM tipos_oficio WHERE clave = 'EXTERNO'")->fetchColumn();
if (!$tipoExterno) {
    fwrite(STDERR, "ERROR: no existe tipo_oficio 'EXTERNO'.\n");
    exit(2);
}

$usuarioCapturo = (int)$pdo->query("SELECT id FROM usuarios WHERE activo = TRUE ORDER BY rol_id ASC, id ASC LIMIT 1")->fetchColumn();
if (!$usuarioCapturo) {
    fwrite(STDERR, "ERROR: no hay usuarios activos; crea un GOD primero.\n");
    exit(2);
}
echo "[info] usuario_capturo_id = $usuarioCapturo\n";
echo "[info] tipo_oficio EXTERNO id = $tipoExterno\n\n";

// --- Helpers ---------------------------------------------------------------
function normaliza_nombre(string $s): string {
    $s = mb_strtoupper(trim($s), 'UTF-8');
    $s = str_replace(['  ', '   '], ' ', $s);
    // Quita acentos para comparar
    $sin = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    return $sin !== false ? preg_replace('/\s+/', ' ', $sin) : $s;
}

function slug_clave(string $s, int $len = 8): string {
    $s = normaliza_nombre($s);
    $s = preg_replace('/[^A-Z0-9 ]/', '', $s);
    $parts = preg_split('/\s+/', $s, -1, PREG_SPLIT_NO_EMPTY);
    $iniciales = '';
    foreach ($parts as $p) {
        if (in_array($p, ['DE','DEL','LA','LOS','LAS','Y','EL','POR','A'], true)) continue;
        $iniciales .= substr($p, 0, 1);
        if (strlen($iniciales) >= $len) break;
    }
    return $iniciales !== '' ? $iniciales : substr(str_replace(' ','', $s), 0, $len);
}

// Cachea dependencias para no consultar repetido
$depCache = [];
foreach ($pdo->query("SELECT id, nombre, clave FROM dependencias") as $r) {
    $key = normaliza_nombre($r['nombre']);
    $depCache[$key] = (int)$r['id'];
}

function obtener_dependencia_id(PDO $pdo, array &$cache, string $nombre, bool $dryRun): ?int {
    $nombre = trim($nombre);
    if ($nombre === '') return null;
    $key = normaliza_nombre($nombre);
    if (isset($cache[$key])) return $cache[$key];

    // Fuzzy: busca por coincidencia parcial
    foreach ($cache as $k => $id) {
        if (str_contains($k, $key) || str_contains($key, $k)) {
            // Solo si la longitud es similar (evitar matches demasiado laxos)
            if (abs(strlen($k) - strlen($key)) < 10) {
                $cache[$key] = $id;
                return $id;
            }
        }
    }

    // No existe: se crea
    $clave = slug_clave($nombre);
    // Evitar colisiones de clave
    $sufijo = 0;
    $claveOriginal = $clave;
    while (true) {
        $st = $pdo->prepare("SELECT 1 FROM dependencias WHERE clave = :c");
        $st->execute([':c' => $clave]);
        if (!$st->fetchColumn()) break;
        $sufijo++;
        $clave = $claveOriginal . $sufijo;
    }

    if ($dryRun) {
        echo "  [dry] Crearia dependencia: '$nombre' (clave $clave)\n";
        $cache[$key] = -1; // placeholder
        return -1;
    }

    $stIns = $pdo->prepare("INSERT INTO dependencias (nombre, clave) VALUES (:n, :c) RETURNING id");
    $stIns->execute([':n' => $nombre, ':c' => $clave]);
    $newId = (int)$stIns->fetchColumn();
    $cache[$key] = $newId;
    echo "  [+] Nueva dependencia: '$nombre' (clave $clave, id $newId)\n";
    return $newId;
}

// --- Leer CSV --------------------------------------------------------------
$fh = fopen($csvPath, 'r');
if (!$fh) { fwrite(STDERR, "ERROR: no se puede abrir $csvPath\n"); exit(1); }

// BOM UTF-8
$bom = fread($fh, 3);
if ($bom !== "\xEF\xBB\xBF") { rewind($fh); }

$headers = fgetcsv($fh);
if (!$headers) { fwrite(STDERR, "ERROR: CSV vacio\n"); exit(1); }
$headers = array_map(fn($h) => trim($h), $headers);

$rows = [];
while (($r = fgetcsv($fh)) !== false) {
    if (count($r) < count($headers)) {
        $r = array_pad($r, count($headers), '');
    }
    $rows[] = array_combine($headers, $r);
}
fclose($fh);

echo "[info] Filas leidas del CSV: " . count($rows) . "\n\n";

// --- Procesar --------------------------------------------------------------
$pdo->beginTransaction();

try {
    if ($truncate && !$dryRun) {
        echo "[!] Borrando oficios existentes (CASCADE)...\n";
        $pdo->exec("DELETE FROM oficios");
    }

    $sqlIns = "INSERT INTO oficios (
                    numero_folio, anio_folio, folio_minutario, folio_direccion,
                    tipo_oficio_id, dependencia_id, estado_id,
                    asunto, observaciones,
                    fecha_recepcion, fecha_oficio_tics, fecha_acuse,
                    realizo, usuario_capturo_id
               ) VALUES (
                    :numero_folio, :anio_folio, :folio_minutario, :folio_direccion,
                    :tipo_oficio_id, :dependencia_id, :estado_id,
                    :asunto, :observaciones,
                    :fecha_recepcion, :fecha_oficio_tics, :fecha_acuse,
                    :realizo, :usuario_capturo_id
               ) RETURNING id";
    $stIns = $pdo->prepare($sqlIns);

    $okCount = 0; $errCount = 0; $errores = [];
    $observacionesOriginales = [];

    foreach ($rows as $i => $row) {
        $fila = $i + 2; // linea en CSV (header es 1)

        $asunto = trim($row['asunto'] ?? '');
        $fechaRec = trim($row['fecha_recepcion'] ?? '');
        if ($asunto === '' || $fechaRec === '') {
            $errores[] = "fila $fila: asunto o fecha_recepcion vacio";
            $errCount++;
            continue;
        }

        // Estado
        $estadoNombre = strtoupper(trim($row['estado_nombre'] ?? 'En Proceso'));
        $estadoId = $estadosMap[$estadoNombre] ?? $estadosMap['EN PROCESO'];

        // Dependencia (autocrear si no existe)
        $depId = obtener_dependencia_id($pdo, $depCache, $row['dependencia_nombre'] ?? '', $dryRun);

        // Numero / anio folio
        $numFolio = trim($row['numero_folio'] ?? '');
        $numFolio = $numFolio !== '' ? (int)$numFolio : null;
        $anioFolio = (int)($row['anio_folio'] ?? date('Y'));

        // Observaciones - concatenar capturo + folio_interno_tesoreria si existen
        $obsParts = [];
        if (!empty($row['observaciones'])) $obsParts[] = $row['observaciones'];
        if (!empty($row['capturo']))       $obsParts[] = 'Capturo: ' . $row['capturo'];
        if (!empty($row['folio_interno_tesoreria'])) $obsParts[] = 'Folio interno: ' . $row['folio_interno_tesoreria'];
        $observaciones = $obsParts ? implode(' | ', $obsParts) : null;

        $params = [
            ':numero_folio'       => $numFolio,
            ':anio_folio'         => $anioFolio,
            ':folio_minutario'    => $row['folio_minutario']    ?: null,
            ':folio_direccion'    => $row['folio_direccion']    ?: null,
            ':tipo_oficio_id'     => $tipoExterno,
            ':dependencia_id'     => $depId > 0 ? $depId : null,
            ':estado_id'          => $estadoId,
            ':asunto'             => $asunto,
            ':observaciones'      => $observaciones,
            ':fecha_recepcion'    => $fechaRec,
            ':fecha_oficio_tics'  => $row['fecha_oficio_tics']  ?: null,
            ':fecha_acuse'        => $row['fecha_acuse']        ?: null,
            ':realizo'            => $row['realizo']            ?: null,
            ':usuario_capturo_id' => $usuarioCapturo,
        ];

        if ($dryRun) {
            $okCount++;
            continue;
        }

        try {
            $stIns->execute($params);
            $okCount++;
        } catch (PDOException $e) {
            $errores[] = "fila $fila (CON. {$row['consecutivo']}): " . $e->getMessage();
            $errCount++;
        }
    }

    echo "\n========================================================\n";
    echo " RESULTADO\n";
    echo "========================================================\n";
    echo " Insertados: $okCount\n";
    echo " Errores   : $errCount\n";
    if ($errores) {
        echo "\n[errores]\n";
        foreach (array_slice($errores, 0, 30) as $e) echo "  - $e\n";
        if (count($errores) > 30) echo "  ... y " . (count($errores) - 30) . " mas.\n";
    }

    if ($dryRun) {
        echo "\n[dry-run] Rollback (no se escribio nada).\n";
        $pdo->rollBack();
    } elseif ($errCount > 0) {
        echo "\n[!] Hubo errores. Ejecuta con --dry-run para ver detalle antes de hacer commit real.\n";
        echo "    Se hace ROLLBACK por seguridad.\n";
        $pdo->rollBack();
    } else {
        $pdo->commit();
        echo "\n[OK] Commit realizado. $okCount oficios insertados.\n";
    }
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "FATAL: " . $e->getMessage() . "\n");
    exit(3);
}
