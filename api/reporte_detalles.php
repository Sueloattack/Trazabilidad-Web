<?php
/**
 * api/reporte_detalles.php (Versión Final y Simplificada)
 *
 * Propósito: Este script actúa como un eficiente "servicio de traducción".
 * Recibe un mapa de facturas del frontend, extrae los identificadores
 * únicos de los terceros (NITs), y devuelve un simple diccionario
 * que mapea cada identificador a su nombre correspondiente.
 * No procesa lógica de negocio, solo enriquece los datos del cliente.
 */

// --- 1. CONFIGURACIÓN INICIAL ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// Incluir el cliente centralizado para la API de GEMA
require_once '../helpers/gema_api_client.php';

// --- 2. LÓGICA PRINCIPAL ---
try {
    // Recibir el mapa de detalles enviado desde el frontend (main.js)
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || empty($input['detalles'])) {
        throw new Exception("Datos de entrada no válidos o mapa de detalles no proporcionado.");
    }
    
    $mapa_detalles = $input['detalles'];

    // Extraer eficientemente todos los códigos de tercero ÚNICOS y NUMÉRICOS del mapa
    $codigos_a_traducir = [];
    foreach (array_keys($mapa_detalles) as $id_compuesto) {
        $parts = explode('-', $id_compuesto, 3);
        // Usamos isset($parts[2]) para una comprobación más rápida y segura
        if (isset($parts[2])) {
            $codigo = filter_var($parts[2], FILTER_SANITIZE_NUMBER_INT);
            if ($codigo) { // filter_var devuelve false si no puede sanitizar
                 $codigos_a_traducir[$codigo] = true;
            }
        }
    }
    
    // Si no se encontraron códigos válidos, devolver un diccionario vacío y finalizar.
    if (empty($codigos_a_traducir)) {
        echo json_encode([]); // Devolver un objeto JSON vacío
        exit;
    }

    $lista_codigos = array_keys($codigos_a_traducir);

    // Construir la cláusula WHERE con condiciones 'OR' para máxima compatibilidad con VFP
    $where_conditions = [];
    foreach ($lista_codigos as $codigo) {
        // [Corrección clave] Comparar como número, sin comillas simples
        $where_conditions[] = "codigo = {$codigo}";
    }
    $where_clause = implode(' OR ', $where_conditions);
    
    // Envolver en paréntesis por robustez si hay múltiples condiciones
    if (count($where_conditions) > 1) {
        $where_clause = "({$where_clause})";
    }

    // Preparar la consulta para la API (sin la palabra 'SELECT')
    $sql_terceros = "codigo, nombre FROM gema10.d/dgen/datos/terceros WHERE {$where_clause}";
    
    // Llamar a la API para obtener la traducción de códigos a nombres
    $terceros_data = queryApiGema($sql_terceros);
    
    // Crear el mapa de traducción final: [código] => "Nombre Completo"
    $mapa_nombres = [];
    foreach ($terceros_data as $tercero) {
        $mapa_nombres[trim($tercero['codigo'])] = trim($tercero['nombre']);
    }

    // Devolver el mapa de nombres (el "diccionario") al frontend
    echo json_encode($mapa_nombres);

} catch (Exception $e) {
    // --- 3. MANEJO DE ERRORES ---
    http_response_code(500);
    error_log("Error fatal en api/reporte_detalles.php: " . $e->getMessage());
    echo json_encode(['error' => 'Ocurrió un error en el servidor al obtener los nombres de las entidades.']);
}

?>