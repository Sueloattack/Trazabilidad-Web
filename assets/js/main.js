// assets/js/main.js (Versión simplificada y final)

document.addEventListener('DOMContentLoaded', () => {
    
    // --- 1. SELECTORES DE ELEMENTOS DEL DOM ---
    const filtroForm = document.getElementById('filtro-form');
    const dashboardContainer = document.getElementById('dashboard-container');
    const fechaInicioInput = document.getElementById('fecha_inicio');
    const fechaFinInput = document.getElementById('fecha_fin');
    const navButtons = document.querySelectorAll('.nav-button');
    
    // Selectores para el modal de Detalles de Factura (único modal ahora)
    const modalDetalles = document.getElementById('modal-detalles');
    const closeDetallesModalButton = document.getElementById('close-detalles-modal-button');
    const detallesListDiv = document.getElementById('detalles-list');
    const detallesModalTitle = document.getElementById('detalles-modal-title');

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
                    renderReporteDetallado(responseData.data);
                    break;
                case 'erp':
                    renderReporteERP(responseData.data);
                    break;
                default:
                    dashboardContainer.innerHTML += `<div class="col-span-full text-center text-red-600 p-8 bg-red-50 rounded-lg shadow-inner">Tipo de reporte desconocido.</div>`;
            }
        }
    };


    /**
     * Renderiza las tarjetas de datos detalladas para los reportes.
     */
    const renderReporteDetallado = (itemsData) => {
        itemsData.forEach(item => {
            let desgloseHTML = '';
            if (item.desglose_ratificacion) {
                for (const [key, value] of Object.entries(item.desglose_ratificacion)) {
                    if (value.cantidad > 0) {
                        desgloseHTML += `<p><span>${key.toUpperCase()}</span><span><strong>${value.cantidad}</strong> / ${value.valor}</span></p>`;
                    }
                }
            }
            if (desgloseHTML === '') { desgloseHTML = '<p>No hay desglose para este período.</p>'; }

            dashboardContainer.innerHTML += `
                <div class="item-card bg-white rounded-lg shadow-md hover:shadow-xl transition-all duration-300 ease-in-out cursor-pointer border border-gray-200 hover:border-blue-500" data-responsable="${item.responsable}">
                    <div class="p-4 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-lg font-montserrat font-semibold text-gray-800">${item.responsable}</h3>
                    </div>
                    <div class="p-4 space-y-3">
                        <h4 class="text-xs font-montserrat font-medium text-gray-500 uppercase tracking-wider">Resumen General</h4>
                        <p class="flex justify-between text-sm text-gray-700"><span>Cantidad Ingresada:</span><strong class="font-semibold text-gray-900">${item.cantidad_glosas_ingresadas}</strong></p>
                        <p class="flex justify-between text-sm text-gray-700"><span>Valor Total Ingresado:</span><strong class="font-semibold text-gray-900">${item.valor_total_glosas}</strong></p>
                    </div>
                    <hr class="border-gray-200 mx-4">
                    <div class="p-4 space-y-3">
                        <h4 class="text-xs font-montserrat font-medium text-gray-500 uppercase tracking-wider">Promedios (${item.promedios.periodo})</h4>
                        <p class="flex justify-between text-sm text-gray-700"><span>Promedio Cantidad:</span><strong class="font-semibold text-gray-900">${item.promedios.promedio_cantidad}</strong></p>
                        <p class="flex justify-between text-sm text-gray-700"><span>Promedio Valor:</span><strong class="font-semibold text-gray-900">${item.promedios.promedio_valor}</strong></p>
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
                        <p class="flex justify-between text-sm text-blue-800"><span>Total Ítems:</span><strong class="font-bold">${item.total_items}</strong></p>
                        <p class="flex justify-between text-sm text-blue-800"><span>Valor Glosado:</span><strong class="font-bold">${item.valor_glosado}</strong></p>
                        <p class="flex justify-between text-sm text-blue-800"><span>Valor Aceptado:</span><strong class="font-bold">${item.valor_aceptado}</strong></p>
                        <p class="flex justify-between text-sm text-blue-800"><span>Valor Total:</span><strong class="font-bold">${item.valor_total_items}</strong></p>
                    </div>
                </div>`;
        });
    };
    
    /**
     * Muestra el modal y carga los detalles según el tipo de reporte.
     */
    const fetchDetalles = async (responsable) => {
        detallesModalTitle.textContent = `Detalle de Documentos para ${responsable}`;
        detallesListDiv.innerHTML = '<div class="text-center text-gray-500 p-8 bg-gray-50 rounded-lg shadow-inner">Cargando detalles...</div>';
        modalDetalles.classList.remove('hidden');
        modalDetalles.classList.add('flex');
        
        const detalles = detallesPorResponsable[responsable];
        if (!detalles || detalles.length === 0) {
            detallesListDiv.innerHTML = '<p>No se encontró mapa de detalles para este responsable.</p>';
            return;
        }

        // Lógica bifurcada: ERP usa su propio renderizador, los otros van a la API de nombres.
        if (activeReport === 'erp') {
            renderERPDetallesModal(detalles);
            return;
        }

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

            renderDetallesModal(detalles, mapaNombres, estatusAceptado);

        } catch (error) {
            console.error('Error al obtener los detalles:', error);
            detallesListDiv.innerHTML = `<p class="error">Error al cargar los detalles: ${error.message}</p>`;
        }
    };

    /**
     * [MODIFICADO] Renderiza los datos del reporte ERP en tarjetas de resumen interactivas.
     */
    const renderReporteERP = (data) => {
        const formatter = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 });

        dashboardContainer.innerHTML = ''; // Limpiar contenido anterior

        data.forEach(item => {
            dashboardContainer.innerHTML += `
                <div class="item-card bg-white rounded-lg shadow-md hover:shadow-xl transition-all duration-300 ease-in-out cursor-pointer border border-gray-200 hover:border-blue-500" data-responsable="${item.responsable}">
                    <div class="p-4 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-lg font-montserrat font-semibold text-gray-800">${item.responsable}</h3>
                    </div>
                    <div class="p-4 space-y-3">
                        <h4 class="text-xs font-montserrat font-medium text-gray-500 uppercase tracking-wider">Resumen de Radicación</h4>
                        <p class="flex justify-between text-sm text-gray-700"><span>Total Cuentas:</span><strong class="font-semibold text-gray-900">${item.total_documentos}</strong></p>
                        <p class="flex justify-between text-sm text-gray-700"><span>Total Facturas:</span><strong class="font-semibold text-gray-900">${item.total_facturas_radicadas}</strong></p>
                        <hr class="border-gray-200 my-3">
                        <p class="flex justify-between text-sm text-gray-700"><span>Valor Aceptado:</span><strong class="font-semibold text-gray-900">${formatter.format(item.total_aceptado)}</strong></p>
                        <p class="flex justify-between text-sm text-gray-700"><span>Valor Refutado:</span><strong class="font-semibold text-gray-900">${formatter.format(item.total_refutado)}</strong></p>
                        <p class="flex justify-between text-sm text-gray-700"><span>Valor Conciliado:</span><strong class="font-semibold text-gray-900">${formatter.format(item.total_conciliado)}</strong></p>
                    </div>
                </div>`;
        });
    };

    /**
     * [MODIFICADO] Renderiza el contenido del modal de detalles para el reporte ERP con filas expandibles.
     */
    const renderERPDetallesModal = (documentos) => {
        const formatter = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 });
        
        let tableHTML = `
            <table class="min-w-full bg-white border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-gray-100 sticky top-0 z-10">
                    <tr>
                        <th class="py-3 px-4 text-left text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider"></th><!-- Columna para el botón de expandir -->
                        <th class="py-3 px-4 text-left text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Cuenta de Cobro</th>
                        <th class="py-3 px-4 text-left text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Entidad</th>
                        <th class="py-3 px-4 text-left text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Facturas Radicadas</th>
                        <th class="py-3 px-4 text-left text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Fecha Creación</th>
                        <th class="py-3 px-4 text-left text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Fecha Radicación</th>
                        <th class="py-3 px-4 text-left text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Valor Aceptado</th>
                    </tr>
                </thead>
                <tbody>`;

        const totales = { facturas: 0, aceptado: 0 };

        documentos.forEach(doc => {
            const cleanGrDocn = doc.gr_docn.replace(/[^a-zA-Z0-9]/g, '-');
            const facturasCount = doc.facturas_por_cuenta;

            tableHTML += `
                <tr class="border-b border-gray-200 hover:bg-gray-50 even:bg-gray-50">
                    <td class="py-3 px-4 whitespace-nowrap">
                        ${facturasCount > 0 ? `<button class="btn-expandir-sub text-blue-600 hover:text-blue-800 font-bold text-lg focus:outline-none" data-gr_docn="${doc.gr_docn}" data-target-id="sub-detalle-${cleanGrDocn}">+</button>` : ''}
                    </td>
                    <td class="py-3 px-4 text-sm text-gray-800 whitespace-nowrap">${doc.gr_docn}</td>
                    <td class="py-3 px-4 text-sm text-gray-800 whitespace-nowrap">${doc.tercero_nombre}</td>
                    <td class="py-3 px-4 text-sm text-gray-800 whitespace-nowrap"><strong>${facturasCount}</strong></td>
                    <td class="py-3 px-4 text-sm text-gray-800 whitespace-nowrap">${doc.freg}</td>
                    <td class="py-3 px-4 text-sm text-gray-800 whitespace-nowrap">${doc.fecha_rep}</td>
                    <td class="py-3 px-4 text-sm text-gray-800 whitespace-nowrap">${formatter.format(doc.vr_tace)}</td>
                </tr>
                <tr class="fila-sub-detalle bg-gray-50" id="sub-detalle-${cleanGrDocn}" style="display: none;">
                    <td colspan="7" class="p-0">
                        <div class="sub-detalle-container p-4 border-t border-gray-200">Cargando facturas...</div>
                    </td>
                </tr>`;
            
            totales.facturas += facturasCount;
            totales.aceptado += doc.vr_tace;
        });

        tableHTML += `
                </tbody>
                <tfoot class="bg-gray-100 sticky bottom-0 z-10">
                    <tr>
                        <th colspan="3" class="py-3 px-4 text-left text-sm font-montserrat font-bold text-gray-800 uppercase tracking-wider">TOTALES</th>
                        <th class="py-3 px-4 text-left text-sm font-montserrat font-bold text-gray-800 uppercase tracking-wider">${totales.facturas}</th>
                        <th colspan="2" class="py-3 px-4"></th>
                        <th class="py-3 px-4 text-left text-sm font-montserrat font-bold text-gray-800 uppercase tracking-wider">${formatter.format(totales.aceptado)}</th>
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
                <thead class="bg-gray-100">
                    <tr>
                        <th class="py-2 px-4 text-left text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Factura</th>
                        <th class="py-2 px-4 text-left text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Fecha Glosa</th>
                        <th class="py-2 px-4 text-left text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Estatus</th>
                    </tr>
                </thead>
                <tbody>`;

        data.forEach(item => {
            tableHTML += `
                <tr class="border-b border-gray-200 hover:bg-gray-50 even:bg-gray-50">
                    <td class="py-2 px-4 text-sm text-gray-800 whitespace-nowrap">${item.fc_serie.trim()}${item.fc_docn.trim()}</td>
                    <td class="py-2 px-4 text-sm text-gray-800 whitespace-nowrap">${item.fecha_gl}</td>
                    <td class="py-2 px-4 text-sm text-gray-800 whitespace-nowrap">${item.estatus1}</td>
                </tr>`;
        });

        tableHTML += '</tbody></table>';
        container.innerHTML = tableHTML;
    };
    
    /**
     * Renderiza el contenido del modal de detalles con los nuevos títulos y formato de datos.
     * @param {object} detallesMap - El mapa de facturas enriquecido.
     * @param {object} mapaNombres - El diccionario de nombres de terceros.
     */
    const renderDetallesModal = (detallesMap, mapaNombres,estatusAceptado) => {
        // --- PASO 1: Procesar y preparar los datos para ordenar ---
        let filasProcesadas = [];
        for (const [idCompuesto, itemsList] of Object.entries(detallesMap)) {
            
            let valorGlosadoNum = 0;
            itemsList.forEach(item => {
                if (item.estatus !== estatusAceptado) {
                    valorGlosadoNum += item.valor;
                }
            });
            
            filasProcesadas.push({
                idCompuesto: idCompuesto,
                itemsList: itemsList,
                valorGlosadoNum: valorGlosadoNum // Guardamos el valor numérico para ordenar
            });
        }

        // --- PASO 2: Ordenar el array de filas de mayor a menor Valor Glosado ---
        filasProcesadas.sort((a, b) => b.valorGlosadoNum - a.valorGlosadoNum);
        
        // --- PASO 3: Construir el HTML con los datos ya ordenados ---
        let tableHTML = `
            <table class="min-w-full bg-white border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-gray-100 sticky top-0 z-10">
                    <tr>
                        <th class="py-3 px-4 text-left text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">No</th>
                        <th class="py-3 px-4 text-left text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Glosa</th>
                        <th class="py-3 px-4 text-left text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Entidad</th>
                        <th class="py-3 px-4 text-left text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Total Ítems</th>
                        <th class="py-3 px-4 text-left text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Desglose por estado</th>
                        <th class="py-3 px-4 text-left text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Valor Glosado</th>
                        <th class="py-3 px-4 text-left text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Valor Aceptado</th>
                        <th class="py-3 px-4 text-left text-xs font-montserrat font-semibold text-gray-600 uppercase tracking-wider">Total Reclamado</th>
                    </tr>
                </thead>
                <tbody>`;

        let numeroFila = 1;
        const totales = { items: 0, glosado: 0.0, aceptado: 0.0, reclamado: 0.0 };
        const formatter = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 });

        filasProcesadas.forEach(fila => {
            const { idCompuesto, itemsList } = fila;
            const parts = idCompuesto.split('-');
            const documento = `${parts[0]}${parts[1]}`;
            const codigoTercero = parts[2];
            const nombreTercero = mapaNombres[codigoTercero] || codigoTercero;
            
            const totalItems = itemsList.length;
            const desglose = { estatusCounts: {}, valorGlosado: 0.0, valorAceptado: 0.0 };

            itemsList.forEach(item => {
                desglose.estatusCounts[item.estatus] = (desglose.estatusCounts[item.estatus] || 0) + 1;
                if (item.estatus === estatusAceptado) {
                    desglose.valorAceptado += item.valor;
                } else {
                    desglose.valorGlosado += item.valor;
                }
            });

            const totalReclamadoFila = desglose.valorGlosado + desglose.valorAceptado;
            
            const desgloseStr = Object.entries(desglose.estatusCounts)
                .map(([est, count]) => `${est.toUpperCase()} (${count})`)
                .join(', ');

            tableHTML += `
                <tr class="border-b border-gray-200 hover:bg-gray-50 even:bg-gray-50">
                    <td class="py-2 px-4 text-sm text-gray-800 whitespace-nowrap">${numeroFila++}</td>
                    <td class="py-2 px-4 text-sm text-gray-800 whitespace-nowrap">${documento}</td>
                    <td class="py-2 px-4 text-sm text-gray-800 whitespace-nowrap">${nombreTercero}</td>
                    <td class="py-2 px-4 text-sm text-gray-800 whitespace-nowrap">${totalItems}</td>
                    <td class="py-2 px-4 text-sm text-gray-800 whitespace-nowrap">${desgloseStr}</td>
                    <td class="py-2 px-4 text-sm text-gray-800 whitespace-nowrap">${formatter.format(desglose.valorGlosado)}</td>
                    <td class="py-2 px-4 text-sm text-gray-800 whitespace-nowrap">${formatter.format(desglose.valorAceptado)}</td>
                    <td class="py-2 px-4 text-sm text-gray-800 whitespace-nowrap">${formatter.format(totalReclamadoFila)}</td>
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
                    <th colspan="3" class="py-3 px-4 text-left text-sm font-montserrat font-bold text-gray-800 uppercase tracking-wider">TOTALES</th>
                    <th class="py-3 px-4 text-left text-sm font-montserrat font-bold text-gray-800 uppercase tracking-wider">${totales.items.toLocaleString('es-CO')}</th>
                    <th class="py-3 px-4"></th>
                    <th class="py-3 px-4 text-left text-sm font-montserrat font-bold text-gray-800 uppercase tracking-wider">${formatter.format(totales.glosado)}</th>
                    <th class="py-3 px-4 text-left text-sm font-montserrat font-bold text-gray-800 uppercase tracking-wider">${formatter.format(totales.aceptado)}</th>
                    <th class="py-3 px-4 text-left text-sm font-montserrat font-bold text-gray-800 uppercase tracking-wider">${formatter.format(totales.reclamado)}</th>
                </tr>
            </tfoot>`;
        tableHTML += '</table>';
        
        detallesListDiv.innerHTML = tableHTML;
    };

    // --- 4. MANEJADORES DE EVENTOS ---
    
    filtroForm.addEventListener('submit', (e) => {
        e.preventDefault();
        fetchReporte(activeReport, fechaInicioInput.value, fechaFinInput.value);
    });

    navButtons.forEach(button => {
        button.addEventListener('click', () => {
            navButtons.forEach(btn => {
                btn.classList.remove('bg-blue-700', 'hover:bg-blue-800', 'active:bg-blue-900');
                btn.classList.add('bg-blue-500', 'hover:bg-blue-600');
            });
            button.classList.remove('bg-blue-500', 'hover:bg-blue-600');
            button.classList.add('bg-blue-700', 'hover:bg-blue-800', 'active:bg-blue-900');
            activeReport = button.dataset.report;
            // Cargar el reporte solo si hay fechas seleccionadas, de lo contrario, mostrar mensaje
            if (fechaInicioInput.value && fechaFinInput.value) {
                fetchReporte(activeReport, fechaInicioInput.value, fechaFinInput.value);
            } else {
                dashboardContainer.innerHTML = '<div class="col-span-full text-center text-gray-500 p-8 bg-gray-50 rounded-lg shadow-inner">Por favor, seleccione un rango de fechas y genere un reporte.</div>';
            }
        });
    });

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