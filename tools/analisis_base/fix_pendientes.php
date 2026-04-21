<?php
/**
 * Inserta los registros que quedaron fuera:
 * - CON.4 de OFICIOS DE CONOCIMIENTO (sin asunto)
 * - 4 duplicados EXTERNO (folios 362, 548, 790, 2379 en 2026)
 * - 2 duplicados INTERNO (folios 7, 8 en 2026)
 * Con numero_folio NULL y el folio original en observaciones.
 */
declare(strict_types=1);
define('IMPORT_CLI', true);
require_once '/var/www/html/config/config.php';
require_once '/var/www/html/config/database.php';

$pdo = Database::pdo();

$estados = [];
foreach ($pdo->query("SELECT id,nombre FROM estados_oficio") as $r) $estados[strtoupper($r['nombre'])] = (int)$r['id'];
$tipos = [];
foreach ($pdo->query("SELECT id,clave FROM tipos_oficio") as $r) $tipos[$r['clave']] = (int)$r['id'];
$usuario = (int)$pdo->query("SELECT id FROM usuarios WHERE activo=TRUE ORDER BY rol_id ASC, id ASC LIMIT 1")->fetchColumn();

function normaliza_nombre(string $s): string {
    $s = mb_strtoupper(trim($s), 'UTF-8');
    $sin = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
    return $sin !== false ? preg_replace('/\s+/',' ',$sin) : $s;
}

function find_dep(PDO $pdo, string $nombre): ?int {
    $nombre = trim($nombre);
    if ($nombre === '') return null;
    $key = normaliza_nombre($nombre);
    foreach ($pdo->query("SELECT id,nombre FROM dependencias") as $r) {
        $k = normaliza_nombre($r['nombre']);
        if ($k === $key) return (int)$r['id'];
        if ((str_contains($k,$key) || str_contains($key,$k)) && abs(strlen($k)-strlen($key))<10) return (int)$r['id'];
    }
    return null;
}

function find_area(PDO $pdo, string $nombre, array $aliases): ?int {
    $nombre = trim($nombre);
    if ($nombre === '') return null;
    $key = normaliza_nombre($nombre);
    $cache = [];
    foreach ($pdo->query("SELECT id,nombre FROM areas_internas WHERE activo=TRUE") as $r) {
        $cache[normaliza_nombre($r['nombre'])] = (int)$r['id'];
    }
    if (isset($cache[$key])) return $cache[$key];
    if (isset($aliases[$key])) {
        $a = normaliza_nombre($aliases[$key]);
        if (isset($cache[$a])) return $cache[$a];
    }
    foreach ($cache as $k=>$id) {
        if ((str_contains($k,$key) || str_contains($key,$k)) && abs(strlen($k)-strlen($key))<15) return $id;
    }
    return null;
}

$areaAliases = [
    'TESORERO MUNICIPAL' => 'TESORERIA MUNICIPAL',
    'SUBDIRECCION DE PROGRAMAS FEDERALES' => 'SUBDIRECCION DE PROGRAMAS FEDERALES Y ESTATALES',
    'SUBDIRECCION DE PROGRAMAS FEDERALES Y ESTATLES' => 'SUBDIRECCION DE PROGRAMAS FEDERALES Y ESTATALES',
    'CORDINADOR DE LAS OFICIALIAS DE REGISTRO CIVIL DEL H. AYUNTAMIENTO' => 'COORDINACION DE OFICIALIAS DE REGISTRO CIVIL',
    'SUBDIRECCION DE JURIDICO DE LA TESORERIA' => 'SUBDIRECCION DE JURIDICO DE TESORERIA',
];

function read_csv(string $path): array {
    $fh = fopen($path,'r');
    $bom = fread($fh,3);
    if ($bom !== "\xEF\xBB\xBF") rewind($fh);
    $headers = array_map('trim', fgetcsv($fh));
    $rows = [];
    while (($r=fgetcsv($fh))!==false) {
        if (count($r)<count($headers)) $r = array_pad($r,count($headers),'');
        $rows[] = array_combine($headers,$r);
    }
    fclose($fh);
    return $rows;
}

