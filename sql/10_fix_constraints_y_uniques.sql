-- =====================================================================
-- 10_fix_constraints_y_uniques.sql
-- Remediación QA Extrema — FASE 3 (medios M2, M9) + seed cleanup A5 +
-- login_intentos (rate-limit A-seguridad).
--
-- Contenido:
--   1. UNIQUE de personal.nombre_normalizado pasa a ser compuesto con
--      numero_empleado (permite homónimos cuando el num_empleado los
--      distingue; bloquea duplicados exactos).
--   2. Tabla login_intentos para rate-limit de login.
--   3. Cleanup de usuarios seed obsoletos (admin/admin@tesoreria/usuario).
--      - NO borra usuarios reales (lucio / any).
--      - Solo borra los del seed histórico si no tienen actividad.
--
-- Idempotente. Transaccional.
-- =====================================================================

BEGIN;

-- ---------------------------------------------------------------------
-- 1. Personal: homónimos con num_empleado distinto
--
--   Antes: UNIQUE (nombre_normalizado)  -> bloqueaba homónimos legítimos.
--   Ahora:
--     - UNIQUE (nombre_normalizado) cuando numero_empleado IS NULL
--       (evita que dos personas 'sin número' tengan mismo nombre).
--     - UNIQUE (nombre_normalizado, numero_empleado) cuando hay número
--       (permite homónimos si su num_empleado difiere).
-- ---------------------------------------------------------------------
DROP INDEX IF EXISTS idx_personal_nombre_norm_unique;

-- Homónimos sin número: no se permiten dos con el mismo nombre y ambos NULL
CREATE UNIQUE INDEX IF NOT EXISTS idx_personal_nombre_norm_sinnum_unique
    ON personal(nombre_normalizado)
    WHERE numero_empleado IS NULL;

-- Homónimos con número: la combinación nombre+num debe ser única
CREATE UNIQUE INDEX IF NOT EXISTS idx_personal_nombre_num_unique
    ON personal(nombre_normalizado, numero_empleado)
    WHERE numero_empleado IS NOT NULL;

-- ---------------------------------------------------------------------
-- 2. login_intentos — rate-limit de autenticación
--    (Se referencia desde Auth::login; bloquea 5 fallos en 10 min.)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_intentos (
    id          BIGSERIAL PRIMARY KEY,
    username    VARCHAR(120) NOT NULL,
    ip          INET,
    exito       BOOLEAN NOT NULL DEFAULT FALSE,
    motivo      VARCHAR(120),
    user_agent  TEXT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_login_intentos_username_ts
    ON login_intentos(username, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_login_intentos_ip_ts
    ON login_intentos(ip, created_at DESC);

-- ---------------------------------------------------------------------
-- 3. Seed cleanup — quitar usuarios de demo obsoletos
--    Los usuarios reales (lucio, any) se preservan. El usuario 'admin'
--    y 'usuario' del seed se eliminan solo si NO tienen actividad
--    (oficios capturados, movimientos, evidencias).
-- ---------------------------------------------------------------------
DO $$
DECLARE
    v_id INT;
    v_act INT;
BEGIN
    FOR v_id IN
        SELECT id FROM usuarios
         WHERE username IN ('admin','usuario')
           AND username NOT IN ('lucio','any')
    LOOP
        SELECT (SELECT COUNT(*) FROM oficios
                 WHERE usuario_capturo_id = v_id OR usuario_responsable_id = v_id) +
               (SELECT COUNT(*) FROM movimientos_oficio WHERE usuario_id = v_id) +
               (SELECT COUNT(*) FROM evidencias_pdf WHERE usuario_subio_id = v_id)
          INTO v_act;

        IF v_act = 0 THEN
            DELETE FROM bitacora WHERE usuario_id = v_id;
            DELETE FROM usuarios WHERE id = v_id;
        ELSE
            -- Con actividad: solo se desactiva para preservar integridad referencial.
            UPDATE usuarios SET activo = FALSE WHERE id = v_id;
        END IF;
    END LOOP;
END$$;

COMMIT;

-- Verificación
SELECT 'personal_total'       AS metrica, COUNT(*)::text AS valor FROM personal
UNION ALL SELECT 'usuarios_god_activos',
       COUNT(*)::text FROM usuarios WHERE rol_id = 1 AND activo = TRUE
UNION ALL SELECT 'login_intentos_tabla',
       CASE WHEN to_regclass('login_intentos') IS NULL THEN 'AUSENTE' ELSE 'OK' END;
