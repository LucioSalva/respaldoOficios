<?php
/**
 * Importador FOLIOS INTERNO SUB - agrega sin truncate.
 * INTERNO requiere area_interna_id (no dependencia_id).
 * CSV esperado en /tmp/FOLIOS_INTERNO_SUB_corregida.csv
 */
declare(strict_types=1);

define('IMPORT_CLI', true);
require_once '/var/www/html/config/config.php';
require_once '/var/www/html/config/database.php';

$dryRun  = in_array('--dry-run',  $argv ?? [], true);
$csvPath = '/tmp/FOLIOS_INTERNO_SUB_corregida.csv';

if (!is_file($csvPath)) {
    fwrite(STDERR, "ERROR: no se encontro $csvPath\n");
    exit(1);
}

echo "========================================================\n";
echo " IMPORTADOR FOLIOS INTERNO SUB (APPEND, usa areas_internas)\n";
echo "========================================================\n";
echo " CSV  : $csvPath\n";
echo " Modo : " . ($dryRun ? 'DRY-RUN' : 'ESCRITURA') . "\n";
echo "========================================================\n\n";

$pdo = Database::pdo();

$estadosMap = [];
foreach ($pdo->query("SELECT id, nombre FROM estados_oficio") as $r) {
    $estadosMap[strtoupper($r['nombre'])] = (int)$r['id'];
}
foreach (['Recibido','En Proceso','Turnado','Archivado','De Conocimiento'] as $req) {
    if (!isset($estadosMap[strtoupper($req)])) {
        fwrite(STDERR, "ERROR: falta estado '$req'\n"); exit(2);
    }
}

$tipoInterno = (int)$pdo->query("SELECT id FROM tipos_oficio WHERE clave='INTERNO'")->fetchColumn();
$usuarioCapturo = (int)$pdo->query("SELECT id FROM usuarios WHERE activo=TRUE ORDER BY rol_id ASC, id ASC LIMIT 1")->fetchColumn();
if (!$tipoInterno || !$usuarioCapturo) {
    fwrite(STDERR, "ERROR: falta tipo_oficio INTERNO o usuario activo.\n"); exit(2);
}
echo "[info] usuario_capturo=$usuarioCapturo  tipo_interno=$tipoInterno\n\n";

function normaliza_nombre(string $s): string {
    $s = mb_strtoupper(trim($s), 'UTF-8');
    $sin = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
    return $sin !== false ? preg_replace('/\s+/',' ',$sin) : $s;
}

// Cache areas_internas por nombre normalizado
$areaCache = [];
foreach ($pdo->query("SELECT id, nombre FROM areas_internas WHERE activo=TRUE") as $r) {
    $areaCache[normaliza_nombre($r['nombre'])] = (int)$r['id'];
}

// Alias manual para nombres que no caen por fuzzy
$aliases = [
    'TESORERO MUNICIPAL'                     => 'TESORERIA MUNICIPAL',
    'SUBDIRECCION DE PROGRAMAS FEDERALES'    => 'SUBDIRECCION DE PROGRAMAS FEDERALES Y ESTATALES',
    'SUBDIRECCION DE PROGRAMAS FEDERALES Y ESTATLES' => 'SUBDIRECCION DE PROGRAMAS FEDERALES Y ESTATALES',
    'CORDINADOR DE LAS OFICIALIAS DE REGISTRO CIVIL DEL H. AYUNTAMIENTO' => 'COORDINACION DE OFICIALIAS DE REGISTRO CIVIL',
    'SUBDIRECCION DE JURIDICO DE LA TESORERIA' => 'SUBDIRECCION DE JURIDICO DE TESORERIA',
];

function obtener_area_id(array $cache, array $aliases, string $nombre): ?array {
    $nombre = trim($nombre);
    if ($nombre === '') return null;
    $key = normaliza_nombre($nombre);
    if (isset($cache[$key])) return ['id' => $cache[$key], 'match' => 'exacto'];
    if (isset($aliases[$key])) {
        $aliasKey = normaliza_nombre($aliases[$key]);
        if (isset($cache[$aliasKey])) return ['id' => $cache[$aliasKey], 'match' => 'alias'];
    }
    foreach ($cache as $k=>$id) {
        if ((str_contains($k,$key) || str_contains($key,$k)) && abs(strlen($k)-strlen($key))<15) {
            return ['id' => $id, 'match' => 'fuzzy'];
        }
    }
    return null;
}

$fh = fopen($csvPath,'r');
$bom = fread($fh,3);
if ($bom !== "\xEF\xBB\xBF") rewind($fh);
$headers = fgetcsv($fh);
$headers = array_map('trim', $headers);
$rows = [];
while (($r=fgetcsv($fh))!==false) {
    if (count($r)<count($headers)) $r = array_pad($r,count($headers),'');
    $rows[] = array_combine($headers,$r);
}
fclose($fh);
echo "[info] filas leidas: ".count($rows)."\n\n";