$pdo->beginTransaction();
try {
    // ================== 1) CON.4 de CONOCIMIENTO ==================
    $cono = read_csv('/tmp/CONOCIMIENTO_corregida.csv');
    $con4 = null;
    foreach ($cono as $r) if ((int)$r['consecutivo'] === 4) { $con4 = $r; break; }
    if (!$con4) throw new Exception("No encontre CON.4 en CONOCIMIENTO");
    $depId = find_dep($pdo, $con4['dependencia_nombre']);
    if (!$depId) {
        // crear
        $clave = 'CCCYC4';
        $st=$pdo->prepare("INSERT INTO dependencias(nombre,clave) VALUES(:n,:c) RETURNING id");
        $st->execute([':n'=>$con4['dependencia_nombre'], ':c'=>$clave]);
        $depId=(int)$st->fetchColumn();
        echo "  [+] dep creada: {$con4['dependencia_nombre']} id=$depId\n";
    }
    $sql = "INSERT INTO oficios (numero_folio,anio_folio,folio_direccion,tipo_oficio_id,dependencia_id,estado_id,asunto,observaciones,fecha_recepcion,fecha_acuse,usuario_capturo_id) VALUES (NULL,:anio,:fdir,:tipo,:dep,:est,:asu,:obs,:frec,:facu,:usr) RETURNING id";
    $st=$pdo->prepare($sql);
    $st->execute([
        ':anio'=>2026, ':fdir'=>$con4['folio_direccion']?:null,
        ':tipo'=>$tipos['CONOCIMIENTO'], ':dep'=>$depId,
        ':est'=>$estados['ARCHIVADO'], ':asu'=>'(Sin asunto)',
        ':obs'=>'CON.4 OFICIOS DE CONOCIMIENTO - asunto no registrado en fuente',
        ':frec'=>$con4['fecha_recepcion']?:null, ':facu'=>$con4['fecha_acuse']?:null,
        ':usr'=>$usuario,
    ]);
    $id=(int)$st->fetchColumn();
    echo "[OK] CON.4 CONOCIMIENTO insertado (id=$id)\n";

    // ================== 2) 4 duplicados EXTERNO ==================
    $ext = read_csv('/tmp/BASE_GENERAL_corregida.csv');
    $dupsExt = [
        ['con'=>22,  'folio_raw'=>'TM/STIyC/0362/2026'],
        ['con'=>36,  'folio_raw'=>'TM/STIyC/0548/2026'],
        ['con'=>38,  'folio_raw'=>'TM/STIyC/0790/2026'],
        ['con'=>155, 'folio_raw'=>'TM/STIyC/2379/2026'],
    ];
    $sqlExt = "INSERT INTO oficios (numero_folio,anio_folio,folio_minutario,folio_direccion,tipo_oficio_id,dependencia_id,estado_id,asunto,observaciones,fecha_recepcion,fecha_oficio_tics,fecha_acuse,realizo,usuario_capturo_id) VALUES (NULL,:anio,:fmin,:fdir,:tipo,:dep,:est,:asu,:obs,:frec,:ftic,:facu,:real,:usr) RETURNING id";
    $stExt = $pdo->prepare($sqlExt);
    foreach ($dupsExt as $d) {
        $row = null;
        foreach ($ext as $r) if ((int)$r['consecutivo'] === $d['con']) { $row = $r; break; }
        if (!$row) { echo "  [SKIP] no encontre CON.{$d['con']} en BASE_GENERAL\n"; continue; }
        $depId = find_dep($pdo, $row['dependencia_nombre']);
        $estadoNom = strtoupper(trim($row['estado_nombre']??'EN PROCESO'));
        $estadoId = $estados[$estadoNom] ?? $estados['EN PROCESO'];
        $obsP = [];
        $obsP[] = "Folio duplicado (sin ECA): {$d['folio_raw']}";
        if (!empty($row['observaciones'])) $obsP[] = $row['observaciones'];
        if (!empty($row['capturo']))       $obsP[] = 'Capturo: '.$row['capturo'];
        if (!empty($row['folio_interno_tesoreria'])) $obsP[] = 'Folio interno: '.$row['folio_interno_tesoreria'];
        $stExt->execute([
            ':anio'=>(int)$row['anio_folio'],
            ':fmin'=>$row['folio_minutario']?:null,
            ':fdir'=>$row['folio_direccion']?:null,
            ':tipo'=>$tipos['EXTERNO'], ':dep'=>$depId,
            ':est'=>$estadoId, ':asu'=>$row['asunto']?:'(Sin asunto)',
            ':obs'=>implode(' | ',$obsP),
            ':frec'=>$row['fecha_recepcion']?:null,
            ':ftic'=>$row['fecha_oficio_tics']?:null,
            ':facu'=>$row['fecha_acuse']?:null,
            ':real'=>$row['realizo']?:null, ':usr'=>$usuario,
        ]);
        $id=(int)$stExt->fetchColumn();
        echo "[OK] EXTERNO duplicado {$d['folio_raw']} CON.{$d['con']} insertado (id=$id)\n";
    }

    // ================== 3) 2 duplicados INTERNO ==================
    $inte = read_csv('/tmp/FOLIOS_INTERNO_SUB_corregida.csv');
    $dupsInt = [
        ['con'=>12, 'folio_raw'=>'TM/STIyC/0007/2026'],
        ['con'=>13, 'folio_raw'=>'TM/STIyC/0008/2026'],
    ];
    $sqlInt = "INSERT INTO oficios (numero_folio,anio_folio,folio_minutario,folio_direccion,tipo_oficio_id,area_interna_id,estado_id,asunto,observaciones,fecha_recepcion,fecha_oficio_tics,fecha_acuse,realizo,usuario_capturo_id) VALUES (NULL,:anio,:fmin,:fdir,:tipo,:area,:est,:asu,:obs,:frec,:ftic,:facu,:real,:usr) RETURNING id";
    $stInt = $pdo->prepare($sqlInt);
    foreach ($dupsInt as $d) {
        $row = null;
        foreach ($inte as $r) if ((int)$r['consecutivo'] === $d['con']) { $row = $r; break; }
        if (!$row) { echo "  [SKIP] no encontre CON.{$d['con']} en INTERNO\n"; continue; }
        $areaId = find_area($pdo, $row['dependencia_nombre'], $areaAliases);
        $estadoNom = strtoupper(trim($row['estado_nombre']??'EN PROCESO'));
        $estadoId = $estados[$estadoNom] ?? $estados['EN PROCESO'];
        $obsP = [];
        $obsP[] = "Folio duplicado (sin ECA): {$d['folio_raw']}";
        if (!empty($row['observaciones'])) $obsP[] = $row['observaciones'];
        if (!empty($row['capturo']))       $obsP[] = 'Capturo: '.$row['capturo'];
        $stInt->execute([
            ':anio'=>(int)$row['anio_folio'],
            ':fmin'=>$row['folio_minutario']?:null,
            ':fdir'=>$row['folio_direccion']?:null,
            ':tipo'=>$tipos['INTERNO'], ':area'=>$areaId,
            ':est'=>$estadoId, ':asu'=>$row['asunto']?:'(Sin asunto)',
            ':obs'=>implode(' | ',$obsP),
            ':frec'=>$row['fecha_recepcion']?:null,
            ':ftic'=>$row['fecha_oficio_tics']?:null,
            ':facu'=>$row['fecha_acuse']?:null,
            ':real'=>$row['realizo']?:null, ':usr'=>$usuario,
        ]);
        $id=(int)$stInt->fetchColumn();
        echo "[OK] INTERNO duplicado {$d['folio_raw']} CON.{$d['con']} insertado (id=$id)\n";
    }

    $pdo->commit();
    echo "\n[COMMIT] OK - todos los pendientes recuperados.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "FATAL: ".$e->getMessage()."\n"); exit(3);
}
