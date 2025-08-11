<?php
// api/reporte_detalles.php (Versión Definitiva "Fetch-by-Date-Only")

ini_set('display_errors', 1); error_reporting(E_ALL); header('Content-Type: application/json');
ini_set('max_execution_time', 180);

require_once __DIR__ . '/../config/MysqlConnection.php';

// Definir la función de ayuda para la API de GEMA
define('API_BASE_URL', 'https://asotrauma.ngrok.app/api-busqueda-gema/public/api');
function queryApiGema($sql_query) {
    // Para depuración
    error_log("[API GEMA - DETALLES] Ejecutando: " . substr($sql_query, 0, 200) . "...");
    
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
    $responsable_solicitado = $_GET['responsable'] ?? null;
    $fecha_inicio = $_GET['fecha_inicio'] ?? null;
    $fecha_fin = $_GET['fecha_fin'] ?? null;
    
    if (!$responsable_solicitado || !$fecha_inicio || !$fecha_fin) {
        throw new Exception("Parámetros requeridos ausentes: responsable, fecha_inicio, fecha_fin.");
    }

    // --- PASO 2: UNA SOLA LLAMADA A LA API CON LA CONSULTA MÁS RÁPIDA ---
    $fecha_inicio_fox = "{^{$fecha_inicio}}";
    $fecha_fin_fox = "{^{$fecha_fin}}";
    
    $sql_gema_unica = "
        d.gl_docn, d.quien, d.estatus1, c.fc_serie, c.fc_docn, c.tercero
        FROM 
            gema10.d/salud/datos/glo_det d 
        JOIN 
            gema10.d/salud/datos/glo_cab c ON d.gl_docn = c.gl_docn 
        WHERE 
            d.freg BETWEEN {$fecha_inicio_fox} AND {$fecha_fin_fox}
    ";
    
    $datos_crudos_de_gema = queryApiGema($sql_gema_unica);

    if (empty($datos_crudos_de_gema)) {
        echo json_encode([]);
        exit;
    }

    // --- PASO 3: FILTRAR Y LIMPIAR EN PHP ---
    $itemsCandidatosVFP = [];
    $estatus_validos = ['NU' => true, 'R2' => true, 'R3' => true, 'R4' => true, 'AE' => true];

    foreach ($datos_crudos_de_gema as $itemCrudo) {
        $estatus_actual = strtoupper(trim($itemCrudo['estatus1']));
        if (isset($estatus_validos[$estatus_actual])) {
            $itemsCandidatosVFP[] = [
                'serie'   => strtoupper(trim($itemCrudo['fc_serie'])),
                'docn'    => strtoupper(trim($itemCrudo['fc_docn'])),
                'tercero' => strtoupper(trim($itemCrudo['tercero'])),
                'quien'   => strtoupper(trim($itemCrudo['quien']))
            ];
        }
    }
    
    if (empty($itemsCandidatosVFP)) {
        echo json_encode([]);
        exit;
    }
    
    // --- PASO 4: CRUZAR CON MYSQL ---
    $idsCompuestos = array_map(function($item){ return $item['serie'].'-'.$item['docn'].'-'.$item['tercero']; }, $itemsCandidatosVFP);
    $idsParaMysql = array_unique($idsCompuestos);
    
    if(empty($idsParaMysql)){ 
        echo json_encode([]);
        exit; 
    }
    $placeholders = implode(',', array_fill(0, count($idsParaMysql), '?'));
    
    $pdoMysql = MysqlConnection::getInstance();
    $sqlMysql = "SELECT UPPER(CONCAT_WS('-', fg.serie, fg.docn, fg.nit_tercero)) as id_compuesto, fg.vr_glosa, UPPER(TRIM(eg.usu_acepta)) as usu_acepta FROM factura_glosas fg JOIN envio_glosas eg ON fg.id = eg.id_facturaglosas WHERE eg.activo = 1 AND UPPER(CONCAT_WS('-', fg.serie, fg.docn, fg.nit_tercero)) IN ($placeholders)";
    $stmtMysql = $pdoMysql->prepare($sqlMysql);
    $stmtMysql->execute($idsParaMysql);
    
    $mapa_mysql = [];
    foreach($stmtMysql->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $mapa_mysql[$row['id_compuesto']] = $row;
    }
    
    // --- PASO 5: FILTRO FINAL POR RESPONSABLE Y CONSTRUCCIÓN DE LA RESPUESTA ---
    $detalles_finales = [];
    $responsable_solicitado_upper = strtoupper($responsable_solicitado);

    foreach ($itemsCandidatosVFP as $itemGema) {
        $idCompuesto = $itemGema['serie'].'-'.$itemGema['docn'].'-'.$itemGema['tercero'];
        
        if (isset($mapa_mysql[$idCompuesto])) {
            $datosMysql = $mapa_mysql[$idCompuesto];
            
            // Lógica de asignación de responsable idéntica al reporte principal
            $responsable_final = !empty($itemGema['quien']) ? $itemGema['quien'] : $datosMysql['usu_acepta'];
            if(empty($responsable_final)) $responsable_final = '(SIN ASIGNAR)';
            
            // Aquí filtramos por el responsable que el usuario clickeó
            if ($responsable_final === $responsable_solicitado_upper) {
                $detalles_finales[] = [
                    "serie"    => $itemGema['serie'],
                    "fc_docn"  => $itemGema['docn'],
                    "tercero"  => $itemGema['tercero'],
                    "vr_glosa" => '$' . number_format($datosMysql['vr_glosa'], 0, ',', '.')
                ];
            }
        }
    }
    
    echo json_encode($detalles_finales);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error fatal en reporte_detalles.php: ".$e->getMessage());
    echo json_encode(['error' => 'Ocurrio un error al obtener los detalles.', 'details' => $e->getMessage()]);
}
?>