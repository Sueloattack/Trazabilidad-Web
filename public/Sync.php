<?php
declare(strict_types=1);

// --- Configuración de Depuración ---
define('DEBUG_SYNC', true); 
define('DEBUG_SYNC_LOG_FILE', __DIR__ . '/../logs/sync_debug.log');
// No se usa DEBUG_MAX_MYSQL_ROWS_TO_PROCESS ya que los IDs vienen de POST
// define('DEBUG_MYSQL_FACTURA_ID_TO_TEST', 34027); // << Puedes usar esto para forzar una factura
// --- Fin Configuración de Depuración ---

require_once __DIR__ . '/../config/MysqlConnection.php'; 
require_once __DIR__ . '/../config/conectFox.php';

// Función de logueo 
function sync_log_message(string $message): void {
    if (!defined('DEBUG_SYNC') || !DEBUG_SYNC) {
        return;
    }
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[{$timestamp}] {$message}";
    if (defined('DEBUG_SYNC_LOG_FILE') && DEBUG_SYNC_LOG_FILE) {
        $logDir = dirname(DEBUG_SYNC_LOG_FILE);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        @file_put_contents(DEBUG_SYNC_LOG_FILE, $formattedMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    if (php_sapi_name() === 'cli') { 
        echo $formattedMessage . PHP_EOL; 
    }
}

$actualizadosCount = 0; 
$idsActualizados = []; 

sync_log_message("Script Sync.php iniciado (Logueando freg_foxpro y gl_docn_foxpro - VERSIÓN ASEGURADA).");

try {
    sync_log_message("Intentando conexión a MySQL..."); 
    $pdoMysql = MysqlConnection::getInstance(); 
    sync_log_message("Conexión a MySQL establecida.");

    sync_log_message("Intentando conexión a FoxPro..."); 
    $pdoFox = ConnectionFox::con(); 
    sync_log_message("Conexión a FoxPro establecida.");

    $ids_a_sincronizar_array = [];
    $pagina_origen = 1; 
    if (isset($_POST['sync_ids']) && !empty($_POST['sync_ids'])) {
        $ids_string = trim($_POST['sync_ids']);
        if ($ids_string !== '') {
            $ids_a_sincronizar_array = array_map('intval', explode(',', $ids_string));
            $ids_a_sincronizar_array = array_filter(array_unique($ids_a_sincronizar_array), function($id) { return $id > 0; });
        }
    }
    if (isset($_POST['current_page']) && is_numeric($_POST['current_page']) && (int)$_POST['current_page'] > 0) {
        $pagina_origen = (int)$_POST['current_page'];
    }

    if (empty($ids_a_sincronizar_array) && defined('DEBUG_MYSQL_FACTURA_ID_TO_TEST') ) {
        $ids_a_sincronizar_array = [(int)DEBUG_MYSQL_FACTURA_ID_TO_TEST];
        sync_log_message("Usando DEBUG_MYSQL_FACTURA_ID_TO_TEST: " . DEBUG_MYSQL_FACTURA_ID_TO_TEST . " (No se recibieron IDs del POST).");
    }
    
    if (empty($ids_a_sincronizar_array)) {
        sync_log_message("No se recibieron IDs válidos para sincronizar. Finalizando.");
        if (php_sapi_name() !== 'cli') { header("Location: index.php?sync_status=no_ids_provided&page={$pagina_origen}"); exit; }
        exit;
    }
    sync_log_message("IDs de factura MySQL a sincronizar: " . implode(', ', $ids_a_sincronizar_array) . " (Originados desde página: {$pagina_origen})");

    $placeholders_for_ids = implode(',', array_fill(0, count($ids_a_sincronizar_array), '?'));
    $sqlMysql = "
        SELECT fg.id, fg.serie, fg.docn, fg.nit_tercero, eg.f_respuesta_g 
        FROM factura_glosas fg
        INNER JOIN envio_glosas eg ON eg.id_facturaglosas = fg.id
        WHERE fg.id IN ({$placeholders_for_ids}) AND eg.activo = 1
        ORDER BY fg.id 
    ";
    sync_log_message("SQL MySQL: " . preg_replace('/\s+/', ' ', $sqlMysql));
    $stmtMysql = $pdoMysql->prepare($sqlMysql);
    $stmtMysql->execute($ids_a_sincronizar_array); 
    $facturasParaSincronizar = $stmtMysql->fetchAll();
    
    if (empty($facturasParaSincronizar)) { 
        sync_log_message("No se encontraron facturas activas en MySQL para los IDs proporcionados (" . implode(', ', $ids_a_sincronizar_array) . "). Finalizando.");
        if (php_sapi_name() !== 'cli') { header("Location: index.php?sync_status=no_data_for_ids&page={$pagina_origen}"); exit; }
        exit;
    }
    sync_log_message("Facturas de MySQL a procesar (encontradas para los IDs): " . count($facturasParaSincronizar));

    // ***** ESTA ES LA SQL FOXPRO BASE QUE DEBE ESTAR EN TU ARCHIVO *****
    $sqlFoxBase = "
        SELECT 
            d.gr_docn,       
            d.fecha_rep,     
            d.freg AS freg_foxpro,       
            c.gl_docn AS gl_docn_foxpro  
        FROM glo_cab c, glo_det d
        WHERE c.gl_docn = d.gl_docn
          AND c.fc_serie = ? 
          AND c.fc_docn = ? 
          AND c.tercero = ? 
    ";
    // La condición "AND d.freg" se añadirá dinámicamente

    foreach ($facturasParaSincronizar as $idx => $factura) {
        sync_log_message("----------------------------------------------------------------------");
        $currentFacturaMsgPrefix = "[Factura MySQL Index: {$idx}, ID: {$factura['id']}] ";
        sync_log_message($currentFacturaMsgPrefix . "Procesando datos de MySQL: " . print_r($factura, true));

        $param_fc_serie = trim((string)$factura['serie']);
        $param_fc_docn = (int)$factura['docn'];        
        $param_tercero = (int)$factura['nit_tercero']; 
        $fecha_para_freg_str_iso = null; 
        $fecha_mysql_a_usar = $factura['f_respuesta_g'];
        $nombre_campo_fecha_mysql = 'f_respuesta_g';

        if ($fecha_mysql_a_usar !== null && trim((string)$fecha_mysql_a_usar) !== '') {
            try {
                $dateTimeObj = new DateTime((string)$fecha_mysql_a_usar); 
                $fecha_para_freg_str_iso = $dateTimeObj->format('Y-m-d');
                sync_log_message("  Campo MySQL '{$nombre_campo_fecha_mysql}' ('{$fecha_mysql_a_usar}') -> Convertido a ISO '{$fecha_para_freg_str_iso}' para interpolar en SQL FoxPro.");
            } catch (Exception $e) {
                sync_log_message("  ERROR convirtiendo campo MySQL '{$nombre_campo_fecha_mysql}' ('{$fecha_mysql_a_usar}'): " . $e->getMessage() . ". Condición de fecha FoxPro se omitirá.");
            }
        } else { 
            sync_log_message("  Campo MySQL '{$nombre_campo_fecha_mysql}' es NULL o vacía. Condición de fecha FoxPro se omitirá.");
        }

        if ($fecha_para_freg_str_iso === null) {
            sync_log_message("  Debido a que la fecha para comparación ({$nombre_campo_fecha_mysql}) es NULL o inválida, y es un criterio de 4 campos, se saltará consulta a FoxPro para factura ID {$factura['id']}.");
            continue; 
        }

        $sqlFoxParaEjecutar = $sqlFoxBase . " AND d.freg = {d '" . $fecha_para_freg_str_iso . "'}";
        sync_log_message("  SQL FoxPro FINAL para esta factura (con fecha interpolada): " . preg_replace('/\s+/', ' ', $sqlFoxParaEjecutar));
        
        $stmtFox = null;
        try {
            sync_log_message("  Preparando statement FoxPro...");
            $stmtFox = $pdoFox->prepare($sqlFoxParaEjecutar); 
            sync_log_message("  Statement FoxPro preparado exitosamente.");
        } catch (PDOException $e) {
            sync_log_message($currentFacturaMsgPrefix . "ERROR AL PREPARAR statement FoxPro: " . $e->getMessage() . ". SQL: " . $sqlFoxParaEjecutar);
            error_log($currentFacturaMsgPrefix . "ERROR AL PREPARAR statement FoxPro: " . $e->getMessage() . ". SQL: " . $sqlFoxParaEjecutar);
            continue; 
        }
        
        sync_log_message($currentFacturaMsgPrefix . "Bindeando 3 parámetros (serie, docn, tercero) para FoxPro:");
        $stmtFox->bindValue(1, $param_fc_serie, PDO::PARAM_STR);
        sync_log_message("    1: fc_serie (PDO::PARAM_STR) = " . var_export($param_fc_serie, true));
        $stmtFox->bindValue(2, $param_fc_docn, PDO::PARAM_INT);
        sync_log_message("    2: fc_docn (PDO::PARAM_INT) = " . var_export($param_fc_docn, true));
        $stmtFox->bindValue(3, $param_tercero, PDO::PARAM_INT);
        sync_log_message("    3: tercero (PDO::PARAM_INT) = " . var_export($param_tercero, true));
        sync_log_message("  Valores bindeados.");

        try {
            sync_log_message($currentFacturaMsgPrefix . "Ejecutando FoxPro statement...");
            $executeSuccess = $stmtFox->execute(); 
            
            if ($executeSuccess) {
                sync_log_message($currentFacturaMsgPrefix . "FoxPro statement ejecutado exitosamente.");
                $resultadoFox = $stmtFox->fetch(PDO::FETCH_ASSOC); 
                if ($resultadoFox) { 
                    // Logueo explícito ANTES del print_r completo
                    if(isset($resultadoFox['freg_foxpro'])) {
                        sync_log_message("    VALOR ENCONTRADO d.freg (freg_foxpro): " . $resultadoFox['freg_foxpro']);
                    } else {
                        sync_log_message("    AVISO: El campo 'freg_foxpro' NO está en el resultado de FoxPro.");
                    }
                    if(isset($resultadoFox['gl_docn_foxpro'])) {
                        sync_log_message("    VALOR ENCONTRADO c.gl_docn (gl_docn_foxpro): " . $resultadoFox['gl_docn_foxpro']);
                    } else {
                        sync_log_message("    AVISO: El campo 'gl_docn_foxpro' NO está en el resultado de FoxPro.");
                    }
                    sync_log_message($currentFacturaMsgPrefix . "¡COINCIDENCIA ENCONTRADA!: " . print_r($resultadoFox, true));
                    
                    $cuentaCobro = isset($resultadoFox['gr_docn']) && trim((string)$resultadoFox['gr_docn']) !== '' && (int)$resultadoFox['gr_docn'] !== 0 ? (int)$resultadoFox['gr_docn'] : null;
                    $fechaResp = null;
                    if (isset($resultadoFox['fecha_rep']) && !empty(trim((string)$resultadoFox['fecha_rep']))) {
                        $dateRepStr = trim((string)$resultadoFox['fecha_rep']);
                        if (preg_match('/^[0\s\/.-]*$/', $dateRepStr) || $dateRepStr === '1899-12-30' || substr($dateRepStr, 0, 4) === '0000') { 
                            $fechaResp = null;
                            sync_log_message("    fecha_rep de FoxPro ('{$dateRepStr}') interpretada como NULL.");
                        } else { 
                            try { $dateRepObj = new DateTime($dateRepStr); $fechaResp = $dateRepObj->format('Y-m-d'); 
                                sync_log_message("    fecha_rep de FoxPro ('{$dateRepStr}') convertida a: '{$fechaResp}'.");
                            } catch(Exception $e) { 
                                sync_log_message("    ERROR parseando fecha_rep de FoxPro '{$dateRepStr}': " . $e->getMessage() . ". fecha_resp para MySQL será NULL.");
                                error_log($currentFacturaMsgPrefix . "ERROR parseando fecha_rep '{$dateRepStr}': " . $e->getMessage());
                            }
                        }
                    } else { 
                        sync_log_message("    fecha_rep de FoxPro vacía o no presente. fecha_resp para MySQL será NULL.");
                    }

                    sync_log_message("    Actualizando MySQL ID {$factura['id']} con cuenta_cobro:".var_export($cuentaCobro,true).", fecha_resp:".var_export($fechaResp,true));
                    $sqlActualizarMysql = "UPDATE factura_glosas SET cuenta_cobro = ?, fecha_resp = ? WHERE id = ?";
                    $stmtActualizarMysql = $pdoMysql->prepare($sqlActualizarMysql);
                    $stmtActualizarMysql->execute([$cuentaCobro, $fechaResp, $factura['id']]);
                    if ($stmtActualizarMysql->rowCount() > 0) {
                         $actualizadosCount++; $idsActualizados[] = $factura['id'];
                         sync_log_message("    MySQL actualizado para ID {$factura['id']}. RowCount: " . $stmtActualizarMysql->rowCount());
                    } else { 
                        sync_log_message("    MySQL: La actualización no afectó filas para ID {$factura['id']} (datos podrían ser iguales o el ID no existe).");
                    }
                } else { 
                    sync_log_message($currentFacturaMsgPrefix . "Sin coincidencias en FoxPro para los 4 criterios. ErrorInfo: " . print_r($stmtFox->errorInfo(), true));
                }
            } else {
                sync_log_message($currentFacturaMsgPrefix . "FALLO al ejecutar FoxPro statement. ErrorInfo: " . print_r($stmtFox->errorInfo(), true));
            }
            $stmtFox->closeCursor(); 
        } catch (PDOException $e) { 
            sync_log_message($currentFacturaMsgPrefix . "ERROR PDO FOXPRO durante execute/fetch: " . $e->getMessage() . " (Code: " . $e->getCode() . ") ErrorInfo: " . print_r(isset($stmtFox) ? $stmtFox->errorInfo() : 'stmtFox no disponible', true));
            error_log($currentFacturaMsgPrefix . "ERROR PDO FOXPRO (CON INTERPOLACIÓN FECHA): " . $e->getMessage());
            if (isset($stmtFox) && $stmtFox instanceof PDOStatement) { 
                 try { $stmtFox->closeCursor(); } catch (PDOException $exClose) { /* ignore */ } 
            }
        }
    } // Fin foreach

    sync_log_message("----------------------------------------------------------------------");
    sync_log_message("Sincronización finalizada. Registros de MySQL procesados: " . count($facturasParaSincronizar) . ". Registros actualizados en MySQL: $actualizadosCount");
    if (!empty($idsActualizados)) {
        sync_log_message("IDs de MySQL actualizados: " . implode(',', $idsActualizados));
    }

    if (php_sapi_name() !== 'cli') {
        if (DEBUG_SYNC) {
            $debugOutput = "<hr style='border-top: 1px dashed #ccc; margin: 20px 0;'>";
            $debugOutput .= "<div style='font-family: Arial, sans-serif; padding:10px; border:1px solid #ccc; background-color:#f9f9f9;'>";
            $debugOutput .= "<h3>Sincronización de Prueba Finalizada</h3>";
            $debugOutput .= "<p>Revisa el log para detalles completos: <a href=\"../logs/sync_debug.log\" target=\"_blank\">logs/sync_debug.log</a></p>";
            $debugOutput .= "<p><strong>Registros de MySQL procesados:</strong> " . count($facturasParaSincronizar) . "</p>";
            $debugOutput .= "<p><strong>Registros actualizados en MySQL:</strong> {$actualizadosCount}</p>";
            if (!empty($idsActualizados)) {
                $debugOutput .= "<p><strong>IDs de facturas actualizadas:</strong> " . implode(', ', $idsActualizados) . "</p>";
            } else { $debugOutput .= "<p>No se realizaron actualizaciones en MySQL.</p>"; }
            $redirectParams = ['sync_ran' => '1', 'updated_count' => $actualizadosCount, 'page' => $pagina_origen];
            if (!empty($idsActualizados)) { $redirectParams['ids'] = implode(',', $idsActualizados); }
            $simulatedRedirectUrl = "index.php?" . http_build_query($redirectParams);
            $debugOutput .= "<p style='margin-top:15px;'><strong>Para ver el resultado en la lista (con resaltado):</strong><br>";
            $debugOutput .= "<a href=\"" . htmlspecialchars($simulatedRedirectUrl) . "\" style='display:inline-block; padding:10px 15px; background-color:#28a745; color:white; text-decoration:none; border-radius:4px;'>Volver a la Página " . htmlspecialchars((string)$pagina_origen) . " con Resultados de Sincronización</a></p>";
            $debugOutput .= "<p><a href=\"index.php?page=" . htmlspecialchars((string)$pagina_origen) . "\">Volver a la Página " . htmlspecialchars((string)$pagina_origen) . " (sin parámetros de sync)</a></p>";
            $debugOutput .= "</div>";
            echo $debugOutput;
            exit;
        } else { 
            $queryParameters = ['sync_status' => ($actualizadosCount > 0 ? 'success' : 'no_changes_or_matches'), 'updated_count' => $actualizadosCount, 'page' => $pagina_origen];
            if (isset($_POST['sync_ids']) && !empty($_POST['sync_ids']) && empty($facturasParaSincronizar) && $actualizadosCount === 0) {
                $queryParameters['sync_status'] = 'no_matches_for_page_ids';
            }
            if (!empty($idsActualizados)) { $queryParameters['ids'] = implode(',', $idsActualizados); }
            header("Location: index.php?" . http_build_query($queryParameters));
            exit;
        }
    }

} catch (PDOException $e) { 
    $finalErrorMsg = "ERROR CRÍTICO PDO (General): " . $e->getMessage() . " (Code: " . $e->getCode() . ")\nTrace: " . $e->getTraceAsString();
    sync_log_message($finalErrorMsg); error_log($finalErrorMsg);
    if (php_sapi_name() !== 'cli') { 
        if (defined('DEBUG_SYNC') && DEBUG_SYNC) { echo "<p style='color:red; font-weight:bold;'>ERROR CRÍTICO (PDO).</p><p>Detalles en log.</p><pre>" . htmlspecialchars($finalErrorMsg) . "</pre>"; } 
        else { echo "<p><b>Error CRÍTICO (PDO).</b> Contacte admin.</p>";}
    } else { echo $finalErrorMsg . PHP_EOL; }
    exit(1);
} catch (Exception $e) { 
    $finalErrorMsg = "ERROR GENERAL INESPERADO: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString();
    sync_log_message($finalErrorMsg); error_log($finalErrorMsg);
    if (php_sapi_name() !== 'cli') { 
        if (defined('DEBUG_SYNC') && DEBUG_SYNC) { echo "<p style='color:red; font-weight:bold;'>Error INESPERADO.</p><p>Detalles en log.</p><pre>" . htmlspecialchars($finalErrorMsg) . "</pre>"; }
        else { echo "<p><b>Error INESPERADO.</b> Contacte admin.</p>"; }
    } else { echo $finalErrorMsg . PHP_EOL; }
    exit(1); 
}
?>