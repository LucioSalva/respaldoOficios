<?php
/**
 * FolioService — Generación atómica del número de folio por tipo.
 *
 * Problema: dos requests concurrentes que hacen SELECT MAX + INSERT
 * pueden generar el mismo numero_folio y colisionar con la UNIQUE
 * (numero_folio, anio_folio).
 *
 * Solución: pg_advisory_xact_lock sobre una clave estable por tipo
 * (hashtext('folio:'||tipo_clave[||anio])). El lock se libera con
 * el COMMIT/ROLLBACK de la transacción.
 *
 * Rangos de numeración por tipo:
 *   EXTERNO       : 1 .. 9999          (por año)
 *   INTERNO       : 10000 .. 19999     (global, no por año)
 *   CONOCIMIENTO  : 20000 .. 2999999   (global, no por año)
 *
 * Uso típico:
 *
 *   $pdo->beginTransaction();
 *   $numero = FolioService::generarNuevoFolio($pdo, 'EXTERNO', 2026);
 *   // ... INSERT INTO oficios ...
 *   $pdo->commit();
 */

class FolioService
{
    public const TIPOS = ['EXTERNO', 'INTERNO', 'CONOCIMIENTO'];

    /**
     * Reserva y devuelve el siguiente número de folio disponible para el
     * tipo dado. Debe llamarse DENTRO de una transacción abierta por
     * el caller (el advisory lock se libera con commit/rollback).
     */
    public static function generarNuevoFolio(PDO $pdo, string $tipoClave, int $anio): int
    {
        $tipoClave = strtoupper(trim($tipoClave));
        if (!in_array($tipoClave, self::TIPOS, true)) {
            throw new InvalidArgumentException("Tipo de oficio no soportado: $tipoClave");
        }
        if (!$pdo->inTransaction()) {
            throw new RuntimeException(
                'FolioService::generarNuevoFolio requiere una transacción abierta.'
            );
        }

        // Clave del advisory lock. Para EXTERNO incluimos el año (rangos
        // por año); para INTERNO/CONOCIMIENTO el rango es global.
        $lockKey = $tipoClave === 'EXTERNO'
            ? "folio:EXTERNO:$anio"
            : "folio:$tipoClave";

        $stmt = $pdo->prepare("SELECT pg_advisory_xact_lock(hashtext(:k))");
        $stmt->execute([':k' => $lockKey]);

        [$min, $max] = self::rango($tipoClave);
        $tipoId = self::resolverTipoId($pdo, $tipoClave);

        if ($tipoClave === 'EXTERNO') {
            $q = $pdo->prepare(
                "SELECT COALESCE(MAX(numero_folio), :min - 1)
                   FROM oficios
                  WHERE tipo_oficio_id = :tid
                    AND anio_folio = :anio
                    AND numero_folio BETWEEN :min AND :max"
            );
            $q->execute([
                ':min' => $min,
                ':max' => $max,
                ':tid' => $tipoId,
                ':anio'=> $anio,
            ]);
        } else {
            $q = $pdo->prepare(
                "SELECT COALESCE(MAX(numero_folio), :min - 1)
                   FROM oficios
                  WHERE tipo_oficio_id = :tid
                    AND numero_folio BETWEEN :min AND :max"
            );
            $q->execute([
                ':min' => $min,
                ':max' => $max,
                ':tid' => $tipoId,
            ]);
        }

        $maxActual = (int)$q->fetchColumn();
        $nuevo = $maxActual + 1;
        if ($nuevo > $max) {
            throw new RuntimeException(
                "Rango de folios agotado para $tipoClave (máximo $max)."
            );
        }
        if ($nuevo < $min) {
            $nuevo = $min;
        }
        return $nuevo;
    }

    /** Rango [min, max] por tipo. */
    public static function rango(string $tipoClave): array
    {
        return match (strtoupper($tipoClave)) {
            'EXTERNO'      => [1, 9999],
            'INTERNO'      => [10000, 19999],
            'CONOCIMIENTO' => [20000, 2999999],
            default        => throw new InvalidArgumentException("Tipo inválido: $tipoClave"),
        };
    }

    /**
     * Formato estándar del folio de tesorería (idéntico al GENERATED STORED
     * en SQL tras la migración 09). Útil para preview cliente y tests.
     */
    public static function formatear(int $numeroFolio, int $anio): string
    {
        $numTxt = $numeroFolio < 10000
            ? str_pad((string)$numeroFolio, 4, '0', STR_PAD_LEFT)
            : (string)$numeroFolio;
        return 'TM/ECA/STIyC/' . $numTxt . '/' . $anio;
    }

    private static function resolverTipoId(PDO $pdo, string $clave): int
    {
        static $cache = [];
        if (isset($cache[$clave])) return $cache[$clave];
        $s = $pdo->prepare("SELECT id FROM tipos_oficio WHERE clave = :c");
        $s->execute([':c' => $clave]);
        $id = (int)$s->fetchColumn();
        if ($id <= 0) {
            throw new RuntimeException("tipos_oficio.$clave no existe en BD.");
        }
        $cache[$clave] = $id;
        return $id;
    }
}
