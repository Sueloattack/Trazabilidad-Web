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
        dashboardContainer.innerHTML = '<div class="loader">Cargando datos...</div>';
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
            dashboardContainer.innerHTML = `<div class="no-results error">Error al cargar el reporte.<br><pre>${error.message}</pre></div>`;
        }
    };
    
    /**
     * Director que renderiza el contenido principal del dashboard.
     */
    const renderContent = (responseData, reporte) => {
        dashboardContainer.innerHTML = '';

        if (!responseData || !responseData.data || responseData.data.length === 0) {
            dashboardContainer.innerHTML += `<div class="no-results">No se encontraron datos para el reporte de '${reporte}' en el período seleccionado.</div>`;
        } else {
            switch (reporte) {
                case 'ingreso':
                case 'analistas':
                    renderReporteDetallado(responseData.data);
                    break;
                case 'erp':
                    dashboardContainer.innerHTML += `<div>El renderizado para el reporte 'Radicación ERP' aún no está implementado.</div>`;
                    break;
                default:
                    dashboardContainer.innerHTML += `<div class="no-results error">Tipo de reporte desconocido.</div>`;
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

            // [CAMBIO] Añadimos la nueva sección de totales al HTML de la tarjeta
            dashboardContainer.innerHTML += `
                <div class="item-card" data-responsable="${item.responsable}">
                    <div class="card-header"><h3>${item.responsable}</h3></div>
                    <div class="card-section card-summary">
                        <h4>Resumen General</h4>
                        <p><span>Cantidad Ingresada:</span><strong>${item.cantidad_glosas_ingresadas}</strong></p>
                        <p><span>Valor Total Ingresado:</span><strong>${item.valor_total_glosas}</strong></p>
                    </div><hr>
                    <div class="card-section promedios-info">
                        <h4>Promedios (${item.promedios.periodo})</h4>
                        <p><span>Promedio Cantidad:</span><strong>${item.promedios.promedio_cantidad}</strong></p>
                        <p><span>Promedio Valor:</span><strong>${item.promedios.promedio_valor}</strong></p>
                    </div><hr>
                    <div class="card-section ratificacion-details">
                        <h4>Desglose por Ratificación</h4>
                        <div class="ratificacion-item">${desgloseHTML}</div>
                    </div><hr>

                    <!-- ============ NUEVA SECCIÓN DE TOTALES ============ -->
                    <div class="card-section card-totals">
                        <h4>Totales de Ítems</h4>
                        <p><span>Total Ítems:</span><strong>${item.total_items}</strong></p>
                        <p><span>Valor Glosado:</span><strong>${item.valor_glosado}</strong></p>
                        <p><span>Valor Aceptado:</span><strong>${item.valor_aceptado}</strong></p>
                        <p><span>Valor Total:</span><strong>${item.valor_total_items}</strong></p>
                    </div>
                </div>`;
        });
    };
    
    /**
     * Muestra el modal, solicita los nombres de los terceros y luego llama a renderDetallesModal.
     */
    const fetchDetalles = async (responsable) => {
        detallesModalTitle.textContent = `Detalle de Facturas para ${responsable}`;
        detallesListDiv.innerHTML = '<div class="loader">Cargando detalles...</div>';
        modalDetalles.style.display = 'flex';
        
        const detallesMap = detallesPorResponsable[responsable];
        if (!detallesMap || Object.keys(detallesMap).length === 0) {
            detallesListDiv.innerHTML = '<p>No se encontró mapa de detalles para este responsable.</p>';
            return;
        }

        try {
            const response = await fetch('api/reporte_detalles.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ detalles: detallesMap })
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'La respuesta del servidor no fue exitosa.');
            }

            const mapaNombres = await response.json();
            const estatusAceptado = (activeReport === 'analistas') ? 'ai' : 'ae';

            // 2. Pasa ese estatus como un tercer argumento a la función de renderizado.
            renderDetallesModal(detallesMap, mapaNombres, estatusAceptado);

        } catch (error) {
            console.error('Error al obtener los detalles:', error);
            detallesListDiv.innerHTML = `<p class="error">Error al cargar los detalles: ${error.message}</p>`;
        }
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
            <table class="inconsistency-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Glosa</th>
                        <th>Entidad</th>
                        <th>Total Ítems</th>
                        <th>Desglose por estado</th>
                        <th>Valor Glosado</th>
                        <th>Valor Aceptado</th>
                        <th>Total Reclamado</th> <!-- [NUEVO] Cabecera de la columna -->
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

            // [NUEVO] Calcular el total para la fila actual
            const totalReclamadoFila = desglose.valorGlosado + desglose.valorAceptado;
            
            const desgloseStr = Object.entries(desglose.estatusCounts)
                .map(([est, count]) => `${est.toUpperCase()} (${count})`)
                .join(', ');

            tableHTML += `
                <tr>
                    <td>${numeroFila++}</td>
                    <td>${documento}</td>
                    <td>${nombreTercero}</td>
                    <td>${totalItems}</td>
                    <td>${desgloseStr}</td>
                    <td>${formatter.format(desglose.valorGlosado)}</td>
                    <td>${formatter.format(desglose.valorAceptado)}</td>
                    <td>${formatter.format(totalReclamadoFila)}</td> <!-- [NUEVO] Celda de la columna -->
                </tr>`;

            // Acumular para los totales generales
            totales.items += totalItems;
            totales.glosado += desglose.valorGlosado;
            totales.aceptado += desglose.valorAceptado;
            totales.reclamado += totalReclamadoFila; // Acumular el nuevo total
        });
        
        tableHTML += '</tbody>';
        tableHTML += `
            <tfoot>
                <tr>
                    <th colspan="3">TOTALES</th>
                    <th>${totales.items.toLocaleString('es-CO')}</th>
                    <th></th>
                    <th>${formatter.format(totales.glosado)}</th>
                    <th>${formatter.format(totales.aceptado)}</th>
                    <th>${formatter.format(totales.reclamado)}</th> <!-- [NUEVO] Celda del total general -->
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
            navButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            activeReport = button.dataset.report;
            if (fechaInicioInput.value && fechaFinInput.value) {
                fetchReporte(activeReport, fechaInicioInput.value, fechaFinInput.value);
            }
        });
    });

    // Delegación de eventos para clicks en una tarjeta
    dashboardContainer.addEventListener('click', (event) => {
        const card = event.target.closest('.item-card');
        if (card && card.dataset.responsable) {
            const responsable = card.dataset.responsable;
            fetchDetalles(responsable);
        }
    });

    // Eventos para cerrar el modal de detalles
    if (closeDetallesModalButton) {
        closeDetallesModalButton.addEventListener('click', () => modalDetalles.style.display = 'none');
    }
    if (modalDetalles) {
        modalDetalles.addEventListener('click', (event) => {
            if (event.target === modalDetalles) { modalDetalles.style.display = 'none'; }
        });
    }

    // --- 5. INICIALIZACIÓN ---
    setDefaultDates();
    dashboardContainer.innerHTML = '<div class="no-results">Por favor, seleccione un rango de fechas y genere un reporte.</div>';
});