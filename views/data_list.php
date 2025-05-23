<?php
// --- INICIALIZACIÓN SEGURA DE VARIABLES ESPERADAS DEL CONTROLADOR ---
$page = $page ?? 1; 
$totalPages = $totalPages ?? 1; 
$rows = $rows ?? []; 
$idsActualizados = $idsActualizados ?? []; // Para resaltado, puede venir de GET o ser pasado por el controlador

// Lógica para tomar 'ids' del GET para resaltado si no se pasó directamente $idsActualizados
// Esto asegura que si la URL tiene ?ids=..., se usen para resaltar.
if (empty($idsActualizados) && isset($_GET['ids']) && !empty(trim($_GET['ids']))) {
    $idsActualizadosGet = explode(',', trim($_GET['ids']));
    $idsActualizados = array_map('intval', $idsActualizadosGet);
    $idsActualizados = array_filter($idsActualizados, function($id) { return $id > 0; });
}
// --- FIN INICIALIZACIÓN ---


// --- RECOLECCIÓN DE IDS DE LA PÁGINA ACTUAL PARA EL FORMULARIO SYNC ---
$ids_en_pagina_actual = [];
if (!empty($rows)) {
    foreach ($rows as $row) {
        // Asumimos que DataModel ahora selecciona 'id' de 'factura_glosas' como 'id' principal
        if (isset($row['id']) && is_numeric($row['id']) && (int)$row['id'] > 0) { 
            $ids_en_pagina_actual[] = (int)$row['id'];
        }
    }
}
$ids_para_sync_string = implode(',', array_unique($ids_en_pagina_actual)); // array_unique por si acaso
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Trazabilidad Web - Datos Paginados</title>
    <style>
        h1 { font-size: 2em; color: #333; text-align: center; }
        body { font-family: sans-serif; margin: 20px; }
        .resaltado { background-color: #ffff99 !important; /* Amarillo claro, !important para asegurar */ }
        button {
            background-color: #007bff; color: white; border: none;
            padding: 10px 18px; border-radius: 5px; cursor: pointer; font-size: 1em;
            transition: background-color 0.2s;
        }
        button:hover { background-color: #0056b3; }
        button:disabled { background-color: #cccccc; cursor: not-allowed; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; box-shadow: 0 2px 3px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #dee2e6; padding: 10px 12px; text-align: left; text-align: center}
        th { background-color: #f8f9fa; font-weight: bold; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        tr:hover { background-color: #e9ecef; }
        .pagination { display: flex; justify-content: center; align-items: center; margin-top: 20px; }
        .pagination a, .pagination strong, .pagination span {
            margin: 0 4px; text-decoration: none; padding: 8px 12px;
            border: 1px solid #dee2e6; border-radius: 4px; color: #007bff;
        }
        .pagination strong { background-color: #007bff; color: white; border-color: #007bff; }
        .pagination a:hover { background-color: #e9ecef; }
        .pagination span { color: #6c757d; border: none; } /* Para los "..." */
        .form-container { margin-bottom: 25px; padding: 15px; background-color: #f9f9f9; border-radius: 5px; text-align: center;}
        .debug-output {
            background-color: #333; color: #0f0; border: 1px dashed #555; padding: 15px;
            margin-bottom: 20px; white-space: pre-wrap; font-family: monospace;
            max-height: 300px; overflow-y: auto; border-radius: 5px;
        }
    </style>
</head>
<body>

<?php
// --- INICIO DEBUG HTML OUTPUT (Controlado por constante DEBUG_SYNC en index.php/DataController) ---
// Para que este bloque de depuración se muestre, debes definir DEBUG_SYNC a true
// en tu public/index.php antes de llamar a DataController, o pasarlo de alguna manera.
// Ejemplo en index.php: define('DEBUG_VIEW', true); (y luego usar DEBUG_VIEW aquí)
if (getenv('APP_DEBUG') === 'true' || (defined('DEBUG_DATA_LIST') && DEBUG_DATA_LIST)) { // Usar variable de entorno o constante
    echo "<div class='debug-output'><strong>DEBUGGING data_list.php:</strong>\n";
    echo "Página actual (page): " . htmlspecialchars((string)$page) . "\n";
    echo "Total de Páginas (totalPages): " . htmlspecialchars((string)$totalPages) . "\n";
    echo "\nContenido de \$rows (primeros 2, si existen):\n";
    if (!empty($rows)) { print_r(array_slice($rows, 0, 2)); } else { echo "El array \$rows está vacío.\n"; }
    echo "\nIDs recolectados de esta página (\$ids_en_pagina_actual):\n"; print_r($ids_en_pagina_actual);
    echo "\nString de IDs para enviar a Sync.php (\$ids_para_sync_string):\n"; var_dump($ids_para_sync_string);
    echo "\nIDs resaltados pasados por GET/Controlador (\$idsActualizados):\n"; print_r($idsActualizados);
    echo "</div>";
}
// --- FIN DEBUG HTML OUTPUT ---
?>

<h1>Trazabilidad Web - Glosas</h1>

<div class="form-container">
    <form method="post" action="Sync.php" id="syncForm">
        <?php if (!empty($ids_para_sync_string)): ?>
            <input type="hidden" name="sync_ids" value="<?= htmlspecialchars($ids_para_sync_string) ?>">
        <?php endif; ?>
        
        <?php if (isset($page) && is_numeric($page) && $page > 0): ?>
            <input type="hidden" name="current_page" value="<?= htmlspecialchars((string)$page) ?>">
        <?php endif; ?>
        
        <button type="submit" <?php if (empty($ids_para_sync_string)) echo 'disabled'; ?>>
            🔄 Sincronizar Datos de ESTA PÁGINA con FoxPro
        </button>
        <?php if (empty($ids_para_sync_string)): ?>
            <br><small style="color: #6c757d;">(No hay datos en esta página para sincronizar)</small>
        <?php endif; ?>
    </form>
</div>

<table>
    <thead>
    <tr>
        <th>ID Factura</th> <!-- El id de factura_glosas.fg.id -->
        <th>Prefijo Fact.</th>
        <th>N° Factura</th>
        <th>NIT Entidad</th>
        <th>Entidad</th>
        <th>Fecha de contestación</th> <!-- La fecha que se usará para d.freg -->
        <th>Cuenta de Cobro</th>
        <th>Fecha de radicado</th>
    </tr>
    </thead>
    <tbody>
    <?php if (!empty($rows)): ?>
        <?php foreach ($rows as $row): ?>
            <?php 
            $esFilaResaltada = isset($row['id']) && in_array((int)$row['id'], $idsActualizados, true);
            $claseFila = $esFilaResaltada ? 'resaltado' : '';
            ?>
            <tr class="<?= $claseFila ?>">
                <td><?= isset($row['id']) ? htmlspecialchars((string)$row['id']) : 'N/A' ?></td>
                <td><?= isset($row['serie']) ? htmlspecialchars($row['serie']) : '' ?></td>
                <td><?= isset($row['docn']) ? htmlspecialchars((string)$row['docn']) : '' ?></td>
                <td><?= isset($row['nit_tercero']) ? htmlspecialchars((string)$row['nit_tercero']) : '' ?></td>
                <td><?= isset($row['nom_tercero']) ? htmlspecialchars($row['nom_tercero']) : '' ?></td>
                <td><?= isset($row['f_respuesta_g']) ? htmlspecialchars( (new DateTime($row['f_respuesta_g']))->format('Y-m-d') ) : '' ?></td> <!-- Mostrar solo fecha -->
                <td><?= isset($row['cuenta_cobro']) ? htmlspecialchars((string)$row['cuenta_cobro']) : '' ?></td>
                <td><?= isset($row['fecha_resp']) ? htmlspecialchars( ($row['fecha_resp'] ? (new DateTime($row['fecha_resp']))->format('Y-m-d') : '') ) : '' ?></td> <!-- Mostrar solo fecha si no es nula/vacía -->
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="8">No hay datos para mostrar.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>">Anterior</a>
    <?php endif; ?>

    <?php 
    $rangoVisible = 2; 
    $inicio = max(1, $page - $rangoVisible);
    $fin = min($totalPages, $page + $rangoVisible);

    if ($inicio > 1) echo "<a href='?page=1'>1</a>";
    if ($inicio > 2) echo "<span>...</span>";

    for ($i = $inicio; $i <= $fin; $i++): ?>
        <?php if ($i == $page): ?>
            <strong><?= $i ?></strong>
        <?php else: ?>
            <a href="?page=<?= $i ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($fin < $totalPages - 1) echo "<span>...</span>"; ?>
    <?php if ($fin < $totalPages && $fin !== $totalPages ) echo "<a href='?page={$totalPages}'>{$totalPages}</a>"; ?>


    <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?>">Siguiente</a>
    <?php endif; ?>
</div>

</body>
</html>