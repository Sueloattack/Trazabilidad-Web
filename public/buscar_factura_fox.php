<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/conectFox.php'; // Asegúrate que la ruta es correcta

header('Content-Type: application/json');
define('LOG_FILE', __DIR__ . '/errores_php.log'); // Asegúrate que este archivo es escribible por el servidor web

function log_this_error(string $message): void {
    // Añadir un prefijo de ID de petición simple para agrupar logs de una misma ejecución podría ser útil para depuración compleja
    // static $requestId = null;
    // if ($requestId === null) {
    //     $requestId = uniqid('req_', true);
    // }
    // error_log(date('[Y-m-d H:i:s] ') . "[{$requestId}] " . $message . PHP_EOL, 3, LOG_FILE);
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, LOG_FILE);
}

function send_json_response(array $data): void {
    echo json_encode($data);
    exit;
}

log_this_error("--- INICIO DE PETICIÓN ---"); // Log para marcar el inicio de una nueva petición

$response = ['success' => false, 'message' => 'Error desconocido en el servidor.'];

// Validación de entrada
if (!isset($_POST['serie']) || !isset($_POST['numero_factura'])) {
    $response['message'] = 'Faltan parámetros: serie o número de factura.';
    log_this_error("ERROR_VALIDACION: " . $response['message'] . " - POST Data: " . print_r($_POST, true));
    send_json_response($response);
}

$serie = strtoupper(trim((string)$_POST['serie']));
$numero_factura_str = trim((string)$_POST['numero_factura']);
log_this_error("PARAMETROS_RECIBIDOS: Serie='{$serie}', Numero_Factura_Str='{$numero_factura_str}'");


if ($serie === '' || $numero_factura_str === '' || !is_numeric($numero_factura_str) || (int)$numero_factura_str <= 0) {
    $response['message'] = 'Valores inválidos para serie o número de factura.';
    log_this_error("ERROR_VALIDACION: " . $response['message'] . " - Serie='{$serie}', Numero_Factura_Str='{$numero_factura_str}'");
    send_json_response($response);
}
$numero_factura = (int)$numero_factura_str;

