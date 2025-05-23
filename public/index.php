<?php
// Autoload o require de clases
// require_once __DIR__ . '/../config/database.php'; // Ya no necesitas esto directamente aquí para la conexión
require_once __DIR__ . '/../config/MysqlConnection.php'; // ¡Importante! Incluye la nueva clase
require_once __DIR__ . '/../src/Models/DataModel.php';
require_once __DIR__ . '/../src/Controllers/DataController.php';

// Conexión PDO MySQL (¡ahora más simple!)
try {
    $pdo = MysqlConnection::getInstance(); 
} catch (Exception $e) { // Captura la excepción que MysqlConnection podría lanzar (o el die)
    die("Error al obtener la conexión a la base de datos: " . $e->getMessage());
}

// Instancia modelo y controlador
$model = new DataModel($pdo); // Pasas la instancia PDO obtenida
$controller = new DataController($model);
$controller->handleRequest();