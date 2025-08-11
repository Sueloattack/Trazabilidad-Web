<?php
// api/reporte_ingreso.php (Versión 7.0 - Estrategia de "Una Sola Llamada")

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
ini_set('max_execution_time', 180); // 3 minutos de tiempo de espera por seguridad

require_once __DIR__ . '/../config/MysqlConnection.php';

define('API_BASE_URL', 'https://asotrauma.ngrok.app/api-busqueda-gema/public/api');

/**
 * Realiza una petición GET a la API de búsqueda de GEMA.
 *
 * @param string $sql_query La consulta SQL de FoxPro a ejecutar.
 * @return array Los datos de la respuesta.
 * @throws Exception Si la llamada a la API falla o la respuesta no es válida.
 */
function queryApiGema($sql_query) {
    // Para depuración: registrar la consulta exacta que se va a ejecutar
    error_log("[API GEMA] Ejecutando consulta: " . $sql_query);
    
    $encoded_query = urlencode($sql_query);
    $url = API_BASE_URL . "/select/?query=" . $encoded_query;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response_str = curl_exec($ch);
    
    if (curl_errno($ch)) { throw new Exception("Error de cURL: " . curl_error($ch)); }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200) { throw new Exception("Error API GEMA. Código: {$http_code}. Respuesta: " . $response_str); }
    
    $response = json_decode($response_str, true);
    if (json_last_error() !== JSON_ERROR_NONE) { throw new Exception("Respuesta API no es JSON válido."); }
    if (empty($response['status']) || $response['status'] !== 'success' || !isset($response['data'])) { throw new Exception("API devolvió error: " . ($response['message'] ?? 'Formato inesperado.')); }

    return $response['data'];
}

