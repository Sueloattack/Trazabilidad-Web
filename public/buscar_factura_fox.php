<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/conectFox.php'; 

header('Content-Type: application/json'); 

// Inicializar datos con valores por defecto para evitar 'undefined index' en el frontend
$default_datos = [
    'nit' => '',
    'tipo_glosa' => '', // Se establecerá a 'NU' si no se encuentra la factura
    'gl_docn' => null,
    'descripcion_glosa' => ''
];

$response = ['success' => false, 'message' => 'Error desconocido.', 'datos' => $default_datos];

if (!isset($_POST['serie']) || !isset($_POST['numero_factura'])) {
    $response['message'] = 'Faltan parámetros: serie o número de factura.';
    $response['datos']['tipo_glosa'] = 'ERR'; // Podrías indicar un error diferente
    echo json_encode($response);
    exit;
}

$serie = trim((string)$_POST['serie']);
$numero_factura_str = trim((string)$_POST['numero_factura']);

if ($serie === '' || $numero_factura_str === '' || !is_numeric($numero_factura_str)) {
    $response['message'] = 'Valores inválidos para serie o número de factura.';
    $response['datos']['tipo_glosa'] = 'ERR';
    echo json_encode($response);
    exit;
}

$numero_factura_int = (int)$numero_factura_str;

try {
    $pdoFox = ConnectionFox::con();

    $sql = "
        SELECT 
            tercero,   
            gl_docn,   
            tipo,       
            nom_tipo    
        FROM glo_cab    
        WHERE fc_serie = ? AND fc_docn = ?
    ";
    
    $stmtFox = $pdoFox->prepare($sql);

    $stmtFox->bindValue(1, $serie, PDO::PARAM_STR);
    $stmtFox->bindValue(2, $numero_factura_int, PDO::PARAM_INT);

    if ($stmtFox->execute()) {
        $resultado = $stmtFox->fetch(PDO::FETCH_ASSOC);
        $stmtFox->closeCursor();

        if ($resultado) { // Si se encontró la factura en glo_cab
            $response['success'] = true;
            $response['message'] = 'Datos encontrados.';
            
            $response['datos'] = [
                'nit' => $resultado['tercero'] ?? '', // Usar '' como default si es null
                'tipo_glosa' => $resultado['tipo'] ?? '', // El tipo real de la glosa
                'gl_docn' => $resultado['gl_docn'] ?? null,
                'descripcion_glosa' => $resultado['nom_tipo'] ?? '' // nom_tipo para descripción
            ];

        } else { // Si NO se encontró la factura en glo_cab
            $response['success'] = false; // Aunque la consulta se ejecutó, no hubo "éxito" en encontrar datos
            $response['message'] = 'Factura no encontrada en FoxPro.';
            $response['datos']['tipo_glosa'] = 'NU'; // <<< ESTADO "NU" CUANDO NO SE ENCUENTRA
            // Los otros campos como nit, gl_docn, descripcion_glosa ya están vacíos por $default_datos
            $response['datos']['descripcion_glosa'] = 'Nueva factura';
        }
    } else {
        $errorInfo = $stmtFox->errorInfo();
        $response['message'] = 'Error al ejecutar la consulta en FoxPro: ' . ($errorInfo[2] ?? 'Error desconocido');
        $response['datos']['tipo_glosa'] = 'ERR_DB'; // Indicar un error de base de datos
        error_log("Error FoxPro en buscar_factura_fox.php: " . ($errorInfo[2] ?? 'Error desconocido'));
    }

} catch (PDOException $e) {
    $response['message'] = 'Error de conexión o consulta a FoxPro: ' . $e->getMessage();
    $response['datos']['tipo_glosa'] = 'ERR_PDO'; // Indicar un error PDO
    error_log("PDOException en buscar_factura_fox.php: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error general en el servidor: ' . $e->getMessage();
    $response['datos']['tipo_glosa'] = 'ERR_GEN'; // Indicar un error general
    error_log("Exception en buscar_factura_fox.php: " . $e->getMessage());
}

echo json_encode($response);
?>