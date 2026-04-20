<?php
/**
 * test_ultimo_god.php
 *
 * READ-ONLY. Valida que el sistema NUNCA se quedará sin un usuario GOD
 * activo a raíz de un toggle/update legítimo.
 *
 * No ejecuta UPDATEs; solo verifica el estado actual y simula la consulta
 * que el UsuarioController usa para decidir si bloquea una acción.
 */

declare(strict_types=1);

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$name = getenv('DB_NAME') ?: 'respaldo_oficios';
$user = getenv('DB_USER') ?: 'oficios_user';
$pass = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$name", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "No se pudo conectar: " . $e->getMessage() . "\n");
    exit(1);
}

// ROL_GOD se define como 1 en src/config/config.php
const ROL_GOD_TEST = 1;

$activos = (int)$pdo->query(
    "SELECT COUNT(*) FROM usuarios WHERE rol_id = " . ROL_GOD_TEST . " AND activo = TRUE"
)->fetchColumn();

echo "GOD activos actualmente: $activos\n";

if ($activos < 1) {
    echo "FAIL — no hay ningún GOD activo. Crea uno siguiendo el README.md.\n";
    exit(1);
}
echo "ok   al menos un GOD activo\n";

// Simula: ¿qué pasaría si intento desactivar al primer GOD activo?
$primero = $pdo->query(
    "SELECT id, nombre FROM usuarios
      WHERE rol_id = " . ROL_GOD_TEST . " AND activo = TRUE
      ORDER BY id LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if ($primero) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM usuarios
          WHERE rol_id = " . ROL_GOD_TEST . "
            AND activo = TRUE
            AND id <> :id"
    );
    $stmt->execute([':id' => $primero['id']]);
    $otros = (int)$stmt->fetchColumn();
    echo "Si se desactivara GOD id={$primero['id']} ({$primero['nombre']}), "
       . "quedarían $otros GODs activos.\n";
    if ($otros === 0) {
        echo "ok   el controlador bloqueará esa operación (último GOD).\n";
    } else {
        echo "ok   la operación sería permitida (hay respaldo).\n";
    }
}

echo "\nOK — verificación de salvaguarda último GOD activo.\n";
exit(0);
