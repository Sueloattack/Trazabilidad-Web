// assets/js/main.js (Versión simplificada y final)

document.addEventListener('DOMContentLoaded', () => {
    
    // --- 1. SELECTORES DE ELEMENTOS DEL DOM ---
    const filtroForm = document.getElementById('filtro-form');
    const dashboardContainer = document.getElementById('dashboard-container');
    const fechaInicioInput = document.getElementById('fecha_inicio');
    const fechaFinInput = document.getElementById('fecha_fin');
    const navButtons = document.querySelectorAll('.nav-button');
    const radicacionMenu = document.getElementById('radicacion-menu');
    const radicacionButton = document.getElementById('radicacion-button');
    const radicacionSubmenu = document.getElementById('radicacion-submenu');
    
    // Selectores para el modal de Detalles de Factura (único modal ahora)
    const modalDetalles = document.getElementById('modal-detalles');
    const closeDetallebaseodalButton = document.getElementById('close-detalles-modal-button');
    const detallesListDiv = document.getElementById('detalles-list');
    const detallebaseodalTitle = document.getElementById('detalles-modal-title');
    const modalHeader = document.querySelector('.modal-header'); // [NUEVO] Selector para la cabecera del modal

    // --- 2. ESTADO DE LA APLICACIÓN ---
    let activeReport = 'ingreso';
    let detallesPorResponsable = {}; // Guardará el mapa enriquecido del reporte principal

    // --- 3. FUNCIONES ---

    /**
     * Establece el rango de fechas por defecto a vacío.
     */
    const setDefaultDates = () => {
        fechaFinInput.value = '';
        fechaInicioInput.value = '';
    };

    /**
     * Llama a la API principal para obtener los datos agregados del reporte.
     */
    const fetchReporte = async (reporte, fechaInicio = '', fechaFin = '') => {
        dashboardContainer.innerHTML = '<div class="col-span-full text-center text-gray-500 p-8 bg-gray-50 rounded-lg shadow-inner">Cargando datos...</div>';
        detallesPorResponsable = {}; 

        try {
            const params = new URLSearchParams();
            if (fechaInicio) params.append('fecha_inicio', fechaInicio);
            if (fechaFin) params.append('fecha_fin', fechaFin);
            
            const url = `api/reporte_${reporte}.php?${params.toString()}`;

            const response = await fetch(url);
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Error en el servidor: ${response.statusText}. Respuesta: ${errorText}`);
            }
            const responseData = await response.json();
            
            detallesPorResponsable = responseData.detalle_mapa || {};
            renderContent(responseData, reporte);

        } catch (error) {
            console.error(`Error al obtener el reporte '${reporte}':`, error);
            dashboardContainer.innerHTML = `<div class="col-span-full text-center text-red-600 p-8 bg-red-50 rounded-lg shadow-inner">Error al cargar el reporte.<br><pre>${error.message}</pre></div>`;
        }
    };
    
    /**
     * Director que renderiza el contenido principal del dashboard.
     */
    const renderContent = (responseData, reporte) => {
        dashboardContainer.innerHTML = '';

        if (!responseData || !responseData.data || responseData.data.length === 0) {
            dashboardContainer.innerHTML += `<div class="col-span-full text-center text-gray-500 p-8 bg-gray-50 rounded-lg shadow-inner">No se encontraron datos para el reporte de '${reporte}' en el período seleccionado.</div>`;
        } else {
            switch (reporte) {
                case 'ingreso':
                case 'analistas':
                    renderReporteDetallado(responseData.data, reporte);
                    break;
                case 'erp':
                case 'erp_proceso':
                    renderReporteERP(responseData.data);
                    break;
                case 'glosas_sin_radicar':
                    renderReporteGlosasSinRadicar(responseData.data);
                    break;
                default:
                    dashboardContainer.innerHTML += `<div class="col-span-full text-center text-red-600 p-8 bg-red-50 rounded-lg shadow-inner">Tipo de reporte desconocido.</div>`;
            }
        }
    };


    /**
     * Renderiza las tarjetas de datos detalladas para los reportes.
     */
    const renderReporteDetallado = (itemsData, reporte) => {
        itemsData.forEach(item => {
            const valorAceptadoLabel = reporte === 'ingreso' ? 'Valor levantado por la ERP' : 'Valor Aceptado';
            let desgloseHTML = '';
            if (item.desglose_ratificacion) {
                // Mostrar todos los estados, incluso si son cero. Añadir flex para la alineación.
                for (const [key, value] of Object.entries(item.desglose_ratificacion)) {
                    desgloseHTML += `<p class="flex justify-between text-base text-gray-700"><span>${key.toUpperCase()}</span><strong class="font-semibold text-gray-900">${value.cantidad_facturas} / ${value.cantidad} / ${value.valor}</strong></p>`;
                }
            }
            dashboardContainer.innerHTML += `
                <div class="item-card bg-white rounded-lg shadow-md hover:shadow-xl transition-all duration-300 ease-in-out cursor-pointer border border-gray-200 hover:border-blue-500" data-responsable="${item.responsable}">
                    <div class="card-header p-4 border-b border-gray-200 bg-gray-50 flex items-center justify-center h-24">
                        <h3 class="text-xl font-montserrat font-semibold text-gray-800 text-center">${item.responsable}</h3>
                    </div>
                    <div class="p-4 space-y-3">
                        <h4 class="text-xs font-montserrat font-medium text-gray-500 uppercase tracking-wider">Resumen General</h4>
                        <p class="flex justify-between text-base text-gray-700"><span>Cantidad Ingresada:</span><strong class="font-semibold text-gray-900">${item.cantidad_glosas_ingresadas}</strong></p>
                        <p class="flex justify-between text-base text-gray-700"><span>Valor Total Ingresado:</span><strong class="font-semibold text-gray-900">${item.valor_total_glosas}</strong></p>
                    </div>
                    <hr class="border-gray-200 mx-4">
                    <div class="p-4 space-y-3">
                        <h4 class="text-xs font-montserrat font-medium text-gray-500 uppercase tracking-wider">Promedios (${item.promedios.periodo})</h4>
                        <p class="flex justify-between text-base text-gray-700"><span>Promedio Cantidad:</span><strong class="font-semibold text-gray-900">${item.promedios.promedio_cantidad}</strong></p>
                        <p class="flex justify-between text-base text-gray-700"><span>Promedio Valor:</span><strong class="font-semibold text-gray-900">${item.promedios.promedio_valor}</strong></p>
                    </div>
                    <hr class="border-gray-200 mx-4">
                    <div class="p-4 space-y-3">
                        <h4 class="text-xs font-montserrat font-medium text-gray-500 uppercase tracking-wider">Desglose por Ratificación</h4>
                        <div class="space-y-2">${desgloseHTML}</div>
                    </div>
                    <hr class="border-gray-200 mx-4">

                    <!-- ============ NUEVA SECCIÓN DE TOTALES ============ -->
                    <div class="p-4 space-y-3 bg-blue-50 rounded-b-lg">
                        <h4 class="text-xs font-montserrat font-medium text-blue-700 uppercase tracking-wider">Totales de Ítems</h4>
                        <p class="flex justify-between text-base text-blue-800"><span>Total Ítems:</span><strong class="font-bold">${item.total_items}</strong></p>
                        <p class="flex justify-between text-base text-blue-800"><span>Valor Glosado:</span><strong class="font-bold">${item.valor_glosado}</strong></p>
                        <p class="flex justify-between text-base text-blue-800"><span>${valorAceptadoLabel}:</span><strong class="font-bold">${item.valor_aceptado}</strong></p>
                        <p class="flex justify-between text-base text-blue-800"><span>Valor Total:</span><strong class="font-bold">${item.valor_total_items}</strong></p>
                    </div>
                </div>`;
        });
    };
    
    const renderReporteERP = (data) => {
        const formatter = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 });

        dashboardContainer.innerHTML = ''; // Limpiar contenido anterior

        data.forEach(item => {
            dashboardContainer.innerHTML += `
                <div class="item-card bg-white rounded-lg shadow-md hover:shadow-xl transition-all duration-300 ease-in-out cursor-pointer border border-gray-200 hover:border-blue-500" data-responsable="${item.responsable}">
                    <div class="card-header p-4 border-b border-gray-200 bg-gray-50 flex items-center justify-center h-24">
                        <h3 class="text-xl font-montserrat font-semibold text-gray-800 text-center">${item.responsable}</h3>
                    </div>
                    <div class="p-4 space-y-3">
                        <h4 class="text-xs font-montserrat font-medium text-gray-500 uppercase tracking-wider">Resumen de Radicación</h4>
                        <p class="flex justify-between text-base text-gray-700"><span>Total Cuentas:</span><strong class="font-semibold text-gray-900">${item.total_documentos}</strong></p>
                        <p class="flex justify-between text-base text-gray-700"><span>Total Facturas:</span><strong class="font-semibold text-gray-900">${item.total_facturas_radicadas}</strong></p>
                        <hr class="border-gray-200 my-3">
                        <p class="flex justify-between text-base text-gray-700"><span>Valor Aceptado:</span><strong class="font-semibold text-gray-900">${formatter.format(item.total_aceptado)}</strong></p>
                        <p class="flex justify-between text-base text-gray-700"><span>Valor Refutado:</span><strong class="font-semibold text-gray-900">${formatter.format(item.total_refutado)}</strong></p>
                        <p class="flex justify-between text-base text-gray-700"><span>Valor Conciliado:</span><strong class="font-semibold text-gray-900">${formatter.format(item.total_conciliado)}</strong></p>
                    </div>
                </div>`;
        });
    };

    /**
     * [NUEVO] Renderiza las tarjetas para el reporte de Facturas sin Radicar.
     */
    const renderReporteGlosasSinRadicar = (data) => {
        dashboardContainer.innerHTML = ''; // Limpiar

        // Ordenar de mayor a menor cantidad de glosas totales
        data.sort((a, b) => b.total_glosas - a.total_glosas);

        // Calcular y mostrar los totalizadores
        const totalGlosas = data.reduce((sum, item) => sum + item.total_glosas, 0);
        const totalConCuenta = data.reduce((sum, item) => sum + item.con_cuenta, 0);
        const totalSinCuenta = data.reduce((sum, item) => sum + item.sin_cuenta, 0);
        
        const totalizadorHTML = `
            <div class="col-span-full grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold">Total Glosas: ${totalGlosas.toLocaleString('es-CO')}</h2>
                </div>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold">Con Cuenta: ${totalConCuenta.toLocaleString('es-CO')}</h2>
                </div>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-lg shadow-md">
                    <h2 class="text-xl font-bold">Sin Cuenta: ${totalSinCuenta.toLocaleString('es-CO')}</h2>
                </div>
            </div>
        `;
        dashboardContainer.innerHTML += totalizadorHTML;

        data.forEach(item => {
            dashboardContainer.innerHTML += `
                <div class="item-card bg-white rounded-lg shadow-md hover:shadow-xl transition-all duration-300 ease-in-out cursor-pointer border border-gray-200 hover:border-blue-500" data-responsable="${item.tercero_nombre}">
                    <div class="card-header p-4 border-b border-gray-200 bg-gray-50 flex items-center justify-center h-20">
                        <h3 class="text-xl font-montserrat font-semibold text-gray-800 text-center">${item.tercero_nombre}</h3>
                    </div>
                    <div class="p-4 space-y-3">
                        <h4 class="text-center text-xs font-montserrat font-medium text-gray-500 uppercase tracking-wider mb-2">Glosas Pendientes de Radicar</h4>
                        <p class="flex justify-between text-lg text-gray-800"><span>Total:</span><strong class="font-bold text-red-600 text-2xl">${item.total_glosas.toLocaleString('es-CO')}</strong></p>
                        <hr class="my-2">
                        <p class="flex justify-between text-base text-gray-700"><span>Con Cuenta de Cobro:</span><strong class="font-semibold text-green-600">${item.con_cuenta.toLocaleString('es-CO')}</strong></p>
                        <p class="flex justify-between text-base text-gray-700"><span>Sin Cuenta de Cobro:</span><strong class="font-semibold text-yellow-600">${item.sin_cuenta.toLocaleString('es-CO')}</strong></p>
                    </div>
                </div>`;
        });
    };

    /**
     * Muestra el modal y carga los detalles según el tipo de reporte.
     */
    const fetchDetalles = async (responsable) => {
        // [NUEVO] Limpiar botón de exportación anterior y añadir el nuevo
        const existingExportBtn = document.getElementById('export-card-btn');
        if (existingExportBtn) {
            existingExportBtn.remove();
        }
        const exportButton = document.createElement('button');
        exportButton.id = 'export-card-btn';
        exportButton.dataset.responsable = responsable;
        exportButton.className = 'ml-4 p-2 rounded-full hover:bg-gray-200 transition-colors';
        exportButton.title = 'Exportar a Excel';
        exportButton.innerHTML = '📄'; // Icono de Excel/documento
        modalHeader.insertBefore(exportButton, closeDetallebaseodalButton);

        const detalles = detallesPorResponsable[responsable];
        if (!detalles || detalles.length === 0) {
            detallesListDiv.innerHTML = '<p>No se encontró mapa de detalles para este responsable.</p>';
            return;
        }

        // Lógica bifurcada
        if (activeReport === 'erp' || activeReport === 'erp_proceso') {
            detallebaseodalTitle.textContent = `Detalle de Documentos para ${responsable}`;
            detallesListDiv.innerHTML = '<div class="text-center text-gray-500 p-8 bg-gray-50 rounded-lg shadow-inner">Cargando detalles...</div>';
            modalDetalles.classList.remove('hidden');
            modalDetalles.classList.add('flex');
            renderERPDetallebaseodal(detalles);
            return;
        }

        if (activeReport === 'glosas_sin_radicar') {
            detallebaseodalTitle.textContent = `Glosas sin Radicar para ${responsable}`;
            detallesListDiv.innerHTML = '<div class="text-center text-gray-500 p-8 bg-gray-50 rounded-lg shadow-inner">Cargando detalles...</div>';
            modalDetalles.classList.remove('hidden');
            modalDetalles.classList.add('flex');
            renderGlosasSinRadicarDetalleModal(detalles);
            return;
        }

        // Lógica para reportes de Ingreso y Analistas
        detallebaseodalTitle.textContent = `Detalle de Documentos para ${responsable}`;
        detallesListDiv.innerHTML = '<div class="text-center text-gray-500 p-8 bg-gray-50 rounded-lg shadow-inner">Cargando detalles...</div>';
        modalDetalles.classList.remove('hidden');
        modalDetalles.classList.add('flex');
        try {
            const response = await fetch('api/reporte_detalles.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ detalles: detalles })
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'La respuesta del servidor no fue exitosa.');
            }

            const mapaNombres = await response.json();
            const estatusAceptado = (activeReport === 'analistas') ? 'ai' : 'ae';

            renderDetallebaseodal(detalles, mapaNombres, estatusAceptado, responsable);

        } catch (error) {
            console.error('Error al obtener los detalles:', error);
            detallesListDiv.innerHTML = `<p class="error">Error al cargar los detalles: ${error.message}</p>`;
        }
    };

    /**
     * [MODIFICADO] Renderiza los datos del reporte ERP en tarjetas de resumen interactivas.
     */

    /**
     * [MODIFICADO] Renderiza el contenido del modal de detalles para el reporte ERP con filas expandibles.
     */
    const renderERPDetallebaseodal = (documentos) => {
        const formatter = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 });
        
        let tableHTML = `
            <table class="min-w-full bg-white border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-gray-100 sticky top-0 z-10">
                    <tr>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider"></th><!-- Columna para el botón de expandir -->
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">No.</th>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Cuenta de Cobro</th>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Entidad</th>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Facturas Radicadas</th>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Fecha Creación</th>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Fecha Radicación</th>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Valor Refutado</th>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Valor Aceptado</th>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Valor Conciliado</th>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Observación</th>
                    </tr>
                </thead>
                <tbody>`;

        const totales = { facturas: 0, refutado: 0, aceptado: 0, conciliado: 0 };
        let rowNum = 1;

        documentos.forEach(doc => {
            const cleanGrDocn = doc.gr_docn.replace(/[^a-zA-Z0-9]/g, '-');
            const facturasCount = doc.facturas_por_cuenta;

            tableHTML += `
                <tr class="border-b border-gray-200 hover:bg-gray-50 even:bg-gray-50">
                    <td class="py-3 px-4 text-center whitespace-nowrap">
                        ${facturasCount > 0 ? `<button class="btn-expandir-sub text-blue-600 hover:text-blue-800 font-bold text-lg focus:outline-none" data-gr_docn="${doc.gr_docn}" data-target-id="sub-detalle-${cleanGrDocn}">+</button>` : ''}
                    </td>
                    <td class="py-3 px-4 text-center text-base text-gray-800 whitespace-nowrap">${rowNum++}</td>
                    <td class="py-3 px-4 text-center text-base text-gray-800 whitespace-nowrap">${doc.gr_docn}</td>
                    <td class="py-3 px-4 text-center text-base text-gray-800 whitespace-nowrap">${doc.tercero_nombre}</td>
                    <td class="py-3 px-4 text-center text-base text-gray-800 whitespace-nowrap"><strong>${facturasCount}</strong></td>
                    <td class="py-3 px-4 text-center text-base text-gray-800 whitespace-nowrap">${doc.freg}</td>
                    <td class="py-3 px-4 text-center text-base text-gray-800 whitespace-nowrap">${doc.fecha_rep === '1899-12-30' ? '<span class="text-red-500 font-semibold">No Radicado</span>' : doc.fecha_rep}</td>
                    <td class="py-3 px-4 text-center text-base text-gray-800 whitespace-nowrap">${formatter.format(doc.vr_tref)}</td>
                    <td class="py-3 px-4 text-center text-base text-gray-800 whitespace-nowrap">${formatter.format(doc.vr_tace)}</td>
                    <td class="py-3 px-4 text-center text-base text-gray-800 whitespace-nowrap">${formatter.format(doc.vr_tcon)}</td>
                    <td class="py-3 px-4 text-center text-base text-gray-800" style="max-width: 200px; white-space: normal;">${doc.observac}</td>
                </tr>
                <tr class="fila-sub-detalle bg-gray-50" id="sub-detalle-${cleanGrDocn}" style="display: none;">
                    <td colspan="11" class="p-0">
                        <div class="sub-detalle-container p-4 border-t border-gray-200">Cargando facturas...</div>
                    </td>
                </tr>`;
            
            totales.facturas += facturasCount;
            totales.refutado += doc.vr_tref;
            totales.aceptado += doc.vr_tace;
            totales.conciliado += doc.vr_tcon;
        });

        tableHTML += `
                </tbody>
                <tfoot class="bg-gray-100 sticky bottom-0 z-10">
                    <tr>
                        <th colspan="4" class="py-3 px-4 text-center text-base font-montserrat font-bold text-gray-800 uppercase tracking-wider">TOTALES</th>
                        <th class="py-3 px-4 text-center text-base font-montserrat font-bold text-gray-800 uppercase tracking-wider">${totales.facturas}</th>
                        <th colspan="2" class="py-3 px-4"></th>
                        <th class="py-3 px-4 text-center text-base font-montserrat font-bold text-gray-800 uppercase tracking-wider">${formatter.format(totales.refutado)}</th>
                        <th class="py-3 px-4 text-center text-base font-montserrat font-bold text-gray-800 uppercase tracking-wider">${formatter.format(totales.aceptado)}</th>
                        <th class="py-3 px-4 text-center text-base font-montserrat font-bold text-gray-800 uppercase tracking-wider">${formatter.format(totales.conciliado)}</th>
                        <th class="py-3 px-4"></th>
                    </tr>
                </tfoot>
            </table>`;

        detallesListDiv.innerHTML = tableHTML;
    };

    /**
     * [MODIFICADO] Renderiza la tabla de sub-detalles (facturas) de forma integrada.
     */
    const renderERPSubDetalles = (data, container) => {
        if (!data || data.length === 0) {
            container.innerHTML = '<p style="text-align:center; padding: 1rem;">No se encontraron facturas para esta cuenta de cobro.</p>';
            return;
        }

        let tableHTML = `
            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                <thead class="bg-gray-100 sticky top-12 z-10">
                    <tr>
                        <th class="py-2 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">No.</th>
                        <th class="py-2 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Factura</th>
                        <th class="py-2 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Fecha Glosa</th>
                        <th class="py-2 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Estatus</th>
                    </tr>
                </thead>
                <tbody>`;
        
        let rowNum = 1;
        data.forEach(item => {
            tableHTML += `
                <tr class="border-b border-gray-200 hover:bg-gray-50 even:bg-gray-50">
                    <td class="py-2 px-4 text-center text-base text-gray-800 whitespace-nowrap">${rowNum++}</td>
                    <td class="py-2 px-4 text-center text-base text-gray-800 whitespace-nowrap">${item.fc_serie.trim()}${item.fc_docn.trim()}</td>
                    <td class="py-2 px-4 text-center text-base text-gray-800 whitespace-nowrap">${item.fecha_gl}</td>
                    <td class="py-2 px-4 text-center text-base text-gray-800 whitespace-nowrap">${item.estatus1}</td>
                </tr>`;
        });

        tableHTML += '</tbody></table>';
        container.innerHTML = tableHTML;
    };
    
    /**
     * Renderiza el contenido del modal de detalles con los nuevos títulos y formato de datos.
     * @param {object} detallebaseap - El mapa de facturas enriquecido.
     * @param {object} mapaNombres - El diccionario de nombres de terceros.
     */
    const renderDetallebaseodal = (detallebaseap, mapaNombres, estatusAceptado, responsable) => {
        const totalEventos = Object.keys(detallebaseap).length;
        const nombreReporte = activeReport === 'ingreso' ? 'Eventos de Ingreso' : 'Eventos de Respuesta';
        detallebaseodalTitle.innerHTML = `Detalle para ${responsable} <span class="text-base font-normal text-gray-500 ml-4">(${totalEventos} ${nombreReporte})</span>`;

        let filasProcesadas = [];
        for (const [eventoKey, data] of Object.entries(detallebaseap)) {
            const itemsList = data.items;
            if (!Array.isArray(itemsList)) continue;

            let valorGlosadoNum = 0;
            itemsList.forEach(item => {
                if (item.estatus1 !== 'ai' && item.estatus1 !== 'ae') {
                    valorGlosadoNum += item.vr_glosa;
                }
            });

            filasProcesadas.push({
                eventoKey: eventoKey,
                itemsList: itemsList,
                valorGlosadoNum: valorGlosadoNum
            });
        }

        filasProcesadas.sort((a, b) => b.valorGlosadoNum - a.valorGlosadoNum);
        
        const valorAceptadoHeader = activeReport === 'ingreso' ? 'Valor levantado por la erp' : 'Valor Aceptado';
        let tableHTML = `
            <table class="min-w-full bg-white border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-gray-100 sticky top-0 z-10">
                    <tr>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">No</th>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Glosa</th>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Entidad</th>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Total Ítems</th>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Desglose por estado</th>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Valor Glosado</th>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">${valorAceptadoHeader}</th>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Total Reclamado</th>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Fecha Glosa</th>
                    </tr>
                </thead>
                <tbody>`;

        let numeroFila = 1;
        const totales = { items: 0, glosado: 0.0, aceptado: 0.0, reclamado: 0.0 };
        const formatter = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 });

        filasProcesadas.forEach(fila => {
            const { eventoKey, itemsList } = fila;
            const parts = eventoKey.split('-');
            const documento = `${parts[0]}${parts[1]}`;
            const codigoTercero = parts[2];
            const nombreTercero = mapaNombres[codigoTercero] || codigoTercero;
            const fechaGlosa = itemsList.length > 0 ? itemsList[0].fecha_gl : 'N/A';
            
            const totalItems = itemsList.length;
            const desglose = { estatusCounts: {}, valorGlosado: 0.0, valorAceptado: 0.0 };

            itemsList.forEach(item => {
                desglose.estatusCounts[item.estatus1] = (desglose.estatusCounts[item.estatus1] || 0) + 1;
                if (item.estatus1 === 'ai' || item.estatus1 === 'ae') {
                    desglose.valorAceptado += item.vr_glosa;
                } else {
                    desglose.valorGlosado += item.vr_glosa;
                }
            });

            const totalReclamadoFila = desglose.valorGlosado + desglose.valorAceptado;
            const desgloseStr = Object.entries(desglose.estatusCounts).map(([est, count]) => `${est.toUpperCase()} (${count})`).join(', ');

            tableHTML += `
                <tr class="border-b border-gray-200 hover:bg-gray-50 even:bg-gray-50">
                    <td class="py-2 px-4 text-center text-base text-gray-800 whitespace-nowrap">${numeroFila++}</td>
                    <td class="py-2 px-4 text-center text-base text-gray-800 whitespace-nowrap">${documento}</td>
                    <td class="py-2 px-4 text-center text-base text-gray-800 whitespace-nowrap">${nombreTercero}</td>
                    <td class="py-2 px-4 text-center text-base text-gray-800 whitespace-nowrap">${totalItems}</td>
                    <td class="py-2 px-4 text-center text-base text-gray-800 whitespace-nowrap">${desgloseStr}</td>
                    <td class="py-2 px-4 text-center text-base text-gray-800 whitespace-nowrap">${formatter.format(desglose.valorGlosado)}</td>
                    <td class="py-2 px-4 text-center text-base text-gray-800 whitespace-nowrap">${formatter.format(desglose.valorAceptado)}</td>
                    <td class="py-2 px-4 text-center text-base text-gray-800 whitespace-nowrap">${formatter.format(totalReclamadoFila)}</td>
                    <td class="py-2 px-4 text-center text-base text-gray-800 whitespace-nowrap">${fechaGlosa}</td>
                </tr>`;

            totales.items += totalItems;
            totales.glosado += desglose.valorGlosado;
            totales.aceptado += desglose.valorAceptado;
            totales.reclamado += totalReclamadoFila;
        });
        
        tableHTML += '</tbody>';
        tableHTML += `
            <tfoot class="bg-gray-100 sticky bottom-0 z-10">
                <tr>
                    <th colspan="3" class="py-3 px-4 text-center text-base font-montserrat font-bold text-gray-800 uppercase tracking-wider">TOTALES</th>
                    <th class="py-3 px-4 text-center text-base font-montserrat font-bold text-gray-800 uppercase tracking-wider">${totales.items.toLocaleString('es-CO')}</th>
                    <th class="py-3 px-4"></th>
                    <th class="py-3 px-4 text-center text-base font-montserrat font-bold text-gray-800 uppercase tracking-wider">${formatter.format(totales.glosado)}</th>
                    <th class="py-3 px-4 text-center text-base font-montserrat font-bold text-gray-800 uppercase tracking-wider">${formatter.format(totales.aceptado)}</th>
                    <th class="py-3 px-4 text-center text-base font-montserrat font-bold text-gray-800 uppercase tracking-wider">${formatter.format(totales.reclamado)}</th>
                    <th class="py-3 px-4"></th>
                </tr>
            </tfoot>`;
        tableHTML += '</table>';
        
        detallesListDiv.innerHTML = tableHTML;
    };

    /**
     * [NUEVO] Renderiza el contenido del modal para el reporte de Facturas sin Radicar.
     */
    const renderGlosasSinRadicarDetalleModal = (glosas) => {
        let tableHTML = `
            <table class="min-w-full bg-white border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-gray-100 sticky top-0 z-10">
                    <tr>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">No.</th>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Glosa</th>
                        <th class="py-3 px-4 text-center text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Estados Pendientes</th>
                    </tr>
                </thead>
                <tbody>`;

        let rowNum = 1;
        glosas.forEach(glosa => {
            tableHTML += `
                <tr class="border-b border-gray-200 hover:bg-gray-50 even:bg-gray-50">
                    <td class="py-2 px-4 text-center text-base text-gray-800 whitespace-nowrap">${rowNum++}</td>
                    <td class="py-2 px-4 text-center text-base text-gray-800 whitespace-nowrap">${glosa.glosa}</td>
                    <td class="py-2 px-4 text-center text-base text-gray-800 whitespace-nowrap font-semibold">${glosa.items.map(item => `${item.estado} (${item.fecha_gl})`).join(', ')}</td>
                </tr>`;
        });

        tableHTML += '</tbody></table>';
        detallesListDiv.innerHTML = tableHTML;
    };

    // --- 4. MANEJADORES DE EVENTOS ---

    filtroForm.addEventListener('submit', (e) => {
        e.preventDefault();
        fetchReporte(activeReport, fechaInicioInput.value, fechaFinInput.value);
    });

    radicacionButton.addEventListener('click', (e) => {
        e.stopPropagation(); // Evita que el evento de clic en el documento se dispare inmediatamente.
        radicacionSubmenu.classList.toggle('hidden');
    });

    navButtons.forEach(button => {
        button.addEventListener('click', () => {
            const report = button.dataset.report;
            if (!report) return; // Si no es un botón de reporte, no hace nada.

            activeReport = report;

            // Gestionar estado visual de los botones
            navButtons.forEach(btn => {
                btn.classList.remove('bg-blue-700', 'text-white', 'font-bold');
                // Para los botones que no están en el submenú
                if (!btn.closest('#radicacion-submenu')) {
                    btn.classList.add('bg-blue-500', 'text-white', 'hover:bg-blue-600');
                }
            });

            if (button.closest('#radicacion-submenu')) {
                // Es un botón del submenú
                radicacionButton.classList.add('bg-blue-700', 'text-white', 'font-bold');
                radicacionButton.classList.remove('bg-blue-500', 'hover:bg-blue-600');
                button.classList.add('font-bold'); // Resalta la opción seleccionada en el submenú
                radicacionSubmenu.classList.add('hidden'); // Oculta el submenú después de la selección
            } else {
                // Es un botón principal
                button.classList.add('bg-blue-700', 'text-white', 'font-bold');
                button.classList.remove('bg-blue-500', 'hover:bg-blue-600');
                radicacionButton.classList.remove('bg-blue-700', 'text-white', 'font-bold');
                radicacionButton.classList.add('bg-blue-500', 'hover:bg-blue-600');
            }

            if (fechaInicioInput.value && fechaFinInput.value) {
                fetchReporte(activeReport, fechaInicioInput.value, fechaFinInput.value);
            } else {
                dashboardContainer.innerHTML = '<div class="col-span-full text-center text-gray-500 p-8 bg-gray-50 rounded-lg shadow-inner">Por favor, seleccione un rango de fechas y genere un reporte.</div>';
            }
        });
    });

    // Cerrar el submenú si se hace clic fuera de él
    document.addEventListener('click', (e) => {
        if (!radicacionMenu.contains(e.target)) {
            radicacionSubmenu.classList.add('hidden');
        }
    });

    // Event listener para abrir el modal de detalles al hacer clic en una tarjeta.
    dashboardContainer.addEventListener('click', (e) => {
        const card = e.target.closest('.item-card');
        if (card && card.dataset.responsable) {
            fetchDetalles(card.dataset.responsable);
        }
    });

    // [NUEVO] Event listener para el botón de exportar a Excel
    modalHeader.addEventListener('click', (e) => {
        const exportButton = e.target.closest('#export-card-btn');
        if (exportButton) {
            const responsable = exportButton.dataset.responsable;
            exportarDetalleXLSX(responsable);
        }
    });

    // Event listeners para cerrar el modal.
    const closeModal = () => {
        modalDetalles.classList.add('hidden');
        modalDetalles.classList.remove('flex');
    };

    closeDetallebaseodalButton.addEventListener('click', closeModal);
    modalDetalles.addEventListener('click', (e) => {
        // Se cierra si se hace clic en el overlay (el fondo oscuro).
        if (e.target === modalDetalles) {
            closeModal();
        }
    });

    // Event listener para expandir/colapsar sub-detalles en el modal de ERP.
    detallesListDiv.addEventListener('click', async (e) => {
        const expandButton = e.target.closest('.btn-expandir-sub');
        if (expandButton) {
            const grDocn = expandButton.dataset.gr_docn;
            const targetId = expandButton.dataset.targetId;
            const subDetalleRow = document.getElementById(targetId);

            if (!subDetalleRow) return; // Guardia de seguridad.

            const subDetalleContainer = subDetalleRow.querySelector('.sub-detalle-container');
            const isVisible = subDetalleRow.style.display !== 'none';

            if (!isVisible) {
                subDetalleRow.style.display = 'table-row';
                expandButton.textContent = '-';
                
                // Cargar datos solo si no se han cargado antes.
                if (subDetalleContainer.innerHTML.includes('Cargando')) {
                    try {
                        const subdetalleApi = activeReport === 'erp_proceso' 
                            ? `api/reporte_erp_subdetalles_proceso.php?gr_docn=${grDocn}` 
                            : `api/reporte_erp_subdetalles.php?gr_docn=${grDocn}`;

                        const response = await fetch(subdetalleApi);
                        if (!response.ok) {
                            const errorText = await response.text();
                            throw new Error(`Error al cargar sub-detalles: ${errorText}`);
                        }
                        const data = await response.json();
                        if (data.error) {
                            throw new Error(data.details || data.error);
                        }
                        renderERPSubDetalles(data, subDetalleContainer);
                    } catch (error) {
                        console.error('Error fetching sub-details:', error);
                        subDetalleContainer.innerHTML = `<p class="error" style="text-align:center; padding: 1rem;">${error.message}</p>`;
                    }
                }
            } else {
                subDetalleRow.style.display = 'none';
                expandButton.textContent = '+';
            }
        }
    });

    /**
     * [NUEVO] Exporta los datos de la tabla de detalles a un archivo .xlsx.
     * @param {string} responsable - El nombre del responsable para añadirlo como columna.
     */
    const exportarDetalleXLSX = (responsable) => {
        const table = detallesListDiv.querySelector('table');
        if (!table) {
            alert('No hay datos para exportar.');
            return;
        }

        // 1. Preparar los datos (Array de Arrays)
        const data = [];

        // 2. Añadir las cabeceras
        const headers = [];
        table.querySelectorAll('thead th').forEach(th => headers.push(th.textContent));
        headers.push('Responsable'); // Añadir la nueva cabecera
        data.push(headers);

        // 3. Añadir las filas del cuerpo de la tabla
        table.querySelectorAll('tbody tr').forEach(tr => {
            const rowData = [];
            tr.querySelectorAll('td').forEach(td => rowData.push(td.textContent));
            rowData.push(responsable); // Añadir el nombre del responsable a cada fila
            data.push(rowData);
        });

        // 4. Usar SheetJS para crear y descargar el archivo
        try {
            const worksheet = XLSX.utils.aoa_to_sheet(data);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Detalle');
            
            // Generar un nombre de archivo dinámico
            const fileName = `Detalle_${responsable.replace(/\s+/g, '_')}_${new Date().toISOString().slice(0,10)}.xlsx`;
            
            XLSX.writeFile(workbook, fileName);
        } catch (error) {
            console.error('Error al generar el archivo XLSX:', error);
            alert('Hubo un error al generar el archivo Excel.');
        }
    };

    // --- 5. INICIALIZACIÓN ---
    setDefaultDates();
    // Inicializar el estado activo del botón de Ingreso al cargar la página
    const initialActiveButton = document.querySelector(`.nav-button[data-report="${activeReport}"]`);
    if (initialActiveButton) {
        initialActiveButton.classList.remove('bg-blue-500', 'hover:bg-blue-600');
        initialActiveButton.classList.add('bg-blue-700', 'hover:bg-blue-800', 'active:bg-blue-900');
    }
    dashboardContainer.innerHTML = '<div class="col-span-full text-center text-gray-500 p-8 bg-gray-50 rounded-lg shadow-inner">Por favor, seleccione un rango de fechas y genere un reporte.</div>';
});