try {
    $pdoFox = ConnectionFox::con();
    log_this_error("CONEXION_FOXPRO: Establecida correctamente.");

    // 1. Consultar GLO_CAB
    $sql_glocab = "SELECT tercero, tipo, nom_tipo, gl_docn, gl_fecha, tot_glosa, acep_ips 
                   FROM glo_cab 
                   WHERE fc_serie = ? AND fc_docn = ?";
    log_this_error("SQL_GLOCAB: {$sql_glocab} con Serie='{$serie}', Numero_Factura={$numero_factura}");

    $stmt_glocab = $pdoFox->prepare($sql_glocab);
    $stmt_glocab->bindValue(1, $serie, PDO::PARAM_STR);
    $stmt_glocab->bindValue(2, $numero_factura, PDO::PARAM_INT);
    $stmt_glocab->execute();
    $glocab_results = $stmt_glocab->fetchAll(PDO::FETCH_ASSOC);
    $stmt_glocab->closeCursor();
    log_this_error("RESULTADOS_GLOCAB: " . (empty($glocab_results) ? 'Vacío' : count($glocab_results) . ' registros encontrados.'));


    if (empty($glocab_results)) {
        log_this_error("INFO: Factura no encontrada en GLO_CAB. Serie='{$serie}', Numero={$numero_factura}. Respondiendo NU.");
        send_json_response([
            'success' => true,
            'datos' => [
                'nit' => '', 
                'tipo_glosa' => 'NU',
                'descripcion_glosa' => 'No registrada', 
                'estado_consolidado' => 'NU'
            ]
        ]);
    }

    usort($glocab_results, function ($a, $b) {
        $fecha_a = isset($a['gl_fecha']) && $a['gl_fecha'] ? strtotime((string)$a['gl_fecha']) : 0;
        $fecha_b = isset($b['gl_fecha']) && $b['gl_fecha'] ? strtotime((string)$b['gl_fecha']) : 0;
        if ($fecha_a == $fecha_b) return 0;
        return ($fecha_a < $fecha_b) ? 1 : -1;
    });
    $glocab_actual = $glocab_results[0];
    log_this_error("GLOCAB_ACTUAL (registro más reciente): " . print_r($glocab_actual, true));


    $nit_cab = trim((string)($glocab_actual['tercero'] ?? ''));
    $tipo_glosa_general_cab = trim((string)($glocab_actual['tipo'] ?? 'NU'));
    $descripcion_glosa_para_respuesta = trim((string)($glocab_actual['nom_tipo'] ?? 'N/A'));
    
    $gl_docn_actual = $glocab_actual['gl_docn'] ?? null;
    if ($gl_docn_actual !== null) {
        $gl_docn_actual = (int)$gl_docn_actual;
    }
    log_this_error("DATOS_DE_GLOCAB_ACTUAL: NIT='{$nit_cab}', TipoGeneral='{$tipo_glosa_general_cab}', GlDocn={$gl_docn_actual}");


    $tot_glosa_para_comparacion_ai = isset($glocab_actual['tot_glosa']) ? (float)$glocab_actual['tot_glosa'] : null;
    $acep_ips_para_comparacion_ai = isset($glocab_actual['acep_ips']) ? (float)$glocab_actual['acep_ips'] : null;
    log_this_error("VALORES_PARA_AI_DESDE_GLOCAB (¡RECONFIRMAR FUENTE!): tot_glosa=" . var_export($tot_glosa_para_comparacion_ai, true) . ", acep_ips=" . var_export($acep_ips_para_comparacion_ai, true));


    if ($gl_docn_actual === null) {
        log_this_error("INFO: GLO_CAB encontrado, pero GL_DOCN es nulo. Serie='{$serie}', Numero={$numero_factura}. Respondiendo NU para estado consolidado.");
        send_json_response([
            'success' => true,
            'datos' => [
                'nit' => $nit_cab,
                'tipo_glosa' => $tipo_glosa_general_cab, 
                'descripcion_glosa' => $descripcion_glosa_para_respuesta,
                'estado_consolidado' => 'NU'
            ]
        ]);
    }

    // 2. Consultar GLO_DET
    $sql_glodet = "SELECT estatus1 FROM glo_det WHERE gl_docn = ?";
    log_this_error("SQL_GLODET: {$sql_glodet} con GlDocn={$gl_docn_actual}");

    $stmt_glodet = $pdoFox->prepare($sql_glodet);
    $stmt_glodet->bindValue(1, $gl_docn_actual, PDO::PARAM_INT);
    $stmt_glodet->execute();
    $glosas_detalle_estatus1_list = $stmt_glodet->fetchAll(PDO::FETCH_COLUMN, 0); 
    $stmt_glodet->closeCursor();
    log_this_error("RESULTADOS_GLODET (estatus1): " . (empty($glosas_detalle_estatus1_list) ? 'Vacío' : implode(', ', $glosas_detalle_estatus1_list)));


    $estado_consolidado_final = 'NU';
    $tiene_glosa_AI_en_detalle = false;
    
    if (!empty($glosas_detalle_estatus1_list)) {
        $estatus_en_detalle_unicos = array_unique(array_map('trim', $glosas_detalle_estatus1_list));
        log_this_error("ESTATUS_UNICOS_GLODET: " . implode(', ', $estatus_en_detalle_unicos));
        
        if (in_array('AI', $estatus_en_detalle_unicos, true)) {
            $tiene_glosa_AI_en_detalle = true;
        }
        log_this_error("\$tiene_glosa_AI_en_detalle: " . ($tiene_glosa_AI_en_detalle ? 'SÍ' : 'NO'));


        if (count(array_intersect(['CO', 'R3'], $estatus_en_detalle_unicos)) > 0) {
            $estado_consolidado_final = 'CO';
        } elseif (count(array_intersect(['C2', 'R2'], $estatus_en_detalle_unicos)) > 0) {
            $estado_consolidado_final = 'R3';
        } elseif (count(array_intersect(['NU', 'C1'], $estatus_en_detalle_unicos)) > 0) {
            $estado_consolidado_final = 'R2';
        }
        log_this_error("ESTADO_CONSOLIDADO (después de lógica GLO_DET): {$estado_consolidado_final}");

    } else { 
        log_this_error("INFO: No hay registros en GLO_DET para GlDocn={$gl_docn_actual}.");
        if ($tipo_glosa_general_cab !== 'NU') { 
            $estado_consolidado_final = 'R2';
            log_this_error("INFO: Tipo general de GLO_CAB no es NU, GLO_DET vacío. Estado consolidado fijado a R2 (default).");
        } else {
            log_this_error("INFO: Tipo general de GLO_CAB es NU y GLO_DET vacío. Estado consolidado se mantiene {$estado_consolidado_final}.");
        }
    }


    // 3. Verificación ADICIONAL para AI si se encontró en GLO_DET
    if ($tiene_glosa_AI_en_detalle) {
        log_this_error("VERIFICACION_AI: Detectado AI en detalle. Verificando si está cobrada.");
        log_this_error("VERIFICACION_AI: Usando tot_glosa=" . var_export($tot_glosa_para_comparacion_ai, true) . " y acep_ips=" . var_export($acep_ips_para_comparacion_ai, true) . " (¡RECONFIRMAR FUENTE DE ESTOS VALORES!)");

        if ($tot_glosa_para_comparacion_ai !== null && 
            $acep_ips_para_comparacion_ai !== null && 
            $tot_glosa_para_comparacion_ai == $acep_ips_para_comparacion_ai) {
            
            log_this_error("VERIFICACION_AI: CONDICIÓN COBRADA CUMPLIDA. Enviando respuesta factura_cobrada.");
            send_json_response([
                'success' => true,
                'factura_cobrada' => true,
                'message' => 'Factura totalmente cobrada (AI detectado y totales coinciden).'
            ]);
        } else {
            log_this_error("VERIFICACION_AI: CONDICIÓN COBRADA NO CUMPLIDA. Totales no son iguales, o uno es nulo, o ambos son nulos.");
        }
    }

    // 4. Respuesta final normal
    log_this_error("RESPUESTA_FINAL_NORMAL: Enviando datos. NIT='{$nit_cab}', TipoGlosaGeneral='{$tipo_glosa_general_cab}', DescripcionRespuesta='{$descripcion_glosa_para_respuesta}', EstadoConsolidado='{$estado_consolidado_final}'");
    send_json_response([
        'success' => true,
        'message' => 'Datos de la factura encontrados.',
        'datos' => [
            'nit' => $nit_cab,
            'tipo_glosa' => $tipo_glosa_general_cab, 
            'descripcion_glosa' => $descripcion_glosa_para_respuesta, 
            'estado_consolidado' => $estado_consolidado_final
        ]
    ]);

} catch (PDOException $e) {
    $currentStatementInfo = "";
    if (isset($stmt_glocab) && $stmt_glocab instanceof PDOStatement && isset($stmt_glocab->errorInfo()[1]) && $stmt_glocab->errorInfo()[1] !== null) {
         $errorInfo = $stmt_glocab->errorInfo();
         $currentStatementInfo = " Error en GLO_CAB. SQLState: {$errorInfo[0]} Error Code: {$errorInfo[1]} Message: {$errorInfo[2]}";
    } elseif (isset($stmt_glodet) && $stmt_glodet instanceof PDOStatement && isset($stmt_glodet->errorInfo()[1]) && $stmt_glodet->errorInfo()[1] !== null) {
         $errorInfo = $stmt_glodet->errorInfo();
         $currentStatementInfo = " Error en GLO_DET. SQLState: {$errorInfo[0]} Error Code: {$errorInfo[1]} Message: {$errorInfo[2]}";
    }
    
    $errorMessage = 'PDOException: ' . $e->getMessage() . $currentStatementInfo;
    log_this_error("ERROR_PDO: " . $errorMessage . " - Trace: " . $e->getTraceAsString());
    $response['message'] = 'Error al procesar la solicitud (DB). Consulte el log del servidor.'; 
    send_json_response($response);
} catch (Exception $e) {
    $errorMessage = 'Exception: ' . $e->getMessage();
    log_this_error("ERROR_GENERAL: " . $errorMessage . " - Trace: " . $e->getTraceAsString());
    $response['message'] = 'Error interno del servidor. Consulte el log del servidor.';
    send_json_response($response);
}

log_this_error("--- FIN DE PETICIÓN (inesperado, si se alcanza este punto) ---");
echo json_encode($response); 
?>