// assets/js/main.js

document.addEventListener('DOMContentLoaded', () => {
    
    const filtroForm = document.getElementById('filtro-form');
    const dashboardContainer = document.getElementById('dashboard-container');
    const fechaInicioInput = document.getElementById('fecha_inicio');
    const fechaFinInput = document.getElementById('fecha_fin');
    const navButtons = document.querySelectorAll('.nav-button');

    let activeReport = 'ingreso';

    // --- FUNCIÓN PRINCIPAL DE FETCH (CORREGIDA) ---
    const fetchReporte = async (reporte, fechaInicio = '', fechaFin = '') => {
        dashboardContainer.innerHTML = '<div class="loader">Cargando datos...</div>';
        const apiEndpoint = `api/reporte_${reporte}.php`;
        const params = new URLSearchParams({ fecha_inicio: fechaInicio, fecha_fin: fechaFin });
        const url = `${apiEndpoint}?${params.toString()}`;

        // *** CORRECCIÓN 1: Declarar responseData fuera del try ***
        let responseData = null; 

        try {
            const response = await fetch(url);
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Error en el servidor: ${response.statusText}. Respuesta: ${errorText}`);
            }
            responseData = await response.json(); // Asignar valor
            renderContent(responseData, reporte); // Pasamos el objeto COMPLETO
        } catch (error) {
            console.error(`Error al obtener el reporte '${reporte}':`, error);
            dashboardContainer.innerHTML = `<div class="no-results error">Error al cargar el reporte. Revisa la consola para más detalles.<br><pre>${error.message}</pre></div>`;
        }
    };
    
    // --- DIRECTOR DE RENDERIZADO (MODIFICADO) ---
    const renderContent = (responseData, reporte) => {
        // Limpiamos antes de empezar a renderizar
        dashboardContainer.innerHTML = '';

        // Ahora verificamos la propiedad 'data'
        if (!responseData || !responseData.data || responseData.data.length === 0) {
            dashboardContainer.innerHTML = `<div class="no-results">No se encontraron datos para el reporte de '${reporte}' en el período seleccionado.</div>`;
        } else {
            // Decidir qué renderizador usar
            switch (reporte) {
                case 'ingreso':
                case 'analistas': // Si analistas usa la misma estructura de tarjeta
                    renderReporteDetallado(responseData.data);
                    break;
                case 'erp':
                    dashboardContainer.innerHTML = `<div>Renderizado para 'ERP' irá aquí.</div>`;
                    break;
                default:
                    dashboardContainer.innerHTML = `<div class="no-results error">Tipo de reporte desconocido: '${reporte}'.</div>`;
            }
        }

        // *** CORRECCIÓN 1 (continuación): Revisar inconsistencias después de renderizar ***
        // Esta parte ahora está dentro de renderContent para tener acceso a responseData
        if (responseData && responseData.inconsistencias && responseData.inconsistencias.length > 0) {
            console.warn('Inconsistencias encontradas:', responseData.inconsistencias);
            const warningHTML = `
                <div class="inconsistency-warning">
                    <strong>Atención:</strong> Se encontraron ${responseData.inconsistencias.length} glosas con inconsistencias que no fueron incluidas en el reporte. Revise la consola del navegador para más detalles.
                </div>
            `;
            // Añade el aviso al principio del dashboard
            dashboardContainer.insertAdjacentHTML('afterbegin', warningHTML);
        }
    };

    // --- FUNCIÓN DE RENDERIZADO ESPECÍFICA (MEJORADA) ---
    const renderReporteDetallado = (itemsData) => {
        itemsData.forEach(item => {

            // *** MEJORA 2: Generar dinámicamente el HTML del desglose ***
            let desgloseHTML = '';
            // Object.entries convierte { nu: {...}, r2: {...} } en [ ['nu', {...}], ['r2', {...}] ]
            // lo que nos permite iterar fácilmente sobre clave y valor.
            for (const [key, value] of Object.entries(item.desglose_ratificacion)) {
                // Opcional: solo muestra los items que tienen al menos 1 en cantidad.
                if (value.cantidad > 0) {
                    desgloseHTML += `
                        <p>
                            <span>${key.toUpperCase()}</span> 
                            <span><strong>${value.cantidad}</strong> / ${value.valor}</span>
                        </p>
                    `;
                }
            }
            // Si después del bucle no hay nada que mostrar en el desglose, ponemos un mensaje.
            if (desgloseHTML === '') {
                desgloseHTML = '<p>No hay desglose para este período.</p>';
            }

            const cardHTML = `
                <div class="item-card">
                    <div class="card-header">
                        <h3>${item.responsable}</h3>
                    </div>
                    
                    <div class="card-section card-summary">
                        <h4>Resumen General</h4>
                        <p><span>Cantidad Ingresada:</span> <strong>${item.cantidad_glosas_ingresadas}</strong></p>
                        <p><span>Valor Total Ingresado:</span> <strong>${item.valor_total_glosas}</strong></p>
                    </div>

                    <hr>

                    <div class="card-section promedios-info">
                        <h4>Promedios (${item.promedios.periodo})</h4>
                        <p><span>Promedio Cantidad:</span> <strong>${item.promedios.promedio_cantidad}</strong></p>
                        <p><span>Promedio Valor:</span> <strong>${item.promedios.promedio_valor}</strong></p>
                    </div>

                    <hr>

                    <div class="card-section ratificacion-details">
                        <h4>Desglose por Ratificación</h4>
                        <div class="ratificacion-item">
                            ${desgloseHTML}
                        </div>
                    </div>
                </div>
            `;
            dashboardContainer.innerHTML += cardHTML;
        });
    };

    // --- MANEJADORES DE EVENTOS (SIN CAMBIOS) ---
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

    fetchReporte(activeReport); // Carga inicial
});