try {
    // --- PASO 1: PARÁMETROS ---
    $pdoMysql = MysqlConnection::getInstance();
    $fecha_inicio = !empty($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
    $fecha_fin = !empty($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-t');

    // --- PASO 2: CONSTRUIR Y EJECUTAR LA ÚNICA CONSULTA A LA API ---
    $fecha_inicio_fox = "{^{$fecha_inicio}}";
    $fecha_fin_fox = "{^{$fecha_fin}}";
    
    // Construcción de la parte WHERE con OR
    $estatus_validos = ['NU', 'R2', 'R3', 'R4', 'AE'];
    $estatus_or_conditions = [];
    foreach($estatus_validos as $estatus) {
        $estatus_or_conditions[] = "d.estatus1 = '{$estatus}'";
    }
    $estatus_where_clause = "(" . implode(' OR ', $estatus_or_conditions) . ")";

    // La consulta completa y unificada
    $sql_gema = "
        d.gl_docn, d.quien, d.estatus1, c.fc_serie, c.fc_docn, c.tercero
        FROM 
            gema10.d/salud/datos/glo_det d 
        JOIN 
            gema10.d/salud/datos/glo_cab c ON d.gl_docn = c.gl_docn 
        WHERE 
            {$estatus_where_clause} 
            AND d.freg BETWEEN {$fecha_inicio_fox} AND {$fecha_fin_fox}
    ";
    
    // Llamada única a la API para obtener el conjunto de datos ya filtrado y unido
    $itemsCandidatosVFP_raw = queryApiGema($sql_gema);

    if (empty($itemsCandidatosVFP_raw)) {
        echo json_encode(['data' => [], 'inconsistencias' => []]);
        exit;
    }
    
    // --- PASO 3: LIMPIEZA Y ESTANDARIZACIÓN (AHORA ES MUCHO MÁS SIMPLE) ---
    $itemsCandidatosVFP = [];
    foreach ($itemsCandidatosVFP_raw as $itemCrudo) {
        $itemsCandidatosVFP[] = [
            'serie'   => strtoupper(trim($itemCrudo['fc_serie'])),
            'docn'    => strtoupper(trim($itemCrudo['fc_docn'])),
            'tercero' => strtoupper(trim($itemCrudo['tercero'])),
            'quien'   => strtoupper(trim($itemCrudo['quien'])),
            'estatus1'  => strtolower(trim($itemCrudo['estatus1']))
        ];
    }

    // --- A PARTIR DE AQUÍ, EL CÓDIGO DE CRUCE CON MYSQL ES IDÉNTICO ---
    
    // --- PASO 4: PREPARACIÓN DE DATOS PARA EL CRUCE CON MYSQL ---
    $idsCompuestosUnicos = [];
    foreach ($itemsCandidatosVFP as $item) { $idsCompuestosUnicos[$item['serie'].'-'.$item['docn'].'-'.$item['tercero']] = true; }
    $idsParaMysql = array_keys($idsCompuestosUnicos);
    if(empty($idsParaMysql)){ echo json_encode(['data' => [], 'inconsistencias' => []]); exit; }
    $placeholders = implode(',', array_fill(0, count($idsParaMysql), '?'));
    
    // --- PASO 5: CONSULTA Y VALIDACIÓN EN MYSQL (MODIFICADA) ---
    // <-- AÑADIMOS eg.activo A LA CONSULTA -->
    $sqlMysql = "
        SELECT 
            UPPER(CONCAT_WS('-', fg.serie, fg.docn, fg.nit_tercero)) as id_compuesto, 
            fg.vr_glosa, 
            UPPER(TRIM(eg.usu_acepta)) as usu_acepta,
            eg.activo
        FROM 
            factura_glosas AS fg 
        JOIN 
            envio_glosas AS eg ON fg.id = eg.id_facturaglosas
        WHERE 
            UPPER(CONCAT_WS('-', fg.serie, fg.docn, fg.nit_tercero)) IN ($placeholders)
    ";
    
    $stmtMysql = $pdoMysql->prepare($sqlMysql);
    $stmtMysql->execute($idsParaMysql);
    $datosValidadosMysql = $stmtMysql->fetchAll(PDO::FETCH_ASSOC);

    // --- PASO 6: CREACIÓN DE MAPA DE VALIDACIÓN (sin cambios) ---
    $mapaValidacionMysql = [];
    foreach ($datosValidadosMysql as $glosa) { $mapaValidacionMysql[$glosa['id_compuesto']] = $glosa; }
    
    // --- PASO 7: AGREGACIÓN FINAL DE RESULTADOS (LÓGICA MEJORADA) ---
    $resultadosAgregados = []; $inconsistencias = []; $facturasContadasParaTotales = [];
    
    foreach ($itemsCandidatosVFP as $itemGema) {
        $idCompuesto = $itemGema['serie'].'-'.$itemGema['docn'].'-'.$itemGema['tercero'];
        
        // <-- LÓGICA REFINADA PARA CAPTURAR LOS TIPOS DE INCONSISTENCIA -->
        if (!isset($mapaValidacionMysql[$idCompuesto])) {
            // CASO 1: No encontrada
            $inconsistencias[$idCompuesto] = ['id' => $idCompuesto, 'motivo' => 'Glosa no encontrada en Traza (MySQL).'];

        } else {
            $datosMysql = $mapaValidacionMysql[$idCompuesto];
            
            if ($datosMysql['activo'] == 0) {
                // CASO 2: Encontrada pero inactiva
                $inconsistencias[$idCompuesto] = ['id' => $idCompuesto, 'motivo' => 'Glosa INACTIVA en Traza (MySQL).'];
            } else {
                // CASO 3: VÁLIDA (encontrada y activa) -> La lógica de agregación que ya tenías
                $responsable = !empty($itemGema['quien']) ? $itemGema['quien'] : $datosMysql['usu_acepta'];
                $responsable = !empty($responsable) ? $responsable : '(Sin Asignar)';
                $tipoItem = $itemGema['estatus1'];
                if (!isset($resultadosAgregados[$responsable])) { $resultadosAgregados[$responsable] = ['responsable' => $responsable, 'cantidad_glosas_ingresadas' => 0, 'valor_total_glosas' => 0.0, 'desglose_ratificacion' => ['nu'=>['cantidad'=>0,'valor'=>0.0],'r2'=>['cantidad'=>0,'valor'=>0.0],'r3'=>['cantidad'=>0,'valor'=>0.0],'r4'=>['cantidad'=>0,'valor'=>0.0],'ae'=>['cantidad'=>0,'valor'=>0.0]]]; }
                $valorFacturaCompleta_MySQL = (float)$datosMysql['vr_glosa'];
                $claveRastreoTotal = $responsable.'::'.$idCompuesto;
                if (!isset($facturasContadasParaTotales[$claveRastreoTotal])) {
                    $resultadosAgregados[$responsable]['cantidad_glosas_ingresadas']++;
                    $resultadosAgregados[$responsable]['valor_total_glosas'] += $valorFacturaCompleta_MySQL;
                    $facturasContadasParaTotales[$claveRastreoTotal] = true;
                }
                if (array_key_exists($tipoItem, $resultadosAgregados[$responsable]['desglose_ratificacion'])) {
                    $resultadosAgregados[$responsable]['desglose_ratificacion'][$tipoItem]['cantidad']++;
                    $resultadosAgregados[$responsable]['desglose_ratificacion'][$tipoItem]['valor'] += $valorFacturaCompleta_MySQL;
                }
            }
        }
    }
    
    // PASO 8: Formato Final
    $dias_en_rango = (new DateTime($fecha_fin))->diff(new DateTime($fecha_inicio))->days + 1;
    $periodo = ($dias_en_rango >= 30) ? 'mensual' : 'diario';
    $divisor = ($dias_en_rango >= 30) ? $dias_en_rango / 30.0 : (float)$dias_en_rango;
    $resultados_finales = [];
    foreach ($resultadosAgregados as $datosResponsable) {
        $promedio_cantidad = ($divisor > 0) ? ($datosResponsable['cantidad_glosas_ingresadas'] / $divisor) : 0;
        $promedio_valor = ($divisor > 0) ? ($datosResponsable['valor_total_glosas'] / $divisor) : 0;
        foreach ($datosResponsable['desglose_ratificacion'] as $key => &$value) { $value['valor'] = '$'.number_format($value['valor'],0,',','.'); }
        unset($value);
        $resultados_finales[] = ['responsable' => $datosResponsable['responsable'], 'cantidad_glosas_ingresadas' => $datosResponsable['cantidad_glosas_ingresadas'], 'valor_total_glosas' => '$'.number_format($datosResponsable['valor_total_glosas'],0,',','.'), 'promedios' => ['periodo' => $periodo, 'promedio_cantidad' => round($promedio_cantidad,2), 'promedio_valor' => '$'.number_format($promedio_valor,0,',','.'),], 'desglose_ratificacion' => $datosResponsable['desglose_ratificacion']];
    }
    usort($resultados_finales, function($a, $b) { return $b['cantidad_glosas_ingresadas'] <=> $a['cantidad_glosas_ingresadas']; });
    
    $respuestaFinal = ['data' => $resultados_finales, 'inconsistencias' => array_values($inconsistencias)];
    echo json_encode($respuestaFinal);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error fatal en reporte_ingreso.php: ".$e->getMessage());
    echo json_encode(['error' => 'Ocurrió un error en el servidor.', 'details' => $e->getMessage()]);
}
?>