// Pre-check: todas las dependencia_nombre deben mapear a area_interna_id
$sinMatch = [];
foreach ($rows as $i=>$row) {
    $dep = trim($row['dependencia_nombre']??'');
    if ($dep === '') continue;
    $m = obtener_area_id($areaCache, $aliases, $dep);
    if (!$m) $sinMatch[$dep] = ($sinMatch[$dep]??0)+1;
}
if ($sinMatch) {
    echo "[ERROR] Hay nombres que no mapean a areas_internas:\n";
    foreach ($sinMatch as $n=>$c) echo "  - $n (x$c)\n";
    exit(4);
}

$pdo->beginTransaction();
try {
    $sql = "INSERT INTO oficios (numero_folio,anio_folio,folio_minutario,folio_direccion,tipo_oficio_id,area_interna_id,estado_id,asunto,observaciones,fecha_recepcion,fecha_oficio_tics,fecha_acuse,realizo,usuario_capturo_id) VALUES (:numero_folio,:anio_folio,:folio_minutario,:folio_direccion,:tipo_oficio_id,:area_interna_id,:estado_id,:asunto,:observaciones,:fecha_recepcion,:fecha_oficio_tics,:fecha_acuse,:realizo,:usuario_capturo_id) RETURNING id";
    $st = $pdo->prepare($sql);
    $ok=0; $err=0; $skip=0; $errores=[]; $skips=[]; $matchStats=['exacto'=>0,'alias'=>0,'fuzzy'=>0];
    foreach ($rows as $i=>$row) {
        $linea=$i+2;
        $asunto=trim($row['asunto']??'');
        $fechaRec=trim($row['fecha_recepcion']??'');
        if ($asunto===''||$fechaRec==='') { $skips[]="linea $linea: asunto/fecha_recepcion vacio"; $skip++; continue; }
        $estadoNom=strtoupper(trim($row['estado_nombre']??'EN PROCESO'));
        $estadoId=$estadosMap[$estadoNom]??$estadosMap['EN PROCESO'];
        $m = obtener_area_id($areaCache, $aliases, $row['dependencia_nombre']??'');
        $areaId = $m ? $m['id'] : null;
        if ($m) $matchStats[$m['match']]++;
        $numFolio=trim($row['numero_folio']??'');
        $numFolio=$numFolio!==''?(int)$numFolio:null;
        $anioFolio=(int)($row['anio_folio']??date('Y'));
        $obsP=[];
        if (!empty($row['observaciones'])) $obsP[]=$row['observaciones'];
        if (!empty($row['capturo']))       $obsP[]='Capturo: '.$row['capturo'];
        $obs=$obsP?implode(' | ',$obsP):null;
        $p=[
            ':numero_folio'=>$numFolio,
            ':anio_folio'=>$anioFolio,
            ':folio_minutario'=>$row['folio_minutario']?:null,
            ':folio_direccion'=>$row['folio_direccion']?:null,
            ':tipo_oficio_id'=>$tipoInterno,
            ':area_interna_id'=>$areaId,
            ':estado_id'=>$estadoId,
            ':asunto'=>$asunto,
            ':observaciones'=>$obs,
            ':fecha_recepcion'=>$fechaRec,
            ':fecha_oficio_tics'=>$row['fecha_oficio_tics']?:null,
            ':fecha_acuse'=>$row['fecha_acuse']?:null,
            ':realizo'=>$row['realizo']?:null,
            ':usuario_capturo_id'=>$usuarioCapturo,
        ];
        if ($dryRun) { $ok++; continue; }
        $sp = 'sp_row_'.$linea;
        $pdo->exec("SAVEPOINT $sp");
        try {
            $st->execute($p);
            $pdo->exec("RELEASE SAVEPOINT $sp");
            $ok++;
        } catch (PDOException $e) {
            $pdo->exec("ROLLBACK TO SAVEPOINT $sp");
            $errores[]="linea $linea (CON.{$row['consecutivo']}): ".$e->getMessage();
            $err++;
        }
    }
    echo "\n========================================================\n";
    echo " Insertados: $ok\n";
    echo " Omitidos  : $skip\n";
    echo " Errores   : $err\n";
    echo " Match areas: exacto={$matchStats['exacto']} alias={$matchStats['alias']} fuzzy={$matchStats['fuzzy']}\n";
    if ($skips)    { echo "\n[omitidos]\n"; foreach(array_slice($skips,0,20) as $e) echo "  - $e\n"; }
    if ($errores) { echo "\n[errores]\n"; foreach(array_slice($errores,0,40) as $e) echo "  - $e\n"; }
    if ($dryRun) { $pdo->rollBack(); echo "\n[dry-run] rollback.\n"; }
    else { $pdo->commit(); echo "\n[OK] COMMIT. $ok oficios INTERNO agregados.\n"; }
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR,"FATAL: ".$e->getMessage()."\n"); exit(3);
}
