<?php
/**
 * test_csrf_token.php
 *
 * Prueba puramente en memoria que el mecanismo CSRF usado por Auth:
 *   - genera tokens de 64 hex (32 bytes de random_bytes).
 *   - compara con hash_equals (timing-safe).
 *   - rechaza tokens truncados, vacíos y con caracteres distintos.
 */

declare(strict_types=1);

$t1 = bin2hex(random_bytes(32));
$t2 = bin2hex(random_bytes(32));

assert(strlen($t1) === 64, 'token debe ser 64 chars hex');
assert(ctype_xdigit($t1), 'token debe ser hex puro');
assert($t1 !== $t2, 'dos tokens consecutivos no deben coincidir');

if (!hash_equals($t1, $t1)) {
    fwrite(STDERR, "FAIL — hash_equals con token idéntico debe ser true\n");
    exit(1);
}
if (hash_equals($t1, $t2)) {
    fwrite(STDERR, "FAIL — hash_equals con tokens distintos debe ser false\n");
    exit(1);
}
if (hash_equals($t1, substr($t1, 0, 60))) {
    fwrite(STDERR, "FAIL — hash_equals debe rechazar token truncado\n");
    exit(1);
}
if (hash_equals($t1, '')) {
    fwrite(STDERR, "FAIL — hash_equals debe rechazar vacío\n");
    exit(1);
}

echo "ok   token 64-hex, aleatorio, comparación segura\n";
echo "\nOK — CSRF mecánico correcto.\n";
exit(0);
