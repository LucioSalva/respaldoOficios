<?php
/**
 * Conexión PDO singleton a PostgreSQL
 */

class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                DB_HOST, DB_PORT, DB_NAME
            );

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ]);
                // Zona horaria PostgreSQL sincronizada
                self::$instance->exec("SET TIME ZONE 'America/Mexico_City'");
            } catch (PDOException $e) {
                // Delegamos al handler global (loguea, bitácora, respuesta amigable).
                // Nunca se expone el mensaje PDO al cliente.
                error_log('DB Connection failed: ' . $e->getMessage());
                throw new RuntimeException('Error de conexión a base de datos.', 0, $e);
            }
        }

        return self::$instance;
    }

    /**
     * Shortcut para obtener la instancia PDO
     */
    public static function pdo(): PDO
    {
        return self::getInstance();
    }
}
