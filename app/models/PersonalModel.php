<?php
/**
 * Repositorio de Personal.
 * Toda consulta usa PDO con prepared statements.
 */

class PersonalModel
{
    /**
     * Normaliza un nombre (upper, sin acentos, sin dobles espacios).
     * Útil para el matching de importación y para garantizar unicidad.
     */
    public static function normalizarNombre(string $s): string
    {
        $s = trim($s);
        if ($s === '') return '';
        if (class_exists('Normalizer')) {
            $s = Normalizer::normalize($s, Normalizer::FORM_D);
        }
        // remover diacríticos
        $s = preg_replace('/\p{Mn}+/u', '', $s) ?? $s;
        // collapse espacios
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return mb_strtoupper(trim($s), 'UTF-8');
    }

    /**
     * Listado con filtros.
     * @param array{num?:string, nombre?:string, tipo?:string, activo?:string} $filtros
     */
    public static function listar(array $filtros = []): array
    {
        $pdo = Database::pdo();
        $where  = ['1=1'];
        $params = [];

        if (!empty($filtros['num'])) {
            $where[] = "p.numero_empleado ILIKE :num";
            $params[':num'] = '%' . $filtros['num'] . '%';
        }
        if (!empty($filtros['nombre'])) {
            $where[] = "p.nombre_normalizado ILIKE :nombre";
            $params[':nombre'] = '%' . self::normalizarNombre($filtros['nombre']) . '%';
        }
        if (!empty($filtros['tipo']) && in_array($filtros['tipo'], ['SINDICALIZADO', 'CONFIANZA'], true)) {
            $where[] = "tp.clave = :tipo";
            $params[':tipo'] = $filtros['tipo'];
        }
        if (isset($filtros['activo']) && $filtros['activo'] !== '') {
            $where[] = "p.activo = :activo";
            $params[':activo'] = $filtros['activo'] === '1' ? 'true' : 'false';
        }

        $sql = "SELECT p.*, tp.clave AS tipo_clave, tp.nombre AS tipo_nombre
                  FROM personal p
                  JOIN tipos_personal tp ON tp.id = p.tipo_personal_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY p.nombre_completo ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function obtener(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            "SELECT p.*, tp.clave AS tipo_clave, tp.nombre AS tipo_nombre
               FROM personal p
               JOIN tipos_personal tp ON tp.id = p.tipo_personal_id
              WHERE p.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function tiposPersonal(): array
    {
        $pdo = Database::pdo();
        return $pdo->query(
            "SELECT id, clave, nombre FROM tipos_personal WHERE activo = TRUE ORDER BY nombre"
        )->fetchAll();
    }

    /**
     * Crea o devuelve el id por match (numero_empleado primero, luego nombre_normalizado).
     * Usado por el importador.
     *
     * @return array{id:int, accion:string, estado:string, motivo:?string}
     */
    public static function upsertParaImport(
        ?string $numeroEmpleado,
        string $nombre,
        int $tipoPersonalId,
        array $extras = []
    ): array {
        $pdo = Database::pdo();
        $nombre = trim($nombre);
        if ($nombre === '') {
            return ['id' => 0, 'accion' => 'OMITIDO', 'estado' => 'ERROR', 'motivo' => 'Nombre vacío.'];
        }
        $norm = self::normalizarNombre($nombre);

        // 1) por numero_empleado
        if ($numeroEmpleado !== null && $numeroEmpleado !== '') {
            $s = $pdo->prepare("SELECT id FROM personal WHERE numero_empleado = :n LIMIT 1");
            $s->execute([':n' => $numeroEmpleado]);
            $existId = $s->fetchColumn();
            if ($existId) {
                // Actualiza tipo/extras si vienen
                self::actualizarCamposOpcionales((int)$existId, $tipoPersonalId, $extras);
                return ['id' => (int)$existId, 'accion' => 'ACTUALIZADO', 'estado' => 'OK', 'motivo' => null];
            }
        }

        // 2) por nombre_normalizado
        $s = $pdo->prepare("SELECT id, numero_empleado FROM personal WHERE nombre_normalizado = :n LIMIT 1");
        $s->execute([':n' => $norm]);
        $row = $s->fetch();
        if ($row) {
            // Si tiene numero_empleado distinto => ambigüedad; marcar revisión.
            if ($row['numero_empleado'] && $numeroEmpleado && $row['numero_empleado'] !== $numeroEmpleado) {
                return [
                    'id' => (int)$row['id'],
                    'accion' => 'OMITIDO',
                    'estado' => 'PENDIENTE_REVISION',
                    'motivo' => "Mismo nombre pero num_empleado distinto (BD={$row['numero_empleado']}, Excel={$numeroEmpleado}).",
                ];
            }
            // Si BD no tiene num y el Excel sí, actualizarlo.
            if (!$row['numero_empleado'] && $numeroEmpleado) {
                $upd = $pdo->prepare("UPDATE personal SET numero_empleado = :n WHERE id = :id");
                $upd->execute([':n' => $numeroEmpleado, ':id' => $row['id']]);
            }
            self::actualizarCamposOpcionales((int)$row['id'], $tipoPersonalId, $extras);
            return ['id' => (int)$row['id'], 'accion' => 'ACTUALIZADO', 'estado' => 'OK', 'motivo' => null];
        }

        // 3) Crear
        $sqlIns = "INSERT INTO personal
            (numero_empleado, nombre_completo, nombre_normalizado, tipo_personal_id,
             categoria, horario, situacion_medica, curp, rfc, clave_issemym,
             grado_estudios, carrera)
            VALUES
            (:num, :nombre, :norm, :tipo,
             :categoria, :horario, :sit_med, :curp, :rfc, :issemym,
             :estudios, :carrera)
            RETURNING id";
        $ins = $pdo->prepare($sqlIns);
        $ins->execute([
            ':num'        => $numeroEmpleado ?: null,
            ':nombre'     => $nombre,
            ':norm'       => $norm,
            ':tipo'       => $tipoPersonalId,
            ':categoria'  => $extras['categoria']      ?? null,
            ':horario'    => $extras['horario']        ?? null,
            ':sit_med'    => $extras['situacion_medica'] ?? null,
            ':curp'       => $extras['curp']           ?? null,
            ':rfc'        => $extras['rfc']            ?? null,
            ':issemym'    => $extras['clave_issemym']  ?? null,
            ':estudios'   => $extras['grado_estudios'] ?? null,
            ':carrera'    => $extras['carrera']        ?? null,
        ]);
        $id = (int)$ins->fetchColumn();
        return ['id' => $id, 'accion' => 'CREADO', 'estado' => 'OK', 'motivo' => null];
    }

    /**
     * Actualiza solo campos que venían nulos, evitando sobrescribir datos buenos.
     */
    private static function actualizarCamposOpcionales(int $id, int $tipoId, array $extras): void
    {
        $pdo = Database::pdo();

        $upd = [];
        $params = [':id' => $id];

        // tipo: lo mantenemos si ya está
        $upd[] = "tipo_personal_id = COALESCE(tipo_personal_id, :tipo)";
        $params[':tipo'] = $tipoId;

        $mapa = [
            'categoria'        => 'categoria',
            'horario'          => 'horario',
            'situacion_medica' => 'situacion_medica',
            'curp'             => 'curp',
            'rfc'              => 'rfc',
            'clave_issemym'    => 'clave_issemym',
            'grado_estudios'   => 'grado_estudios',
            'carrera'          => 'carrera',
        ];
        foreach ($mapa as $k => $col) {
            if (!empty($extras[$k])) {
                $upd[] = "$col = COALESCE($col, :$col)";
                $params[":$col"] = $extras[$k];
            }
        }

        $sql = "UPDATE personal SET " . implode(', ', $upd) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Crea personal desde formulario web.
     */
    public static function crearManual(array $data): int
    {
        $pdo = Database::pdo();
        $nombre = trim($data['nombre_completo']);
        $norm   = self::normalizarNombre($nombre);

        $sql = "INSERT INTO personal
            (numero_empleado, nombre_completo, nombre_normalizado, tipo_personal_id,
             categoria, horario, observaciones, activo)
            VALUES (:num, :nombre, :norm, :tipo, :categoria, :horario, :obs, TRUE)
            RETURNING id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':num'       => $data['numero_empleado'] ?: null,
            ':nombre'    => $nombre,
            ':norm'      => $norm,
            ':tipo'      => (int)$data['tipo_personal_id'],
            ':categoria' => $data['categoria'] ?: null,
            ':horario'   => $data['horario']   ?: null,
            ':obs'       => $data['observaciones'] ?: null,
        ]);
        return (int)$stmt->fetchColumn();
    }

    public static function actualizarManual(int $id, array $data): void
    {
        $pdo = Database::pdo();
        $nombre = trim($data['nombre_completo']);
        $norm   = self::normalizarNombre($nombre);

        $sql = "UPDATE personal SET
                    numero_empleado  = :num,
                    nombre_completo  = :nombre,
                    nombre_normalizado = :norm,
                    tipo_personal_id = :tipo,
                    categoria        = :categoria,
                    horario          = :horario,
                    observaciones    = :obs,
                    activo           = :activo
                  WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':num'       => $data['numero_empleado'] ?: null,
            ':nombre'    => $nombre,
            ':norm'      => $norm,
            ':tipo'      => (int)$data['tipo_personal_id'],
            ':categoria' => $data['categoria'] ?: null,
            ':horario'   => $data['horario']   ?: null,
            ':obs'       => $data['observaciones'] ?: null,
            ':activo'    => !empty($data['activo']) ? 'true' : 'false',
            ':id'        => $id,
        ]);
    }
}
