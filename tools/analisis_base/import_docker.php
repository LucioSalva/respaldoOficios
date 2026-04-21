<?php
/**
 * Version del importador pensada para ejecutarse DENTRO del contenedor
 * respaldo_oficios_app. Paths absolutos del contenedor.
 * CSV esperado en /tmp/BASE_GENERAL_corregida.csv
 */
declare(strict_types=1);

define('IMPORT_CLI', true);
require_once '/var/www/html/config/config.php';
require_once '/var/www/html/config/database.php';

$dryRun   = in_array('--dry-run',  $argv ?? [], true);
$truncate = in_array('--truncate', $argv ?? [], true);
$csvPath  = '/tmp/BASE_GENERAL_corregida.csv';

if (!is_file($csvPath)) {
    fwrite(STDERR, "ERROR: no se encontro $csvPath\n");
    exit(1);
}

echo "========================================================\n";
echo " IMPORTADOR BASE GENERAL (contenedor)\n";
echo "========================================================\n";
echo " CSV      : $csvPath\n";
echo " Modo     : " . ($dryRun ? 'DRY-RUN' : 'ESCRITURA') . "\n";
echo " Truncate : " . ($truncate ? 'SI' : 'NO') . "\n";
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

$tipoExterno = (int)$pdo->query("SELECT id FROM tipos_oficio WHERE clave='EXTERNO'")->fetchColumn();
$usuarioCapturo = (int)$pdo->query("SELECT id FROM usuarios WHERE activo=TRUE ORDER BY rol_id ASC, id ASC LIMIT 1")->fetchColumn();
if (!$tipoExterno || !$usuarioCapturo) {
    fwrite(STDERR, "ERROR: falta tipo_oficio EXTERNO o usuario activo.\n"); exit(2);
}
echo "[info] usuario_capturo=$usuarioCapturo  tipo_externo=$tipoExterno\n\n";

function normaliza_nombre(string $s): string {
    $s = mb_strtoupper(trim($s), 'UTF-8');
    $sin = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
    return $sin !== false ? preg_replace('/\s+/',' ',$sin) : $s;
}
function slug_clave(string $s, int $len = 8): string {
    $s = normaliza_nombre($s);
    $s = preg_replace('/[^A-Z0-9 ]/','',$s);
    $parts = preg_split('/\s+/',$s,-1,PREG_SPLIT_NO_EMPTY);
    $iniciales='';
    foreach ($parts as $p) {
        if (in_array($p,['DE','DEL','LA','LOS','LAS','Y','EL','POR','A'],true)) continue;
        $iniciales .= substr($p,0,1);
        if (strlen($iniciales) >= $len) break;
    }
    return $iniciales !== '' ? $iniciales : substr(str_replace(' ','',$s),0,$len);
}

$depCache = [];
foreach ($pdo->query("SELECT id, nombre FROM dependencias") as $r) {
    $depCache[normaliza_nombre($r['nombre'])] = (int)$r['id'];
}

