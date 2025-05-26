<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/conectFox.php';
header('Content-Type: application/json');

$default_datos = [
    'nit' => '',
    'tipo_glosa' => '',
    'gl_docn' => null,
    'descripcion_glosa' => '',
    'estado_consolidado' => ''
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
            // Ordenar por fecha descendente y tomar el más reciente
            usort($resultados, fn($a, $b) => strtotime($b['gl_fecha']) <=> strtotime($a['gl_fecha']));
            $resultadoFinal = $resultados[0];

            $glDocn = $resultadoFinal['gl_docn'] ?? null;
            $estadoConsolidado = 'R2'; // Valor por defecto

            if ($glDocn !== null) {
                $sqlDet = "SELECT estatus1 FROM glo_det WHERE gl_docn = ?";
                $stmtDet = $pdoFox->prepare($sqlDet);
                $stmtDet->bindValue(1, $glDocn, PDO::PARAM_INT);
                $stmtDet->execute();
                $estados = $stmtDet->fetchAll(PDO::FETCH_COLUMN);
                $stmtDet->closeCursor();

                if ($estados) {
                    $estadosUnicos = array_unique($estados);
                    if (in_array('CO', $estadosUnicos) || in_array('R3', $estadosUnicos)) {
                        $estadoConsolidado = 'CO';
                    } elseif (in_array('C2', $estadosUnicos) || in_array('R2', $estadosUnicos)) {
                        $estadoConsolidado = 'R3';
                    } elseif (in_array('NU', $estadosUnicos) || in_array('C1', $estadosUnicos)) {
                        $estadoConsolidado = 'R2';
                    }
                }
            }

            $response['success'] = true;
            $response['message'] = 'Datos encontrados.';
            $response['datos'] = [
                'nit' => $resultadoFinal['tercero'] ?? '',
                'tipo_glosa' => $resultadoFinal['tipo'] ?? '',
                'gl_docn' => $glDocn,
                'descripcion_glosa' => $resultadoFinal['nom_tipo'] ?? '',
                'estado_consolidado' => $estadoConsolidado
            ];
        } else {
            // Factura no existe → tipo_glosa = NU, estado_consolidado = NU
            $response['success'] = false;
            $response['message'] = 'Factura no encontrada en FoxPro.';
            $response['datos'] = [
                'tipo_glosa' => 'NU',
                'descripcion_glosa' => 'Nueva factura',
                'estado_consolidado' => 'NU',
                'nit' => '',
                'gl_docn' => null
            ];
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
