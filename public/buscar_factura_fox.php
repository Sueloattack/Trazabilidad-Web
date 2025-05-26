<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/conectFox.php';
header('Content-Type: application/json');

$default_datos = [
    'nit' => '',
    'tipo_glosa' => '',
    'gl_docn' => null,
    'descripcion_glosa' => ''
];

$response = ['success' => false, 'message' => 'Error desconocido.', 'datos' => $default_datos];

if (!isset($_POST['serie']) || !isset($_POST['numero_factura'])) {
    $response['message'] = 'Faltan parámetros: serie o número de factura.';
    $response['datos']['tipo_glosa'] = 'ERR';
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
            nom_tipo,
            gl_fecha
        FROM glo_cab
        WHERE fc_serie = ? AND fc_docn = ?
    ";

    $stmtFox = $pdoFox->prepare($sql);
    $stmtFox->bindValue(1, $serie, PDO::PARAM_STR);
    $stmtFox->bindValue(2, $numero_factura_int, PDO::PARAM_INT);

    if ($stmtFox->execute()) {
        $resultados = $stmtFox->fetchAll(PDO::FETCH_ASSOC);
        $stmtFox->closeCursor();

        if ($resultados && count($resultados) > 0) {
            // Ordenar todos por fecha descendente y tomar el más reciente, sin importar tipo
            usort($resultados, fn($a, $b) => strtotime($b['gl_fecha']) <=> strtotime($a['gl_fecha']));
            $resultadoFinal = $resultados[0];

            $response['success'] = true;
            $response['message'] = 'Datos encontrados.';
            $response['datos'] = [
                'nit' => $resultadoFinal['tercero'] ?? '',
                'tipo_glosa' => $resultadoFinal['tipo'] ?? '',
                'gl_docn' => $resultadoFinal['gl_docn'] ?? null,
                'descripcion_glosa' => $resultadoFinal['nom_tipo'] ?? ''
            ];
        } else {
            $response['success'] = false;
            $response['message'] = 'Factura no encontrada en FoxPro.';
            $response['datos']['tipo_glosa'] = 'NU';
            $response['datos']['descripcion_glosa'] = 'Nueva factura';
        }

    } else {
        $errorInfo = $stmtFox->errorInfo();
        $response['message'] = 'Error al ejecutar la consulta en FoxPro: ' . ($errorInfo[2] ?? 'Error desconocido');
        $response['datos']['tipo_glosa'] = 'ERR_DB';
        error_log("Error FoxPro en buscar_factura_fox.php: " . ($errorInfo[2] ?? 'Error desconocido'));
    }

} catch (PDOException $e) {
    $response['message'] = 'Error de conexión o consulta a FoxPro: ' . $e->getMessage();
    $response['datos']['tipo_glosa'] = 'ERR_PDO';
    error_log("PDOException en buscar_factura_fox.php: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = 'Error general en el servidor: ' . $e->getMessage();
    $response['datos']['tipo_glosa'] = 'ERR_GEN';
    error_log("Exception en buscar_factura_fox.php: " . $e->getMessage());
}

echo json_encode($response);
?>
