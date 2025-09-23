<?php
// api/reporte_analistas.php (Lanzador de Reporte Analistas)
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');


require_once '../helpers/gema_api_client.php';
require_once '../helpers/reporte_engine.php'; // Incluimos el motor

try {
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');

    // ESTADOS ESPECÍFICOS DEL REPORTE DE ANALISTAS
    $estatus_validos = ['C1', 'C2', 'C3', 'CO', 'AI']; 
    // CLAVES DE DESGLOSE A ESPERAR (minúsculas)
    // Usamos AE si esa lógica también debe existir en analistas. Si no, quítalo. 
    // Los 4 que vienen del estatus 1 y la clave "ae" por si acaso.
    $desglose_keys = ['c1', 'c2', 'c3', 'co', 'ai', 'ae']; 

    $estatus_aceptado_analistas = 'ai';

    // Llama al motor de procesamiento
    $respuestaFinal = generarReporte(
        $fecha_inicio,
        $fecha_fin,
        $estatus_validos,
        $desglose_keys,
        $estatus_aceptado_analistas
    );
    
    // Devuelve la respuesta como JSON
    echo json_encode($respuestaFinal);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error fatal en reporte_analistas.php: ".$e->getMessage());
    echo json_encode(['error' => 'Ocurrió un error en el servidor.', 'details' => $e->getMessage()]);
}
?>