<?php
// helpers/reporte_engine.php

// Debe ser llamado después de incluir gema_api_client.php

/**
 * Procesa la petición de reporte genérica y ejecuta la lógica de agregación.
 *
 * @param string $fecha_inicio Fecha de inicio (Y-m-d).
 * @param string $fecha_fin Fecha de fin (Y-m-d).
 * @param array $estatus_validos Lista de estatus a incluir en la consulta (ej: ['NU', 'R2']).
 * @param array $desglose_keys Claves de desglose que se inicializarán (ej: ['nu', 'r2', 'r3']).
 * @return array Los datos finales del reporte listos para ser codificados en JSON.
 */
function generarReporte(
    $fecha_inicio,
    $fecha_fin,
    $estatus_validos,
    $desglose_keys,
    $estatus_aceptado
) {
    // --- PASO 1: CREACIÓN DE LA CONSULTA SQL ---
    
    // Convertir fechas a formato VFP
    $fecha_inicio_fox = "{^{$fecha_inicio}}";
    $fecha_fin_fox = "{^{$fecha_fin}}";
    
    // Crear la cláusula WHERE de estatus
    $estatus_or_conditions = [];
    foreach($estatus_validos as $estatus) {
        $estatus_or_conditions[] = "d.estatus1 = '{$estatus}'";
    }
    $estatus_where_clause = "(" . implode(' OR ', $estatus_or_conditions) . ")";

    $sql_gema = "
        d.gl_docn, d.quien, d.estatus1, c.fc_serie AS serie, c.fc_docn AS docn, c.tercero, d.vr_glosa
        FROM gema10.d/salud/datos/glo_det d
        JOIN gema10.d/salud/datos/glo_cab c ON d.gl_docn = c.gl_docn
        WHERE {$estatus_where_clause}
          AND d.freg BETWEEN {$fecha_inicio_fox} AND {$fecha_fin_fox}
    ";
    
    $itemsCandidatosVFP_raw = queryApiGema($sql_gema);
    if (empty($itemsCandidatosVFP_raw)) {
        return ['data' => [], 'detalle_mapa' => []];
    }
    
    // --- PASO 2: LIMPIEZA Y AGRAGACIÓN ---
    $itemsCandidatosVFP = [];
    foreach ($itemsCandidatosVFP_raw as $itemCrudo) {
        $itemsCandidatosVFP[] = [
            // ... (Lógica de limpieza) ...
            'serie'   => strtoupper(trim($itemCrudo['serie'])),
            'docn'    => strtoupper(trim($itemCrudo['docn'])),
            'tercero' => strtoupper(trim($itemCrudo['tercero'])),
            'quien'   => strtoupper(trim($itemCrudo['quien'])),
            'estatus1'  => strtolower(trim($itemCrudo['estatus1'])),
            'vr_glosa' => (float)trim($itemCrudo['vr_glosa'])
        ];
    }
    
    // --- Obtener nombres reales de los responsables (quien) ---
    $quien_aliases = array_unique(array_column($itemsCandidatosVFP, 'quien'));
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

    $resultadosAgregados = [];
    // $facturasContadasParaTotales = []; // Eliminado para contar ítems en lugar de facturas únicas.
    $mapa_para_detalles_enriquecido = [];
    
    // Estructura base para el desglose (varía entre reportes)
    $desglose_base = array_fill_keys(
        $desglose_keys, 
        ['cantidad' => 0, 'valor' => 0.0, 'facturas' => []] // Añadido 'facturas' para el conteo por estado.
    );

    foreach ($itemsCandidatosVFP as $itemGema) {
        // ... (TODA la lógica de agregación de glosas, totales y mapas) ...
        $idCompuesto = $itemGema['serie'] . '-' . $itemGema['docn'] . '-' . $itemGema['tercero'];
        $responsable_alias = !empty($itemGema['quien']) ? strtoupper(trim($itemGema['quien'])) : '(Sin Asignar)';
        // Usar el nombre real si se encuentra, de lo contrario, mantener el alias
        $responsable_final = $mapa_quien_nombres[$responsable_alias] ?? $responsable_alias;
        $tipoItem = $itemGema['estatus1'];
        $valorItem = $itemGema['vr_glosa'];
        
        // Inicializar la estructura completa si es la primera vez
        if (!isset($resultadosAgregados[$responsable_final])) {
            $resultadosAgregados[$responsable_final] = [
                'responsable' => $responsable_final,
                'cantidad_glosas_ingresadas' => 0,
                'valor_total_glosas' => 0.0,
                'desglose_ratificacion' => $desglose_base, // USAMOS EL BASE
                'total_items' => 0,
                'valor_glosado' => 0.0,
                'valor_aceptado' => 0.0
            ];
        }
        
        // Se elimina el conteo de productividad de aquí. Se calculará al final.

        // Lógica de Desglose y Totales 
        if (array_key_exists($tipoItem, $resultadosAgregados[$responsable_final]['desglose_ratificacion'])) {
            $resultadosAgregados[$responsable_final]['desglose_ratificacion'][$tipoItem]['cantidad']++;
            $resultadosAgregados[$responsable_final]['desglose_ratificacion'][$tipoItem]['valor'] += $valorItem;
            // PASO 2: Recolectar la factura para el conteo por estado.
            $resultadosAgregados[$responsable_final]['desglose_ratificacion'][$tipoItem]['facturas'][] = $idCompuesto;
        }
        
        $resultadosAgregados[$responsable_final]['valor_total_glosas'] += $valorItem; 
        $resultadosAgregados[$responsable_final]['total_items']++;

        if ($tipoItem === $estatus_aceptado) { 
            $resultadosAgregados[$responsable_final]['valor_aceptado'] += $valorItem;
        } else {
            $resultadosAgregados[$responsable_final]['valor_glosado'] += $valorItem;
        }
        
        // Construir el mapa enriquecido para el modal de detalles
        $mapa_para_detalles_enriquecido[$responsable_final][$idCompuesto][] = [
            'estatus' => $tipoItem,
            'valor' => $valorItem
        ];
    }


    // --- PASO 3: CÁLCULO DE PROMEDIOS Y FORMATEO FINAL ---
    $dias_en_rango = (new DateTime($fecha_fin))->diff(new DateTime($fecha_inicio))->days + 1;
    $resultados_finales = [];
    
    foreach ($resultadosAgregados as $datosResponsable) {
        $periodo = ($dias_en_rango >= 28) ? 'mensual' : 'diario';
        $divisor = 1.0;
        if ($dias_en_rango > 0) {
            if ($periodo === 'mensual') {
                $divisor = $dias_en_rango / 30.0;
            } else {
                $divisor = $dias_en_rango;
            }
        }

        // --- INICIO DE LA CORRECCIÓN ---
        $totalGlosasIngresadas = 0;

        // Calcular conteo de facturas, formatear desglose y calcular el nuevo total de productividad.
        foreach ($datosResponsable['desglose_ratificacion'] as $estado => &$value) {
            $facturasUnicasCount = count(array_unique($value['facturas']));

            // Sumar al total de productividad, EXCLUYENDO el estado aceptado.
            if ($estado !== $estatus_aceptado) {
                $totalGlosasIngresadas += $facturasUnicasCount;
            }

            // Añadir el conteo de facturas para la UI.
            $value['cantidad_facturas'] = $facturasUnicasCount;
            // Formatear el valor monetario.
            $value['valor'] = '$' . number_format($value['valor'], 0, ',', '.');
            // Limpiar el array temporal de la respuesta final.
            unset($value['facturas']);
        }
        unset($value);

        // Asignar el total de productividad correcto.
        $datosResponsable['cantidad_glosas_ingresadas'] = $totalGlosasIngresadas;
        // --- FIN DE LA CORRECCIÓN ---

        $promedio_cantidad = $divisor > 0 ? ($datosResponsable['cantidad_glosas_ingresadas'] / $divisor) : 0;
        $promedio_valor = $divisor > 0 ? ($datosResponsable['valor_total_glosas'] / $divisor) : 0;
        
        $valor_total_items = $datosResponsable['valor_glosado'] + $datosResponsable['valor_aceptado'];
        
        $resultados_finales[] = [
            'responsable' => $datosResponsable['responsable'],
            'cantidad_glosas_ingresadas' => $datosResponsable['cantidad_glosas_ingresadas'],
            'valor_total_glosas' => '$' . number_format($datosResponsable['valor_total_glosas'], 0, ',', '.'),
            'promedios' => [
                'periodo' => $periodo,
                'promedio_cantidad' => round($promedio_cantidad, 0),
                'promedio_valor' => '$' . number_format($promedio_valor, 0, ',', '.'),
            ],
            'desglose_ratificacion' => $datosResponsable['desglose_ratificacion'],
            'total_items' => number_format($datosResponsable['total_items']),
            'valor_glosado' => '$' . number_format($datosResponsable['valor_glosado'], 0, ',', '.'),
            'valor_aceptado' => '$' . number_format($datosResponsable['valor_aceptado'], 0, ',', '.'),
            'valor_total_items' => '$' . number_format($valor_total_items, 0, ',', '.')
        ];
    }
    
    // Ordenar los resultados finales por cantidad
    usort($resultados_finales, function($a, $b) {
        return $b['cantidad_glosas_ingresadas'] <=> $a['cantidad_glosas_ingresadas'];
    });
    
    // Retornar la respuesta sin el encode JSON
    return [
        'data' => $resultados_finales, 
        'detalle_mapa' => $mapa_para_detalles_enriquecido
    ];
}