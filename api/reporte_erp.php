<?php
// api/reporte_erp.php (Versión Final - Lógica Original con Mejoras)
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

    // 2. CONSULTA ÚNICA CON LEFT JOIN (Lógica original que VFP puede manejar)
    $sql = "rec.gr_docn, rec.tercero, rec.freg, rec.fecha_rep, rec.quien, rec.vr_tref, rec.vr_tace, rec.vr_tcon, rec.observac, red.fc_serie, red.fc_docn, red.estatus1 FROM gema10.d/salud/datos/glo_rec rec LEFT JOIN gema10.d/salud/datos/glo_red red ON rec.gr_docn = red.gr_docn WHERE BETWEEN(rec.freg, {$fecha_inicio_vfp}, {$fecha_fin_vfp}) AND NOT ('ANULADO' $ UPPER(rec.observac) OR 'ANULADA' $ UPPER(rec.observac)) AND !EMPTY(rec.fecha_rep)";
    $raw_results = queryApiGema($sql);

    if (empty($raw_results)) {
        echo json_encode(['data' => [], 'detalle_mapa' => new stdClass()]);
        exit;
    }

    // 3. PROCESAMIENTO EN PHP (Lógica original para de-duplicar y contar)
    $cuentas_de_cobro = [];
    $seen_invoices = [];

    // --- Primera pasada: Agrupar por cuenta de cobro y contar facturas únicas ---
    foreach ($raw_results as $row) {
        $gr_docn = trim($row['gr_docn']);
        // [MEJORA] Ignorar registros sin un gr_docn válido
        if (empty($gr_docn)) {
            continue;
        }

        if (!isset($cuentas_de_cobro[$gr_docn])) {
            $cuentas_de_cobro[$gr_docn] = [
                'gr_docn' => $gr_docn,
                'responsable' => trim($row['quien']) ?: 'SIN ASIGNAR',
                'tercero' => trim($row['tercero']),
                'freg' => trim($row['freg']),
                'fecha_rep' => trim($row['fecha_rep']),
                'vr_tace' => floatval($row['vr_tace']),
                'vr_tref' => floatval($row['vr_tref']),
                'vr_tcon' => floatval($row['vr_tcon']),
                'observac' => trim($row['observac']),
                'facturas_por_cuenta' => 0
            ];
        }

        if ($row['fc_serie'] !== null) {
            $invoice_key = trim($row['fc_serie']) . trim($row['fc_docn']) . trim($row['estatus1']);
            $full_key = $gr_docn . '|' . $invoice_key;
            if (!isset($seen_invoices[$full_key])) {
                $seen_invoices[$full_key] = true;
                $cuentas_de_cobro[$gr_docn]['facturas_por_cuenta']++;
            }
        }
    }

    // --- Obtener nombres reales de los responsables (quien) ---
    $quien_aliases = array_unique(array_column($cuentas_de_cobro, 'responsable')); // 'responsable' holds the 'quien' alias
    $mapa_quien_nombres = [];
    if (!empty($quien_aliases)) {
        // VFP IN clause for strings needs single quotes
        $in_values_quien = "'" . implode("','", array_map('trim', $quien_aliases)) . "'";
        $sql_quien_names = "id, nombre FROM gema10.d/dgen/datos/maopera2 WHERE id IN ({$in_values_quien})";
        $quien_data = queryApiGema($sql_quien_names);
        foreach ($quien_data as $operador) {
            $mapa_quien_nombres[trim($operador['id'])] = trim($operador['nombre']);
        }
    }

    // --- Obtener nombres de terceros ---
    $codigos_terceros = array_unique(array_column($cuentas_de_cobro, 'tercero'));
    $mapa_nombres = [];
    if (!empty($codigos_terceros)) {
        $where_conditions = [];
        foreach ($codigos_terceros as $codigo) { if (is_numeric(trim($codigo))) { $where_conditions[] = "codigo = ".trim($codigo); } }
        if (!empty($where_conditions)) {
            $sql_terceros = "codigo, nombre FROM gema10.d/dgen/datos/terceros WHERE ".implode(' OR ', $where_conditions);
            $terceros_data = queryApiGema($sql_terceros);
            foreach ($terceros_data as $tercero) { $mapa_nombres[trim($tercero['codigo'])] = trim($tercero['nombre']); }
        }
    }

    // --- Segunda pasada: Agrupar por responsable para la respuesta final ---
    $resumen_responsables = [];
    $detalle_mapa = [];

    foreach ($cuentas_de_cobro as $doc) {
        $responsable_alias = $doc['responsable']; // Obtener el alias
        // Usar el nombre real si se encuentra, de lo contrario, mantener el alias
        $responsable = $mapa_quien_nombres[$responsable_alias] ?? $responsable_alias;
        $doc['responsable'] = $responsable; // Actualizar el array doc para detalle_mapa

        $doc['tercero_nombre'] = $mapa_nombres[$doc['tercero']] ?? $doc['tercero'];

        if (!isset($detalle_mapa[$responsable])) { $detalle_mapa[$responsable] = []; }
        $detalle_mapa[$responsable][] = $doc;

        if (!isset($resumen_responsables[$responsable])) {
            $resumen_responsables[$responsable] = [
                'responsable' => $responsable, 'total_documentos' => 0,
                'total_aceptado' => 0.0, 'total_refutado' => 0.0, 'total_conciliado' => 0.0,
                'total_facturas_radicadas' => 0
            ];
        }
        $resumen_responsables[$responsable]['total_documentos']++;
        $resumen_responsables[$responsable]['total_facturas_radicadas'] += $doc['facturas_por_cuenta'];
        $resumen_responsables[$responsable]['total_aceptado'] += $doc['vr_tace'];
        $resumen_responsables[$responsable]['total_refutado'] += $doc['vr_tref'];
        $resumen_responsables[$responsable]['total_conciliado'] += $doc['vr_tcon'];
    }

    // 4. DEVOLVER RESPUESTA FINAL
    echo json_encode([
        'data' => array_values($resumen_responsables),
        'detalle_mapa' => $detalle_mapa
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error fatal en reporte_erp.php: ".$e->getMessage());
    echo json_encode(['error' => 'Ocurrió un error en el servidor.', 'details' => $e->getMessage()]);
}
?>