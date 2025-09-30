<?php
// api/reporte_erp_subdetalles_en_proceso.php

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once '../helpers/gema_api_client.php';

try {
    // 1. OBTENER EL PARÁMETRO REQUERIDO
    if (!isset($_GET['gr_docn']) || empty($_GET['gr_docn'])) {
        throw new Exception("El parámetro 'gr_docn' es requerido.");
    }
    $gr_docn = $_GET['gr_docn'];

    // 2. CONSTRUIR LA CONSULTA
    $fields = "fc_serie, fc_docn, fecha_gl, estatus1";
    $from = "gema10.d/salud/datos/glo_red";
    $where = "gr_docn = {$gr_docn}";
    
    $groupBy = "GROUP BY fc_serie, fc_docn, fecha_gl, estatus1";
    $order = "ORDER BY fc_serie, fc_docn, estatus1";

    $sql_glo_red = "{$fields} FROM {$from} WHERE {$where} {$groupBy} {$order}";

    // 3. EJECUTAR LA CONSULTA
    $sub_detalles = queryApiGema($sql_glo_red);

    // 4. DEVOLVER LA RESPUESTA
    echo json_encode($sub_detalles);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error fatal en reporte_erp_subdetalles.php: ".$e->getMessage());
    echo json_encode(['error' => 'Ocurrió un error en el servidor.', 'details' => $e->getMessage()]);
}
?>