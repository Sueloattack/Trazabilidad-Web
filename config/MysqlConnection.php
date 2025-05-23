<?php // config/MysqlConnection.php
declare(strict_types=1);

class MysqlConnection
{
    private static ?PDO $pdoInstance = null; // Para almacenar la instancia PDO única
    private static ?array $config = null;    // Para almacenar la configuración cargada

    // Constructor privado para prevenir la instanciación directa.
    private function __construct() {}

    // Método privado para clonar, para prevenir la clonación de la instancia.
    private function __clone() {}

    // Método privado para deserializar, para prevenir la deserialización.
    public function __wakeup() {}

    /**
     * Carga la configuración de la base de datos desde el archivo.
     * @return array La configuración de la base de datos.
     */
    private static function getConfig(): array
    {
        if (self::$config === null) {
            // Asume que database.php está en el mismo directorio (config)
            $configPath = __DIR__ . '/database.php'; 
            if (!file_exists($configPath)) {
                // Manejo de error si el archivo de configuración no existe
                error_log("Error crítico: El archivo de configuración de la base de datos MySQL no se encuentra en {$configPath}");
                die("Error de configuración del sistema. Por favor, contacte al administrador.");
            }
            self::$config = require $configPath;
        }
        return self::$config;
    }

    /**
     * Obtiene la instancia única de la conexión PDO a MySQL.
     * @return PDO La instancia de PDO.
     */
    public static function getInstance(): PDO
    {
        if (self::$pdoInstance === null) {
            $dbConfig = self::getConfig();

            $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['db']};charset={$dbConfig['charset']}";
            
            $options = $dbConfig['options'] ?? [ // Usar opciones del config o valores por defecto
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                self::$pdoInstance = new PDO(
                    $dsn,
                    $dbConfig['user'],
                    $dbConfig['pass'],
                    $options
                );
            } catch (\PDOException $e) {
                // En un entorno de producción, loguea el error y muestra un mensaje amigable.
                error_log("Error de conexión a MySQL: " . $e->getMessage() . "\nDSN: " . $dsn);
                // Considera no usar die() directamente en producción, sino un sistema de manejo de errores.
                die("No se pudo conectar a la base de datos principal. Por favor, intente más tarde o contacte al administrador.");
            }
        }
        return self::$pdoInstance;
    }
}
?>