function obtener_dependencia_id(PDO $pdo, array &$cache, string $nombre, bool $dryRun): ?int {
    $nombre = trim($nombre);
    if ($nombre === '') return null;
    $key = normaliza_nombre($nombre);
    if (isset($cache[$key])) return $cache[$key];
    foreach ($cache as $k=>$id) {
        if ((str_contains($k,$key) || str_contains($key,$k)) && abs(strlen($k)-strlen($key))<10) {
            $cache[$key]=$id; return $id;
        }
    }
    $clave = slug_clave($nombre);
    $claveOri=$clave; $n=0;
    while (true) {
        $st=$pdo->prepare("SELECT 1 FROM dependencias WHERE clave=:c");
        $st->execute([':c'=>$clave]);
        if (!$st->fetchColumn()) break;
        $n++; $clave=$claveOri.$n;
    }
    if ($dryRun) { $cache[$key]=-1; return -1; }
    $st=$pdo->prepare("INSERT INTO dependencias(nombre,clave) VALUES(:n,:c) RETURNING id");
    $st->execute([':n'=>$nombre, ':c'=>$clave]);
    $id=(int)$st->fetchColumn();
    $cache[$key]=$id;
    echo "  [+] Nueva dependencia: $nombre (id=$id, clave=$clave)\n";
    return $id;
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

$pdo->beginTransaction();
try {
    if ($truncate && !$dryRun) {
        echo "[!] Liberando FKs en tablas de staging (si existen)...\n";
        foreach (['import_folios_interno_sub_raw','importacion_raw','import_oficios_raw','import_oficios_conocimiento_raw'] as $tbl) {
            $exists = (bool)$pdo->query("SELECT to_regclass('public.$tbl') IS NOT NULL")->fetchColumn();
            if (!$exists) continue;
            $hasCol = (bool)$pdo->query("SELECT 1 FROM information_schema.columns WHERE table_name='$tbl' AND column_name='oficio_id'")->fetchColumn();
            if ($hasCol) {
                $pdo->exec("UPDATE $tbl SET oficio_id = NULL WHERE oficio_id IS NOT NULL");
                echo "    - $tbl.oficio_id liberado\n";
            }
        }
        echo "[!] DELETE FROM movimientos_oficio / evidencias_pdf (CASCADE via oficios) ...\n";
        echo "[!] DELETE FROM oficios ...\n";
        $pdo->exec("DELETE FROM oficios");
    }
    $sql = "INSERT INTO oficios (numero_folio,anio_folio,folio_minutario,folio_direccion,tipo_oficio_id,dependencia_id,estado_id,asunto,observaciones,fecha_recepcion,fecha_oficio_tics,fecha_acuse,realizo,usuario_capturo_id) VALUES (:numero_folio,:anio_folio,:folio_minutario,:folio_direccion,:tipo_oficio_id,:dependencia_id,:estado_id,:asunto,:observaciones,:fecha_recepcion,:fecha_oficio_tics,:fecha_acuse,:realizo,:usuario_capturo_id) RETURNING id";
    $st = $pdo->prepare($sql);
    $ok=0; $err=0; $skip=0; $errores=[]; $skips=[];
    foreach ($rows as $i=>$row) {
        $linea=$i+2;
        $asunto=trim($row['asunto']??'');
        $fechaRec=trim($row['fecha_recepcion']??'');
        if ($asunto===''||$fechaRec==='') { $skips[]="linea $linea: asunto/fecha_recepcion vacio"; $skip++; continue; }
        $estadoNom=strtoupper(trim($row['estado_nombre']??'EN PROCESO'));
        $estadoId=$estadosMap[$estadoNom]??$estadosMap['EN PROCESO'];
        $depId=obtener_dependencia_id($pdo,$depCache,$row['dependencia_nombre']??'',$dryRun);
        $numFolio=trim($row['numero_folio']??'');
        $numFolio=$numFolio!==''?(int)$numFolio:null;
        $anioFolio=(int)($row['anio_folio']??date('Y'));
        $obsP=[];
        if (!empty($row['observaciones'])) $obsP[]=$row['observaciones'];
        if (!empty($row['capturo']))       $obsP[]='Capturo: '.$row['capturo'];
        if (!empty($row['folio_interno_tesoreria'])) $obsP[]='Folio interno: '.$row['folio_interno_tesoreria'];
        $obs=$obsP?implode(' | ',$obsP):null;
        $p=[
            ':numero_folio'=>$numFolio,
            ':anio_folio'=>$anioFolio,
            ':folio_minutario'=>$row['folio_minutario']?:null,
            ':folio_direccion'=>$row['folio_direccion']?:null,
            ':tipo_oficio_id'=>$tipoExterno,
            ':dependencia_id'=>$depId>0?$depId:null,
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
    echo " Omitidos  : $skip (filas sin asunto o sin fecha)\n";
    echo " Errores   : $err\n";
    if ($skips)    { echo "\n[omitidos]\n"; foreach(array_slice($skips,0,20) as $e) echo "  - $e\n"; }
    if ($errores) { echo "\n[errores]\n"; foreach(array_slice($errores,0,40) as $e) echo "  - $e\n"; if(count($errores)>40) echo "  ... y ".(count($errores)-40)." mas\n"; }
    if ($dryRun) { $pdo->rollBack(); echo "\n[dry-run] rollback.\n"; }
    else { $pdo->commit(); echo "\n[OK] COMMIT. $ok oficios insertados ($err errores no fatales, $skip omitidos).\n"; }
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR,"FATAL: ".$e->getMessage()."\n"); exit(3);
}
