<?php
// ... (Encabezados e includes siguen igual)
require_once __DIR__ . '/../config/conectFox.php'; 
require_once __DIR__ . '/../config/MysqlConnection.php';

try {
    // --- PASO 1: CONEXIÓN Y FECHAS (Sin cambios) ---
    // ...
    $pdoFox = ConnectionFox::con();
    $pdoMysql = MysqlConnection::getInstance();
    $fecha_inicio = !empty($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
    $fecha_fin = !empty($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-t');


    // --- PASO 2: EXTRACCIÓN DE DATOS "EN CRUDO" DE FOXPRO (Sin cambios en la consulta) ---
    $sqlFox = "
        SELECT 
            c.fc_serie, c.fc_docn, c.tercero,
            d.quien, d.estatus1, d.freg, d.fecha_gl
        FROM 
            glo_det d
        JOIN 
            glo_cab c ON d.gl_docn = c.gl_docn
    ";
    
    $stmtFox = $pdoFox->prepare($sqlFox);
    $stmtFox->execute();


    // =================================================================
    // PASO 3: FILTRADO Y LIMPIEZA EFICIENTE (LA CORRECCIÓN PRINCIPAL)
    // =================================================================
    
    $fecha_inicio_obj = new DateTime($fecha_inicio);
    $fecha_fin_obj = new DateTime($fecha_fin . ' 23:59:59');
    
    $itemsCandidatosVFP = []; // Aquí guardaremos los datos que SÍ necesitamos.
    
    // *** CAMBIO CLAVE: Bucle 'while' con 'fetch()' en lugar de 'fetchAll()' ***
    // Esto procesa una fila a la vez, sin agotar la memoria.
    while ($itemCrudo = $stmtFox->fetch(PDO::FETCH_ASSOC)) {
        
        // El resto de la lógica de limpieza y filtrado va DENTRO del bucle.
        
        // --- 3.1: Filtrado por Fecha ---
        $fecha_a_usar_str = (is_null($itemCrudo['freg']) || trim($itemCrudo['freg']) === '') ? $itemCrudo['fecha_gl'] : $itemCrudo['freg'];
        if (is_null($fecha_a_usar_str) || trim($fecha_a_usar_str) === '') {
            continue;
        }
        
        try {
            $fecha_item_obj = new DateTime($fecha_a_usar_str);
            if (!($fecha_item_obj >= $fecha_inicio_obj && $fecha_item_obj <= $fecha_fin_obj)) {
                continue;
            }
        } catch (Exception $e) {
            continue;
        }

        // --- 3.2: Limpieza de Datos y Construcción del Array ---
        // Solo guardamos en memoria los registros que pasan el filtro,
        // por lo que el array `$itemsCandidatosVFP` se mantendrá pequeño.
        $itemsCandidatosVFP[] = [
            'serie'    => strtoupper(trim($itemCrudo['fc_serie'])),
            'docn'     => strtoupper(trim($itemCrudo['fc_docn'])),
            'tercero'  => strtoupper(trim($itemCrudo['tercero'])),
            'quien'    => strtoupper(trim($itemCrudo['quien'])),
            'c_item'   => strtoupper(trim($itemCrudo['estatus1']))
        ];
    }
    // Una vez terminado el bucle, la conexión a FoxPro puede liberar recursos.

    if (empty($itemsCandidatosVFP)) {
        echo json_encode(['data' => [], 'inconsistencias' => []]);
        exit;
    }

    // --- PASO 4, 5 Y 6: CONTINÚAN EXACTAMENTE IGUAL ---
    // El resto de tu código ya está preparado para trabajar con el array 
    // `$itemsCandidatosVFP`, que ahora ha sido construido de forma eficiente.
    
    // ... (El código restante, desde la preparación de la consulta a MySQL en adelante, 
    //      es idéntico al de la respuesta anterior)
    
    // ... [Pego el resto del código aquí por completitud]
    
    $idsCompuestosUnicos = [];
    foreach ($itemsCandidatosVFP as $item) {
        $idsCompuestosUnicos[$item['serie'] . '-' . $item['docn'] . '-' . $item['tercero']] = true;
    }
    $idsParaMysql = array_keys($idsCompuestosUnicos);
    $placeholders = implode(',', array_fill(0, count($idsParaMysql), '?'));
    
    $sqlMysql = "
        SELECT
            UPPER(CONCAT_WS('-', fg.serie, fg.docn, fg.nit_tercero)) as id_compuesto, -- <-- CAMBIO REALIZADO
            fg.vr_glosa,
            UPPER(TRIM(eg.usu_acepta)) as usu_acepta
        FROM
            factura_glosas AS fg
        JOIN
            envio_glosas AS eg ON fg.id = eg.id_facturaglosas
        WHERE
            eg.activo = 1 
            AND UPPER(CONCAT_WS('-', fg.serie, fg.docn, fg.nit_tercero)) IN ($placeholders) -- <-- CAMBIO REALIZADO
    ";
    $stmtMysql = $pdoMysql->prepare($sqlMysql);
    $stmtMysql->execute($idsParaMysql);
    $datosValidadosMysql = $stmtMysql->fetchAll(PDO::FETCH_ASSOC);
    $mapaValidacionMysql = [];
    foreach ($datosValidadosMysql as $glosa) {
        $mapaValidacionMysql[$glosa['id_compuesto']] = $glosa;
    }
    
    $resultadosAgregados = [];
    $inconsistencias = [];
    $facturasContadasParaTotales = [];
    
    foreach ($itemsCandidatosVFP as $itemGema) {
        $idCompuesto = $itemGema['serie'] . '-' . $itemGema['docn'] . '-' . $itemGema['tercero'];
        if (isset($mapaValidacionMysql[$idCompuesto])) {
            $datosMysql = $mapaValidacionMysql[$idCompuesto];
            $responsable = !empty($itemGema['quien']) ? $itemGema['quien'] : $datosMysql['usu_acepta'];
            $responsable = !empty($responsable) ? $responsable : '(Sin Asignar)';
            $tipoItem = strtolower($itemGema['c_item']); 
            
            if (!isset($resultadosAgregados[$responsable])) {
                $resultadosAgregados[$responsable] = [
                    'responsable' => $responsable, 'cantidad_glosas_ingresadas' => 0, 'valor_total_glosas' => 0.0,
                    'desglose_ratificacion' => ['nu'=>['cantidad'=>0,'valor'=>0.0],'r2'=>['cantidad'=>0,'valor'=>0.0],'r3'=>['cantidad'=>0,'valor'=>0.0],'co'=>['cantidad'=>0,'valor'=>0.0],'ai'=>['cantidad'=>0,'valor'=>0.0],'c1'=>['cantidad'=>0,'valor'=>0.0],'c2'=>['cantidad'=>0,'valor'=>0.0],'c3'=>['cantidad'=>0,'valor'=>0.0],'r4'=>['cantidad'=>0,'valor'=>0.0]]
                ];
            }
            $valorFacturaCompleta_MySQL = (float)$datosMysql['vr_glosa'];
            $claveRastreoTotal = $responsable . '::' . $idCompuesto;
            if (!isset($facturasContadasParaTotales[$claveRastreoTotal])) {
                $resultadosAgregados[$responsable]['cantidad_glosas_ingresadas']++;
                $resultadosAgregados[$responsable]['valor_total_glosas'] += $valorFacturaCompleta_MySQL;
                $facturasContadasParaTotales[$claveRastreoTotal] = true;
            }
            if (array_key_exists($tipoItem, $resultadosAgregados[$responsable]['desglose_ratificacion'])) {
                $resultadosAgregados[$responsable]['desglose_ratificacion'][$tipoItem]['cantidad']++;
                $resultadosAgregados[$responsable]['desglose_ratificacion'][$tipoItem]['valor'] += $valorFacturaCompleta_MySQL;
            }
        } else {
            $inconsistencias[$idCompuesto] = ['id' => $idCompuesto, 'motivo' => 'No encontrada o inactiva en el sistema de trazabilidad (MySQL).'];
        }
    }
    
    $dias_en_rango = (new DateTime($fecha_fin))->diff(new DateTime($fecha_inicio))->days + 1;
    $periodo = ($dias_en_rango >= 30) ? 'mensual' : 'diario';
    $divisor = ($dias_en_rango >= 30) ? $dias_en_rango / 30.0 : (float)$dias_en_rango;
    $resultados_finales = [];
    foreach ($resultadosAgregados as $datosResponsable) {
        $promedio_cantidad = ($divisor > 0) ? ($datosResponsable['cantidad_glosas_ingresadas'] / $divisor) : 0;
        $promedio_valor = ($divisor > 0) ? ($datosResponsable['valor_total_glosas'] / $divisor) : 0;
        foreach ($datosResponsable['desglose_ratificacion'] as $key => $value) {
            $datosResponsable['desglose_ratificacion'][$key]['valor'] = '$' . number_format($value['valor'], 0, ',', '.');
        }
        $resultados_finales[] = ['responsable' => $datosResponsable['responsable'],'cantidad_glosas_ingresadas' => $datosResponsable['cantidad_glosas_ingresadas'],'valor_total_glosas' => '$' . number_format($datosResponsable['valor_total_glosas'], 0, ',', '.'),'promedios' => ['periodo' => $periodo,'promedio_cantidad' => round($promedio_cantidad, 2),'promedio_valor' => '$' . number_format($promedio_valor, 0, ',', '.'),],'desglose_ratificacion' => $datosResponsable['desglose_ratificacion']];
    }
    usort($resultados_finales, function($a, $b) { return $b['cantidad_glosas_ingresadas'] <=> $a['cantidad_glosas_ingresadas']; });
    $respuestaFinal = ['data' => $resultados_finales, 'inconsistencias' => array_values($inconsistencias)];
    echo json_encode($respuestaFinal);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Error de BD en reporte_ingreso.php: " . $e->getMessage());
    echo json_encode(['error' => 'Ocurrió un error al consultar la base de datos.', 'details' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error general en reporte_ingreso.php: " . $e->getMessage());
    echo json_encode(['error' => 'Ocurrió un error general en el servidor.', 'details' => $e->getMessage()]);
}
?>