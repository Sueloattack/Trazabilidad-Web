<?php
// api/reporte_analistas.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config/MysqlConnection.php';

// --- Gestión de Fechas (Idéntica al otro reporte) ---
if (isset($_GET['fecha_inicio']) && !empty($_GET['fecha_inicio']) && isset($_GET['fecha_fin']) && !empty($_GET['fecha_fin'])) {
    $fecha_inicio = $_GET['fecha_inicio'];
    $fecha_fin = $_GET['fecha_fin'];
} else {
    $fecha_inicio = date('Y-m-01');
    $fecha_fin = date('Y-m-t');
}

// --- Cálculo del Divisor para Promedios (Idéntico) ---
try {
    $fecha_inicio_obj = new DateTime($fecha_inicio);
    $fecha_fin_obj = new DateTime($fecha_fin);
    $dias_en_rango = $fecha_fin_obj->diff($fecha_inicio_obj)->days + 1;
    $periodo = 'diario';
    $divisor = (float)$dias_en_rango;

    if ($dias_en_rango >= 30) {
        $periodo = 'mensual';
        $divisor = $dias_en_rango / 30.0;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato de fecha inválido. Use AAAA-MM-DD.']);
    exit;
}

// --- Conexión a BD y NUEVA Consulta SQL para Analistas ---
try {
    $pdo = MysqlConnection::getInstance();

    // Consulta específica para la productividad de las analistas
    $sql = "
        SELECT
            eg.auditora,
            COUNT(eg.id) AS cantidad_glosas_ingresadas,
            COALESCE(SUM(fg.vr_glosa), 0) AS valor_total_glosas,
            COUNT(CASE WHEN fg.ratificacion = 0 THEN 1 END) AS cant_nu,
            COALESCE(SUM(CASE WHEN fg.ratificacion = 0 THEN fg.vr_glosa END), 0) AS valor_nu,
            COUNT(CASE WHEN fg.ratificacion = 1 THEN 1 END) AS cant_r2,
            COALESCE(SUM(CASE WHEN fg.ratificacion = 1 THEN fg.vr_glosa END), 0) AS valor_r2,
            COUNT(CASE WHEN fg.ratificacion = 2 THEN 1 END) AS cant_r3,
            COALESCE(SUM(CASE WHEN fg.ratificacion = 2 THEN fg.vr_glosa END), 0) AS valor_r3,
            COUNT(CASE WHEN fg.ratificacion = 3 THEN 1 END) AS cant_co,
            COALESCE(SUM(CASE WHEN fg.ratificacion = 3 THEN fg.vr_glosa END), 0) AS valor_co,
            COUNT(CASE WHEN fg.ratificacion = 4 THEN 1 END) AS cant_ai,
            COALESCE(SUM(CASE WHEN fg.ratificacion = 4 THEN fg.vr_glosa END), 0) AS valor_ai
        FROM
            envio_glosas AS eg
        JOIN

        
            factura_glosas AS fg ON eg.id_facturaglosas = fg.id
        WHERE
            eg.activo = 1
            AND eg.auditora IS NOT NULL AND eg.auditora != '' -- Asegurarse que hay una auditora
            AND fg.erp_directo = 0 -- Solo las que no fueron a ERP directo
            AND DATE(eg.fecha_env_erp) BETWEEN ? AND ?
        GROUP BY
            eg.auditora
        ORDER BY
            cantidad_glosas_ingresadas DESC;
    ";

    $stmt = $pdo->prepare($sql);
    // Solo necesitamos pasar el rango de fechas una vez para esta consulta
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $analistas_data = $stmt->fetchAll();

    // --- Procesamiento y Formateo (Prácticamente idéntico) ---
    $resultados_finales = [];
    $formatCurrency = function($amount) {
        return '$' . number_format($amount, 0, ',', '.');
    };

    foreach ($analistas_data as $analista) {
        $promedio_cantidad = ($divisor > 0) ? ($analista['cantidad_glosas_ingresadas'] / $divisor) : 0;
        $promedio_valor = ($divisor > 0) ? ($analista['valor_total_glosas'] / $divisor) : 0;
        
        $resultados_finales[] = [
            // El frontend recibirá una clave 'responsable' genérica
            'responsable' => $analista['auditora'],
            'cantidad_glosas_ingresadas' => $analista['cantidad_glosas_ingresadas'],
            'valor_total_glosas' => $formatCurrency($analista['valor_total_glosas']),
            'promedios' => [
                'periodo' => $periodo,
                'promedio_cantidad' => round($promedio_cantidad, 2),
                'promedio_valor' => $formatCurrency($promedio_valor)
            ],
            'desglose_ratificacion' => [
                'nu' => ['cantidad' => (int)$analista['cant_nu'], 'valor' => $formatCurrency($analista['valor_nu'])],
                'r2' => ['cantidad' => (int)$analista['cant_r2'], 'valor' => $formatCurrency($analista['valor_r2'])],
                'r3' => ['cantidad' => (int)$analista['cant_r3'], 'valor' => $formatCurrency($analista['valor_r3'])],
                'co' => ['cantidad' => (int)$analista['cant_co'], 'valor' => $formatCurrency($analista['valor_co'])],
                'ai' => ['cantidad' => (int)$analista['cant_ai'], 'valor' => $formatCurrency($analista['valor_ai'])]
            ]
        ];
    }
    
    echo json_encode($resultados_finales);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Error de BD en reporte_analistas.php: " . $e->getMessage());
    echo json_encode(['error' => 'Ocurrio un error al consultar la base de datos para el reporte de analistas.'. $e->getMessage()]);
}
?>