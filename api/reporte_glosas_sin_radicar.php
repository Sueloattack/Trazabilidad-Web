<?php
// api/reporte_facturas_sin_radicar.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once '../helpers/gema_api_client.php';

try {
    // 1. OBTENER PARÁMETROS
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
    $fecha_inicio_vfp = "{^{$fecha_inicio}}";
    $fecha_fin_vfp = "{^{$fecha_fin}}";

    // 2. CONSULTA
    $sql = "DISTINCT cab.tercero, cab.fc_serie, cab.fc_docn, det.estatus1, det.fecha_gl, det.gr_docn 
            FROM gema10.d/salud/datos/glo_det det, gema10.d/salud/datos/glo_cab cab 
            WHERE cab.gl_docn = det.gl_docn AND BETWEEN(det.fecha_gl, {$fecha_inicio_vfp}, {$fecha_fin_vfp}) 
            AND (det.estatus1 = 'C1' OR det.estatus1 = 'C2' OR det.estatus1 = 'C3' OR det.estatus1 = 'CO' OR det.estatus1 = 'AI') 
            AND EMPTY(det.fecha_rep)";
    $raw_results = queryApiGema($sql);

    if (empty($raw_results)) {
        echo json_encode(['data' => [], 'detalle_mapa' => new stdClass()]);
        exit;
    }

    // 3. PROCESAMIENTO EN PHP
    $resumen_por_entidad = [];
    $detalle_mapa = []; // Estructura: [tercero_code][glosa_key] => ['glosa' => ..., 'tiene_cuenta' => bool, 'items' => []]

    foreach ($raw_results as $row) {
        $tercero_code = trim($row['tercero']);
        if (empty($tercero_code)) continue;

        $glosa_key = trim($row['fc_serie']) . trim($row['fc_docn']);
        $item_details = [
            'estado' => trim($row['estatus1']),
            'fecha_gl' => trim($row['fecha_gl'])
        ];
        $tiene_gr_docn = !empty(trim($row['gr_docn']));

        // Inicializar el resumen para la entidad si no existe
        if (!isset($resumen_por_entidad[$tercero_code])) {
            $resumen_por_entidad[$tercero_code] = [
                'tercero_code' => $tercero_code,
                'tercero_nombre' => '',
                'total_glosas' => 0,
                'con_cuenta' => 0,
                'sin_cuenta' => 0
            ];
            $detalle_mapa[$tercero_code] = [];
        }

        // Si es la primera vez que vemos esta glosa, la contamos e inicializamos
        if (!isset($detalle_mapa[$tercero_code][$glosa_key])) {
            $resumen_por_entidad[$tercero_code]['total_glosas']++;
            $detalle_mapa[$tercero_code][$glosa_key] = [
                'glosa' => $glosa_key,
                'tiene_cuenta' => false, // Se actualizará si cualquier item tiene cuenta
                'gr_docn'      => null, // Inicializar el campo
                'items' => []
            ];
        }

        // Añadir el item (estado, fecha) a la glosa
        $detalle_mapa[$tercero_code][$glosa_key]['items'][] = $item_details;

        // Marcar la glosa como 'tiene_cuenta' si al menos uno de sus items la tiene y almacenar el valor
        if ($tiene_gr_docn) {
            $detalle_mapa[$tercero_code][$glosa_key]['tiene_cuenta'] = true;
            $detalle_mapa[$tercero_code][$glosa_key]['gr_docn'] = trim($row['gr_docn']);
        }
    }

    // Contar glosas con y sin cuenta de cobro
    foreach ($resumen_por_entidad as $tercero_code => &$entidad) {
        if (isset($detalle_mapa[$tercero_code])) {
            foreach ($detalle_mapa[$tercero_code] as $glosa_detalle) {
                if ($glosa_detalle['tiene_cuenta']) {
                    $entidad['con_cuenta']++;
                } else {
                    $entidad['sin_cuenta']++;
                }
            }
        }
    }
    unset($entidad);

    // --- Obtener nombres de terceros ---
    $codigos_terceros = array_keys($resumen_por_entidad);
    $mapa_nombres = [];
    if (!empty($codigos_terceros)) {
        $where_conditions = [];
        foreach ($codigos_terceros as $codigo) {
            if (is_numeric(trim($codigo))) {
                $where_conditions[] = "codigo = ".trim($codigo);
            }
        }
        if (!empty($where_conditions)) {
            $sql_terceros = "codigo, nombre FROM gema10.d/dgen/datos/terceros WHERE ".implode(' OR ', $where_conditions);
            $terceros_data = queryApiGema($sql_terceros);
            foreach ($terceros_data as $tercero) {
                $mapa_nombres[trim($tercero['codigo'])] = trim($tercero['nombre']);
            }
        }
    }

    // --- Enriquecer CADA GLOSA con su información de tercero ---
    foreach ($detalle_mapa as $code => &$glosas) {
        $nombre = $mapa_nombres[$code] ?? $code;
        foreach ($glosas as &$glosa_detalle) {
            $glosa_detalle['tercero_code'] = $code;
            $glosa_detalle['tercero_nombre'] = $nombre;
        }
        unset($glosa_detalle);
    }
    unset($glosas);

    // --- Enriquecer el resumen con los nombres y reindexar el mapa de detalles ---
    $detalle_mapa_con_nombres = [];
    foreach ($resumen_por_entidad as $code => &$entidad) {
        $nombre = $mapa_nombres[$code] ?? $code;
        $entidad['tercero_nombre'] = $nombre;
        if (isset($detalle_mapa[$code])) {
            // Convertir el array asociativo de glosas en una lista simple para JS
            $detalle_mapa_con_nombres[$nombre] = array_values($detalle_mapa[$code]);
        }
    }
    unset($entidad); // Romper la referencia

    // 4. DEVOLVER RESPUESTA FINAL
    echo json_encode([
        'data' => array_values($resumen_por_entidad),
        'detalle_mapa' => $detalle_mapa_con_nombres
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error fatal en reporte_glosas_sin_radicar.php: ".$e->getMessage());
}
?>