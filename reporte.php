<!-- reporte.php -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo de Reportes de Productividad</title>

    <!-- LÍNEAS NUEVAS PARA IMPORTAR LAS FUENTES -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Custom Tailwind config (optional, but good for fonts) -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        montserrat: ['Montserrat', 'sans-serif'],
                        lato: ['Lato', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="font-lato bg-gray-100 text-gray-800 p-4 sm:p-6 md:p-8">
    <main class="bg-white shadow-lg rounded-xl overflow-hidden">
        <header class="text-center py-6 px-4 bg-blue-600 text-white">
            <h1 class="text-3xl font-montserrat font-bold mb-4">Productividad de la oficina de glosas por areas</h1>
            <!-- Menú de Navegación -->
            <nav class="report-nav flex justify-center space-x-4 border-b border-blue-700 pb-2">
                <button class="nav-button px-6 py-2 rounded-full text-lg font-montserrat font-semibold transition-all duration-200 ease-in-out bg-blue-500 text-white hover:bg-blue-600" data-report="ingreso">Ingreso</button>
                <button class="nav-button px-6 py-2 rounded-full text-lg font-montserrat font-semibold transition-all duration-200 ease-in-out bg-blue-500 text-white hover:bg-blue-600" data-report="analistas">Respuesta  </button>
                <div class="relative" id="radicacion-menu">
                    <button id="radicacion-button" class="nav-button-parent px-6 py-2 rounded-full text-lg font-montserrat font-semibold transition-all duration-200 ease-in-out bg-blue-500 text-white hover:bg-blue-600 flex items-center">
                        Radicación
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    <div id="radicacion-submenu" class="absolute mt-2 w-72 bg-white rounded-md shadow-lg z-10 hidden ring-1 ring-black ring-opacity-5 focus:outline-none" tabindex="-1">
                        <div class="py-1" role="none">
                            <button class="nav-button text-gray-700 block w-full text-left px-4 py-3 text-base font-montserrat hover:bg-gray-100" data-report="erp" role="menuitem" tabindex="-1">Cuentas de cobro radicadas</button>
                            <button class="nav-button text-gray-700 block w-full text-left px-4 py-3 text-base font-montserrat hover:bg-gray-100" data-report="erp_proceso" role="menuitem" tabindex="-1">Cuentas de cobro en proceso</button>
                            <button class="nav-button text-gray-700 block w-full text-left px-4 py-3 text-base font-montserrat hover:bg-gray-100" data-report="glosas_sin_radicar" role="menuitem" tabindex="-1">Glosas sin radicar</button>
                        </div>
                    </div>
                </div>
            </nav>
        </header>

        <section class="filtros-container p-6 bg-gray-50 border-b border-gray-200">
            <form id="filtro-form" class="flex flex-col sm:flex-row sm:items-end justify-center gap-4">
                <div class="form-group flex flex-col w-full sm:w-auto">
                    <label for="fecha_inicio" class="text-sm font-semibold text-gray-700 mb-1">Fecha Inicio:</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" required class="p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                </div>
                <div class="form-group flex flex-col w-full sm:w-auto">
                    <label for="fecha_fin" class="text-sm font-semibold text-gray-700 mb-1">Fecha Fin:</label>
                    <input type="date" id="fecha_fin" name="fecha_fin" required class="p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                </div>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white font-bold rounded-md hover:bg-blue-700 transition-colors duration-200 ease-in-out w-full sm:w-auto">Generar Reporte</button>
            </form>
        </section>

        <section id="dashboard-container" class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- El contenido dinámico se cargará aquí -->
            <div class="loader col-span-full text-center text-gray-500 p-8 bg-gray-50 rounded-lg shadow-inner">Cargando datos...</div>
        </section>
    </main>

    <!-- Librería SheetJS para exportar a XLSX -->
    <script src="https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js"></script>

    <script src="assets/js/main.js"></script>

    <!-- ======== MODAL DE DETALLES DE FACTURA ======== -->
    <div id="modal-detalles" class="fixed inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center z-50 p-12 hidden">
        <div class="modal-content bg-white shadow-xl w-full max-h-[90vh] flex flex-col rounded-lg">
            <div class="modal-header flex justify-between items-center p-4 border-b border-gray-200 bg-gray-50">
                <h2 id="detalles-modal-title" class="text-xl font-montserrat font-semibold text-gray-800">Detalle de Facturas</h2>
                <button id="close-detalles-modal-button" class="close-button text-gray-500 hover:text-gray-800 text-3xl leading-none font-semibold">&times;</button>
            </div>
            <div id="detalles-list" class= "modal-body overflow-y-auto flex-grow">
                <!-- El contenido se generará con JavaScript -->
            </div>
        </div>
    </div>
    <!-- ============================================= -->
</body>
</html>