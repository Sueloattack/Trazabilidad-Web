<?php
// Configuración PDO para MySQL
return [
    'host' => '192.168.1.1',     // O tu host 192.168.1.16
    'db' => 'calidad',             // Tu nombre de base de datos
    'user' => 'local_ro',          // Tu usuario de MySQL
    'pass' => '@so2025*',             // Tu contraseña de MySQL
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, // Buena práctica para MySQL
        // PDO::MYSQL_ATTR_SSL_CA => '/path/to/ca-cert.pem', // Ejemplo si usas SSL
    ],
];
?>