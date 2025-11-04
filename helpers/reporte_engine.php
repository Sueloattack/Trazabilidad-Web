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
        d.gl_docn, d.quien, d.estatus1, c.fc_serie AS serie, c.fc_docn AS docn, c.tercero, d.vr_glosa, d.fecha_gl
        FROM gema10.d/salud/datos/glo_det d
        JOIN gema10.d/salud/datos/glo_cab c ON d.gl_docn = c.gl_docn
        WHERE {$estatus_where_clause}
          AND d.freg BETWEEN {$fecha_inicio_fox} AND {$fecha_fin_fox}
    ";
    
    $itemsCandidatosVFP_raw = queryApiGema($sql_gema);
    if (empty($itemsCandidatosVFP_raw)) {
        return ['data' => [], 'detalle_mapa' => []];
    }
    
    // --- PASO 2: LIMPIEZA Y AGRUPACIÓN INICIAL POR FACTURA ---
    $mapa_por_factura = [];
    foreach ($itemsCandidatosVFP_raw as $itemCrudo) {
        $idCompuesto = strtoupper(trim($itemCrudo['serie'])) . '-' . strtoupper(trim($itemCrudo['docn'])) . '-' . strtoupper(trim($itemCrudo['tercero']));
        $mapa_por_factura[$idCompuesto][] = [
            'serie'   => strtoupper(trim($itemCrudo['serie'])),
            'docn'    => strtoupper(trim($itemCrudo['docn'])),
            'tercero' => strtoupper(trim($itemCrudo['tercero'])),
            'quien'   => strtoupper(trim($itemCrudo['quien'])),
            'estatus1'  => strtolower(trim($itemCrudo['estatus1'])),
            'vr_glosa' => (float)trim($itemCrudo['vr_glosa']),
            'fecha_gl' => trim($itemCrudo['fecha_gl']), // Fecha de creación del ítem
        ];
    }

    // --- Obtener nombres reales de los responsables (quien) ---
    $quien_aliases = [];
    foreach($mapa_por_factura as $items) {
        foreach($items as $item) {
            if(!empty($item['quien'])) {
                $quien_aliases[$item['quien']] = true;
            }
        }
    }
    $quien_aliases = array_keys($quien_aliases);
    $mapa_quien_nombres = [];
    if (!empty($quien_aliases)) {
        $in_values_quien = "'" . implode("','", array_map('trim', $quien_aliases)) . "'";
        $sql_quien_names = "id, nombre FROM gema10.d/dgen/datos/maopera2 WHERE id IN ({$in_values_quien})";
        $quien_data = queryApiGema($sql_quien_names);
        foreach ($quien_data as $operador) {
            $mapa_quien_nombres[trim($operador['id'])] = trim($operador['nombre']);
        }
    }

    // --- PASO 3: LÓGICA DE EVENTOS DE TRABAJO Y AGRUPACIÓN FINAL ---
    $resultadosAgregados = [];
    $mapa_para_detalles_final = [];
    $desglose_base = array_fill_keys($desglose_keys, ['cantidad' => 0, 'valor' => 0.0, 'facturas' => []]);

    foreach ($mapa_por_factura as $idCompuesto => $itemsList) {
        $responsable_alias = !empty($itemsList[0]['quien']) ? strtoupper(trim($itemsList[0]['quien'])) : '(Sin Asignar)';
        $responsable_final = $mapa_quien_nombres[$responsable_alias] ?? $responsable_alias;

        // 1. Separar items por tipo (primario, secundario)
        $primary_items = [];
        $secondary_items = []; // AI y AE
        foreach ($itemsList as $item) {
            if ($item['estatus1'] === 'ai' || $item['estatus1'] === 'ae') {
                $secondary_items[] = $item;
            } else {
                $primary_items[] = $item;
            }
        }

        // 2. Agrupar items primarios por status y fecha
        $grupos_primarios = [];
        $fechas_por_grupo = [];
        foreach ($primary_items as $item) {
            $status = $item['estatus1'];
            $grupos_primarios[$status][] = $item;
            $fechas_por_grupo[$status][$item['fecha_gl']] = true;
        }

        // 3. Asociar items secundarios a los grupos primarios por fecha
        $unmatched_secondary_items = [];
        foreach ($secondary_items as $item) {
            $is_matched = false;
            foreach ($fechas_por_grupo as $status => $fechas) {
                if (isset($fechas[$item['fecha_gl']])) {
                    $grupos_primarios[$status][] = $item;
                    $is_matched = true;
                    break; 
                }
            }
            if (!$is_matched) {
                $unmatched_secondary_items[] = $item;
            }
        }

        // 4. Crear los eventos de trabajo (filas finales)
        $eventos_de_trabajo = [];
        if (empty($grupos_primarios)) { // Caso Aceptación Total
            if (!empty($secondary_items)) {
                $eventos_de_trabajo[$idCompuesto] = $secondary_items;
            }
        } else {
            foreach ($grupos_primarios as $status => $items_del_grupo) {
                $eventos_de_trabajo[$idCompuesto . '-' . $status] = $items_del_grupo;
            }
            if (!empty($unmatched_secondary_items)) { // Secundarios sin match van aparte
                $eventos_de_trabajo[$idCompuesto . '-' . $estatus_aceptado] = $unmatched_secondary_items;
            }
        }

        // 5. Procesar cada evento para agregados y mapa de detalle
        foreach ($eventos_de_trabajo as $evento_key => $evento_items) {
            // Inicializar responsable si no existe
            if (!isset($resultadosAgregados[$responsable_final])) {
                $resultadosAgregados[$responsable_final] = [
                    'responsable' => $responsable_final, 'cantidad_glosas_ingresadas' => 0,
                    'valor_total_glosas' => 0.0, 'desglose_ratificacion' => $desglose_base,
                    'total_items' => 0, 'valor_glosado' => 0.0, 'valor_aceptado' => 0.0
                ];
            }

            // Contar como 1 evento de trabajo
            $resultadosAgregados[$responsable_final]['cantidad_glosas_ingresadas']++;
            
            foreach ($evento_items as $item) {
                $tipoItem = $item['estatus1'];
                $valorItem = $item['vr_glosa'];

                // Sumar a totales del responsable
                $resultadosAgregados[$responsable_final]['total_items']++;
                $resultadosAgregados[$responsable_final]['valor_total_glosas'] += $valorItem;
                if ($tipoItem === 'ai' || $tipoItem === 'ae') {
                    $resultadosAgregados[$responsable_final]['valor_aceptado'] += $valorItem;
                } else {
                    $resultadosAgregados[$responsable_final]['valor_glosado'] += $valorItem;
                }
                
                // Sumar a desglose de ratificación
                if (array_key_exists($tipoItem, $resultadosAgregados[$responsable_final]['desglose_ratificacion'])) {
                    $resultadosAgregados[$responsable_final]['desglose_ratificacion'][$tipoItem]['cantidad']++;
                    $resultadosAgregados[$responsable_final]['desglose_ratificacion'][$tipoItem]['valor'] += $valorItem;
                    $resultadosAgregados[$responsable_final]['desglose_ratificacion'][$tipoItem]['facturas'][] = $idCompuesto;
                }
            }
            
            // Guardar en el mapa para el modal
            $mapa_para_detalles_final[$responsable_final][$evento_key] = [
                'items' => $evento_items,
                'ingresos' => 1 // Cada fila es 1 ingreso/evento
            ];
        }
    }

    // --- PASO 4: CÁLCULO DE PROMEDIOS Y FORMATEO FINAL ---
    $dias_en_rango = (new DateTime($fecha_fin))->diff(new DateTime($fecha_inicio))->days + 1;
    $resultados_finales = [];

    foreach ($resultadosAgregados as $datosResponsable) {
        $periodo = ($dias_en_rango >= 28) ? 'mensual' : 'diario';
        $divisor = ($dias_en_rango > 0) ? (($periodo === 'mensual') ? $dias_en_rango / 30.0 : $dias_en_rango) : 1.0;

        // Formatear desglose (el conteo de productividad ya es correcto)
        foreach ($datosResponsable['desglose_ratificacion'] as &$value) {
            $value['cantidad_facturas'] = count(array_unique($value['facturas']));
            $value['valor'] = '$' . number_format($value['valor'], 0, ',', '.');
            unset($value['facturas']);
        }
        unset($value);

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
        'detalle_mapa' => $mapa_para_detalles_final
    ];
}