<!-- reporte.php -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Reportes de Productividad</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <main>
        <header>
            <h1>Módulo de Reportes</h1>
            <!-- Menú de Navegación -->
            <nav class="report-nav">
                <button class="nav-button active" data-report="ingreso">Ingreso</button>
                <button class="nav-button" data-report="analistas">Analistas</button>
                <button class="nav-button" data-report="erp">Radicación ERP</button>
            </nav>
        </header>

        <section class="filtros-container">
            <form id="filtro-form">
                <div class="form-group">
                    <label for="fecha_inicio">Fecha Inicio:</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio">
                </div>
                <div class="form-group">
                    <label for="fecha_fin">Fecha Fin:</label>
                    <input type="date" id="fecha_fin" name="fecha_fin">
                </div>
                <button type="submit">Generar Reporte</button>
            </form>
        </section>

        <section id="dashboard-container">
            <!-- El contenido dinámico se cargará aquí -->
            <div class="loader">Cargando datos...</div>
        </section>
    </main>

    <script src="assets/js/main.js"></script>
</body>
</html>