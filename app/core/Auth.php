<?php
/**
 * Autenticación y control de acceso.
 *
 * Incluye:
 *   - Login por username O email (un solo campo en el form).
 *   - Rate-limit por username y por IP contra tabla login_intentos
 *     (ventana rodante de 10 minutos, máximo 5 intentos fallidos).
 *   - Regeneración de session id post-login.
 *   - Logout con invalidación de cookie y destrucción segura de sesión
 *     (sin levantar una sesión nueva después).
 *   - CSRF por sesión con hash_equals.
 *   - Bitácora best-effort: nunca rompe la request si falla el INSERT.
 */

class Auth
{
    /** Máximo de intentos fallidos en la ventana para username o IP. */
    private const LOGIN_MAX_FAILS       = 5;
    /** Ventana en minutos para conteo del rate-limit. */
    private const LOGIN_WINDOW_MINUTES  = 10;

    /**
     * Intenta hacer login. Acepta username o email en el campo $login.
     * Retorna true en éxito, false en fallo.
     */
    public static function login(string $login, string $password): bool
    {
        $login = mb_strtolower(trim($login));
        $ip    = self::clientIp();

        // Rate-limit: cuenta fallos recientes por username/email y por IP.
        if (self::loginBloqueado($login, $ip)) {
            self::registrarBitacora(null, 'LOGIN_BLOQUEADO', null, null, [
                'login' => $login,
                'ip'    => $ip,
                'motivo'=> 'rate_limit',
            ]);
            return false;
        }

        try {
            $pdo  = Database::pdo();
            $stmt = $pdo->prepare(
                "SELECT u.id, u.nombre, u.username, u.email, u.password_hash,
                        u.rol_id, u.activo, r.nombre AS rol_nombre
                   FROM usuarios u
                   JOIN roles r ON r.id = u.rol_id
                  WHERE (LOWER(u.username) = :login OR LOWER(u.email) = :login)
                  LIMIT 1"
            );
            $stmt->execute([':login' => $login]);
            $user = $stmt->fetch();
        } catch (\PDOException $e) {
            error_log('Auth::login error: ' . $e->getMessage());
            return false;
        }

        $ok = $user
            && (bool)$user['activo'] === true
            && !empty($user['password_hash'])
            && password_verify($password, $user['password_hash']);

        if (!$ok) {
            $motivo = !$user
                ? 'usuario_no_existe'
                : (!(bool)$user['activo']
                    ? 'usuario_inactivo'
                    : 'password_invalido');

            self::registrarLoginIntento($login, $ip, false);
            self::registrarBitacora(null, 'LOGIN_FALLIDO', null, null, [
                'login'  => $login,
                'motivo' => $motivo,
            ]);
            return false;
        }

        // Éxito: limpiar historial de intentos fallidos y regenerar ID.
        self::registrarLoginIntento($login, $ip, true);
        session_regenerate_id(true);

        $_SESSION['user_id']       = (int)$user['id'];
        $_SESSION['user_nombre']   = $user['nombre'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_email']    = $user['email'] ?? null;
        $_SESSION['user_rol_id']   = (int)$user['rol_id'];
        $_SESSION['user_rol']      = $user['rol_nombre'];
        $_SESSION['login_at']      = time();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        self::registrarBitacora((int)$user['id'], 'LOGIN', null, null, null);
        return true;
    }

    /**
     * Cierra la sesión de forma segura:
     *   1. Registra bitácora.
     *   2. Limpia $_SESSION.
     *   3. Expira la cookie de sesión en el navegador.
     *   4. Destruye la sesión en disco.
     * No se vuelve a llamar session_start() — eso se hará en el próximo request.
     */
    public static function logout(): void
    {
        $uid = $_SESSION['user_id'] ?? null;
        self::registrarBitacora($uid, 'LOGOUT', null, null, null);

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $p['path'] ?? '/',
                    'domain'   => $p['domain'] ?? '',
                    'secure'   => $p['secure'] ?? false,
                    'httponly' => $p['httponly'] ?? true,
                    'samesite' => $p['samesite'] ?? 'Strict',
                ]
            );
        }
        session_unset();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /** Verifica si hay sesión activa */
    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /** Requiere sesión activa; redirige a login si no */
    public static function requireAuth(): void
    {
        if (!self::check()) {
            redirect_to('/login');
        }
        // Previene que páginas autenticadas queden en caché del navegador;
        // así, tras logout, el botón "atrás" no muestra contenido privado.
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }

    /**
     * Requiere un rol mínimo
     * @param int|int[] $roles ROL_GOD | ROL_ADMIN | ROL_USER o array
     */
    public static function requireRole($roles): void
    {
        self::requireAuth();
        $roles = (array)$roles;
        if (!in_array($_SESSION['user_rol_id'], $roles, true)) {
            http_response_code(403);
            require VIEWS_PATH . '/errors/403.php';
            exit;
        }
    }

    /** ID del usuario actual */
    public static function userId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /** Nombre del usuario actual */
    public static function userName(): string
    {
        return $_SESSION['user_nombre'] ?? 'Desconocido';
    }

    /** Rol ID del usuario actual */
    public static function userRolId(): int
    {
        return $_SESSION['user_rol_id'] ?? ROL_USER;
    }

    /** Nombre del rol actual */
    public static function userRol(): string
    {
        return $_SESSION['user_rol'] ?? 'User';
    }

    /** Verifica si el usuario tiene alguno de los roles dados */
    public static function hasRole($roles): bool
    {
        if (!self::check()) return false;
        return in_array($_SESSION['user_rol_id'], (array)$roles, true);
    }

    /** Obtiene el token CSRF de la sesión */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Valida un token CSRF enviado en POST */
    public static function verifyCsrf(string $token): bool
    {
        if (empty($_SESSION['csrf_token'])) return false;
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /** Registra una acción en la bitácora (best-effort, jamás lanza). */
    public static function registrarBitacora(
        ?int $usuario_id,
        string $accion,
        ?string $tabla,
        ?int $registro_id,
        ?array $datos
    ): void {
        try {
            $pdo  = Database::pdo();
            $stmt = $pdo->prepare(
                "INSERT INTO bitacora (usuario_id, accion, tabla, registro_id, datos_json, ip, user_agent)
                 VALUES (:uid, :accion, :tabla, :rid, :datos, :ip::inet, :ua)"
            );
            $ip = self::clientIp();
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $stmt->execute([
                ':uid'   => $usuario_id,
                ':accion'=> $accion,
                ':tabla' => $tabla,
                ':rid'   => $registro_id,
                ':datos' => $datos ? json_encode($datos, JSON_UNESCAPED_UNICODE) : null,
                ':ip'    => $ip,
                ':ua'    => $ua,
            ]);
        } catch (Throwable $e) {
            error_log('Bitacora error: ' . $e->getMessage());
        }
    }

    // -----------------------------------------------------------------
    // Internos: rate-limit y login_intentos
    // -----------------------------------------------------------------

    /**
     * Registra un intento (OK o fallo) en login_intentos.
     * En éxito, limpia los fallos previos del mismo username/IP para no
     * arrastrar contadores entre sesiones legítimas.
     */
    private static function registrarLoginIntento(string $username, string $ip, bool $exito): void
    {
        try {
            $pdo = Database::pdo();
            if ($exito) {
                $del = $pdo->prepare(
                    "DELETE FROM login_intentos
                      WHERE (LOWER(username) = :u OR ip::text = :ip)
                        AND exito = FALSE"
                );
                $del->execute([':u' => $username, ':ip' => $ip]);
            }
            $stmt = $pdo->prepare(
                "INSERT INTO login_intentos (username, ip, exito, user_agent)
                 VALUES (:u, :ip::inet, :ok, :ua)"
            );
            $stmt->execute([
                ':u'  => $username,
                ':ip' => $ip,
                ':ok' => $exito ? 'true' : 'false',
                ':ua' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ]);
        } catch (Throwable $e) {
            // Si la tabla no existe todavía (migración 10 pendiente) o falla
            // por cualquier razón, no bloqueamos el login.
            error_log('login_intentos error: ' . $e->getMessage());
        }
    }

    /**
     * ¿El login está bloqueado por rate-limit? Cuenta fallos en la ventana
     * rodante por username O por IP. Si la tabla no existe, devuelve false
     * (permite el intento) en lugar de romper login.
     */
    private static function loginBloqueado(string $username, string $ip): bool
    {
        try {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare(
                "SELECT
                   SUM(CASE WHEN LOWER(username) = :u AND exito = FALSE THEN 1 ELSE 0 END) AS fails_user,
                   SUM(CASE WHEN ip::text        = :ip AND exito = FALSE THEN 1 ELSE 0 END) AS fails_ip
                 FROM login_intentos
                 WHERE created_at >= NOW() - (:mins || ' minutes')::interval"
            );
            $stmt->execute([
                ':u'    => $username,
                ':ip'   => $ip,
                ':mins' => (string)self::LOGIN_WINDOW_MINUTES,
            ]);
            $row = $stmt->fetch();
            $failsUser = (int)($row['fails_user'] ?? 0);
            $failsIp   = (int)($row['fails_ip']   ?? 0);
            return $failsUser >= self::LOGIN_MAX_FAILS
                || $failsIp   >= self::LOGIN_MAX_FAILS;
        } catch (Throwable $e) {
            error_log('login_intentos check error: ' . $e->getMessage());
            return false;
        }
    }

    private static function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return '0.0.0.0';
        }
        return $ip;
    }
}
