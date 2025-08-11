// assets/js/main.js (Completo con Fechas, Modal de Inconsistencias y Modal de Detalles)

document.addEventListener('DOMContentLoaded', () => {
    
    // --- 1. SELECTORES DE ELEMENTOS DEL DOM ---
    const filtroForm = document.getElementById('filtro-form');
    const dashboardContainer = document.getElementById('dashboard-container');
    const fechaInicioInput = document.getElementById('fecha_inicio');
    const fechaFinInput = document.getElementById('fecha_fin');
    const navButtons = document.querySelectorAll('.nav-button');
    
    // Selectores para el modal de Inconsistencias
    const modalInconsistencias = document.getElementById('modal-inconsistencias');
    const closeModalInconsistencias = document.getElementById('close-modal-button');
    const inconsistenciasListDiv = document.getElementById('inconsistencias-list');

    // Selectores para el modal de Detalles de Factura
    const modalDetalles = document.getElementById('modal-detalles');
    const closeDetallesModalButton = document.getElementById('close-detalles-modal-button');
    const detallesListDiv = document.getElementById('detalles-list');
    const detallesModalTitle = document.getElementById('detalles-modal-title');

    // --- 2. ESTADO DE LA APLICACIÓN ---
    let activeReport = 'ingreso';
    let currentInconsistencies = [];

    // --- 3. FUNCIONES ---

    /**
     * Establece el rango de fechas por defecto: hoy y hace 15 días.
     */
    const setDefaultDates = () => {
        const today = new Date();
        const fifteenDaysAgo = new Date();
        fifteenDaysAgo.setDate(today.getDate() - 14); // Correcto

        const formatAsYMD = (date) => date.toISOString().split('T')[0];

        fechaFinInput.value = formatAsYMD(today);
        fechaInicioInput.value = formatAsYMD(fifteenDaysAgo);
    };

    /**
     * Llama a la API principal para obtener los datos agregados del reporte.
     */
    const fetchReporte = async (reporte, fechaInicio = '', fechaFin = '') => {
        dashboardContainer.innerHTML = '<div class="loader">Cargando datos...</div>';
        currentInconsistencies = [];

        try {
            const url = `api/reporte_${reporte}.php?${new URLSearchParams({ fecha_inicio: fechaInicio, fecha_fin: fechaFin })}`;
            const response = await fetch(url);
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Error en el servidor: ${response.statusText}. Respuesta: ${errorText}`);
            }
            const responseData = await response.json();
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

        if (responseData && responseData.inconsistencias && responseData.inconsistencias.length > 0) {
            currentInconsistencies = responseData.inconsistencias;
            const warningHTML = `
                <div class="inconsistency-warning">
                    <strong>Atención:</strong> Se encontraron ${currentInconsistencies.length} glosas con inconsistencias.
                    <a id="open-modal-link">Ver detalles</a>
                </div>
            `;
            dashboardContainer.insertAdjacentHTML('afterbegin', warningHTML);
        }

        if (!responseData || !responseData.data || responseData.data.length === 0) {
            dashboardContainer.innerHTML += `<div class="no-results">No se encontraron datos para el reporte de '${reporte}' en el período seleccionado.</div>`;
        } else {
            switch (reporte) {
                case 'ingreso':
                case 'analistas':
                    renderReporteDetallado(responseData.data);
                    break;
                case 'erp':
                    dashboardContainer.innerHTML += `<div>Renderizado para 'ERP' irá aquí.</div>`;
                    break;
                default:
                    dashboardContainer.innerHTML += `<div class="no-results error">Tipo de reporte desconocido.</div>`;
            }
        }
    };

    /**
     * Renderiza las tarjetas de datos detalladas.
     */
    const renderReporteDetallado = (itemsData) => {
        itemsData.forEach(item => {
            let desgloseHTML = '';
            for (const [key, value] of Object.entries(item.desglose_ratificacion)) {
                if (value.cantidad > 0) {
                    desgloseHTML += `<p><span>${key.toUpperCase()}</span><span><strong>${value.cantidad}</strong> / ${value.valor}</span></p>`;
                }
            }
            if (desgloseHTML === '') { desgloseHTML = '<p>No hay desglose para este período.</p>'; }

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
                    </div>
                </div>`;
        });
    };
    
    /**
     * Llama a la API para obtener los detalles de las facturas de un responsable.
     */
    const fetchDetalles = async (responsable, fechaInicio, fechaFin) => {
        detallesModalTitle.textContent = `Detalle de Facturas para ${responsable}`;
        detallesListDiv.innerHTML = '<div class="loader">Cargando detalles...</div>';
        modalDetalles.style.display = 'flex';
        
        try {
            const params = new URLSearchParams({ responsable, fecha_inicio: fechaInicio, fecha_fin: fechaFin });
            const url = `api/reporte_detalles.php?${params.toString()}`;
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error('La respuesta de la red no fue exitosa.');
            }
            const detalles = await response.json();
            renderDetallesModal(detalles);
        } catch (error) {
            console.error('Error al obtener detalles:', error);
            detallesListDiv.innerHTML = '<div class="no-results error">No se pudo cargar el detalle.</div>';
        }
    };
    
    /**
     * Renderiza el contenido del modal de detalles de facturas.
     */
    const renderDetallesModal = (detalles) => {
        if (!detalles || detalles.length === 0) {
            detallesListDiv.innerHTML = '<p>No se encontraron detalles para este responsable en el período seleccionado.</p>';
            return;
        }
        let tableHTML = '<table class="inconsistency-table"><thead><tr><th>Serie</th><th>Documento</th><th>Tercero</th><th>Valor Glosa</th></tr></thead><tbody>';
        detalles.forEach(d => {
            tableHTML += `<tr><td>${d.serie}</td><td>${d.fc_docn}</td><td>${d.tercero}</td><td>${d.vr_glosa}</td></tr>`;
        });
        tableHTML += '</tbody></table>';
        detallesListDiv.innerHTML = tableHTML;
    };
    
    /**
     * Abre y puebla el modal con los datos de inconsistencias.
     */
    const openInconsistencyModal = () => {
        inconsistenciasListDiv.innerHTML = '';
        let tableHTML = '<table class="inconsistency-table"><thead><tr><th>ID Compuesto</th><th>Motivo</th></tr></thead><tbody>';
        currentInconsistencies.forEach(item => {
            tableHTML += `<tr><td>${item.id}</td><td>${item.motivo}</td></tr>`;
        });
        tableHTML += '</tbody></table>';
        inconsistenciasListDiv.innerHTML = tableHTML;
        modalInconsistencias.style.display = 'flex';
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
            fetchReporte(activeReport, fechaInicioInput.value, fechaFinInput.value);
        });
    });

    // Delegación de eventos para clicks dentro del dashboard (eficiente y robusto)
    dashboardContainer.addEventListener('click', (event) => {
        // Clic en "Ver detalles" de inconsistencias
        if (event.target && event.target.id === 'open-modal-link') {
            event.preventDefault();
            openInconsistencyModal();
        }
        
        // Clic en una tarjeta de item/responsable
        const card = event.target.closest('.item-card');
        if (card && card.dataset.responsable) {
            const responsable = card.dataset.responsable;
            fetchDetalles(responsable, fechaInicioInput.value, fechaFinInput.value);
        }
    });

    // Eventos para cerrar los modales
    closeModalInconsistencias.addEventListener('click', () => modalInconsistencias.style.display = 'none');
    modalInconsistencias.addEventListener('click', (event) => {
        if (event.target === modalInconsistencias) { modalInconsistencias.style.display = 'none'; }
    });
    
    closeDetallesModalButton.addEventListener('click', () => modalDetalles.style.display = 'none');
    modalDetalles.addEventListener('click', (event) => {
        if (event.target === modalDetalles) { modalDetalles.style.display = 'none'; }
    });

    // --- 5. INICIALIZACIÓN ---
    setDefaultDates();
    fetchReporte(activeReport, fechaInicioInput.value, fechaFinInput.value);
});