<?php
// api/reporte_ingreso.php (Lanzador de Reporte)
ini_set('memory_limit', '512M'); 
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');


require_once '../helpers/gema_api_client.php';
require_once '../helpers/reporte_engine.php'; // Incluimos el nuevo motor

try {
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');

    // ESTADOS ESPECÍFICOS DEL REPORTE DE INGRESO
    $estatus_validos = ['NU', 'R2', 'R3', 'R4', 'AE'];
    // CLAVES DE DESGLOSE A ESPERAR EN EL FORMATO FINAL (minúsculas)
    $desglose_keys = ['nu', 'r2', 'r3', 'r4', 'ae']; 

    $estatus_aceptado_ingreso = 'ae';

    // Llama al motor de procesamiento y obtiene la respuesta en PHP Array
    $respuestaFinal = generarReporte(
        $fecha_inicio,
        $fecha_fin,
        $estatus_validos,
        $desglose_keys,
        $estatus_aceptado_ingreso
    );
    
    // Devuelve la respuesta como JSON
    echo json_encode($respuestaFinal);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error fatal en reporte_ingreso.php: ".$e->getMessage());
    echo json_encode(['error' => 'Ocurrió un error en el servidor.', 'details' => $e->getMessage()]);
}
?>