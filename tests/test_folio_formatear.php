<?php
/**
 * test_folio_formatear.php
 *
 * Verifica que FolioService::formatear produce exactamente el mismo folio
 * que la columna GENERATED STORED de Postgres (definida en sql/09).
 *
 * Regla: LPAD a 4 solo si numero_folio < 10000; a partir de 10000 se
 * imprime tal cual.
 *
 * Casos cubiertos (cubren el bug original C1 donde INTERNOs 5-dígitos
 * se truncaban a 4 caracteres y producían 51 duplicados visuales).
 */

declare(strict_types=1);

// Define constantes mínimas para cargar FolioService sin levantar la app.
require __DIR__ . '/../src/core/FolioService.php';

$casos = [
    // [numero_folio, anio, esperado]
    [1,       2026, 'TM/ECA/STIyC/0001/2026'],
    [7,       2026, 'TM/ECA/STIyC/0007/2026'],
    [58,      2026, 'TM/ECA/STIyC/0058/2026'],
    [495,     2025, 'TM/ECA/STIyC/0495/2025'],
    [2379,    2026, 'TM/ECA/STIyC/2379/2026'],
    [9999,    2026, 'TM/ECA/STIyC/9999/2026'],
    // Frontera: 10000 ya no debe llevar LPAD
    [10000,   2026, 'TM/ECA/STIyC/10000/2026'],
    [10001,   2026, 'TM/ECA/STIyC/10001/2026'],
    [15423,   2026, 'TM/ECA/STIyC/15423/2026'],
    [19999,   2026, 'TM/ECA/STIyC/19999/2026'],
    // CONOCIMIENTO
    [20000,   2026, 'TM/ECA/STIyC/20000/2026'],
    [20001,   2026, 'TM/ECA/STIyC/20001/2026'],
    [250000,  2026, 'TM/ECA/STIyC/250000/2026'],
];

$fallos = 0;
foreach ($casos as [$num, $anio, $esperado]) {
    $real = FolioService::formatear($num, $anio);
    if ($real !== $esperado) {
        echo "FAIL: formatear($num, $anio) -> '$real'  (esperado '$esperado')\n";
        $fallos++;
    } else {
        echo "ok   formatear($num, $anio) = $real\n";
    }
}

// Verificar rangos expuestos
$rangos = [
    'EXTERNO'      => [1, 9999],
    'INTERNO'      => [10000, 19999],
    'CONOCIMIENTO' => [20000, 2999999],
];
foreach ($rangos as $tipo => $esperado) {
    $real = FolioService::rango($tipo);
    if ($real !== $esperado) {
        echo "FAIL: rango($tipo) -> " . json_encode($real)
           . " (esperado " . json_encode($esperado) . ")\n";
        $fallos++;
    } else {
        echo "ok   rango($tipo) = [" . $real[0] . ", " . $real[1] . "]\n";
    }
}

if ($fallos > 0) {
    echo "\nFALLARON $fallos casos\n";
    exit(1);
}
echo "\nOK — todos los casos pasaron (" . count($casos) . " de formatear, "
   . count($rangos) . " de rango).\n";
exit(0);
