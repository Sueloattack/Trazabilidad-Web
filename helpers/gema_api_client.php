<?php

/**
 * gema_api_client.php
 *
 * Este archivo define una constante con la URL base de la API de GEMA y una
 * función reutilizable (queryApiGema) para ejecutar consultas SQL a través de esa API.
 * Centraliza la lógica de conexión, codificación de URL y manejo de errores.
 */

// Define la URL base de la API para que sea fácil de modificar en un solo lugar.
define('API_BASE_URL', 'https://asotrauma.ngrok.app/api-busqueda-gema/public/api');

/**
 * Ejecuta una consulta SQL contra la API de GEMA.
 *
 * Esta función se encarga de todo el proceso de comunicación:
 * 1. Codifica la consulta SQL para que sea segura en una URL.
 * 2. Realiza la petición HTTP usando CURL.
 * 3. Valida exhaustivamente la respuesta para detectar cualquier posible error.
 * 4. Devuelve únicamente el array de datos si la petición fue exitosa.
 *
 * @param string $sql_query La consulta SQL a ejecutar (sin la palabra "SELECT").
 * @return array Los datos devueltos por la API.
 * @throws Exception Si ocurre cualquier error durante la comunicación o si la API devuelve un error.
 */
function queryApiGema($sql_query)
{
    // Registra en el log de errores de PHP que se está ejecutando una consulta.
    // Es útil para depurar y ver qué consultas se están realizando.
    error_log("[API GEMA] Ejecutando: " . substr($sql_query, 0, 200) . "...");

    // 1. PREPARACIÓN DE LA URL
    // Codifica la consulta para que los caracteres especiales (espacios, =, etc.)
    // se conviertan a un formato seguro para URLs (ej. ' ' -> '%20').
    $encoded_query = urlencode($sql_query);

    // Construye la URL completa del endpoint de la API.
    $url = API_BASE_URL . "/select/?query=" . $encoded_query;

    // 2. COMUNICACIÓN CON CURL (CLIENTE HTTP)
    // Inicializa una sesión de cURL, que es una librería de PHP para hacer peticiones web.
    $ch = curl_init($url);

    // Configura las opciones de la petición:
    // CURLOPT_RETURNTRANSFER: Le dice a cURL que no imprima la respuesta en pantalla,
    // sino que la devuelva como una cadena de texto para guardarla en una variable.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // CURLOPT_FOLLOWLOCATION: Permite que cURL siga redirecciones si la API las usa.
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    // Ejecuta la petición y guarda la respuesta (en formato de texto JSON) en $response_str.
    $response_str = curl_exec($ch);

    // 3. MANEJO EXHAUSTIVO DE ERRORES
    // 3.1. Error de Conexión o de Red:
    // Si hubo un problema a nivel de red (ej. no se pudo conectar, timeout), cURL lo reporta aquí.
    if (curl_errno($ch)) {
        throw new Exception("Error de cURL: " . curl_error($ch));
    }

    // 3.2. Error de Servidor (Código HTTP):
    // Obtiene el código de estado HTTP de la respuesta (ej. 200 OK, 404 Not Found, 500 Error).
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Cierra la sesión de cURL para liberar recursos.
    curl_close($ch);
    
    // Un código diferente a 200 significa que el servidor de la API tuvo un problema.
    if ($http_code != 200) {
        throw new Exception("Error API GEMA. Código: {$http_code}. Respuesta: " . $response_str);
    }

    // 3.3. Error de Formato JSON:
    // Intenta decodificar la respuesta de texto JSON a un array asociativo de PHP.
    $response = json_decode($response_str, true);
    
    // Si la respuesta no es un JSON válido, la decodificación falla.
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Respuesta API no es un JSON válido.");
    }

    // 3.4. Error Lógico de la API:
    // La API puede devolver un JSON válido pero que contiene un mensaje de error propio.
    // Esta línea comprueba que la respuesta tenga el formato esperado de éxito.
    // Busca una clave 'status' con valor 'success' y que exista la clave 'data'.
    if (empty($response['status']) || $response['status'] !== 'success' || !isset($response['data'])) {
        // Si no cumple el formato, lanza un error con el mensaje de la API si existe.
        throw new Exception("API devolvió error: " . ($response['message'] ?? 'Formato inesperado.'));
    }

    // 4. DEVOLUCIÓN DEL RESULTADO
    // Si todas las validaciones pasaron, la función devuelve únicamente el array con los datos.
    return $response['data'];
}
?>