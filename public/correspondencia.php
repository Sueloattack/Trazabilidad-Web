<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Radicación de Correspondencia</title>
    <style>
        body {font-family:Arial,sans-serif;background-color:#f4f7f6;margin:0;padding:20px;display:flex;justify-content:center;align-items:center;min-height:100vh;box-sizing:border-box;}
        .container{background-color:#fff;padding:30px 40px;border-radius:8px;box-shadow:0 4px 10px rgba(0,0,0,.1);width:100%;max-width:600px}
        h1{color:#333;text-align:center;margin-bottom:30px;font-size:1.8em}
        .form-group{margin-bottom:20px}
        .form-group label{display:block;margin-bottom:8px;color:#555;font-weight:700}
        .form-group input[type=text],.form-group input[type=number],.form-group textarea{width:calc(100% - 22px);padding:10px;border:1px solid #ccc;border-radius:4px;font-size:1em;box-sizing:border-box}
        .form-group input[disabled],.form-group textarea[disabled]{background-color:#e9ecef;cursor:not-allowed;color:#6c757d}
        .form-group textarea{min-height:80px;resize:vertical}
        .form-actions{margin-top:30px;text-align:center}
        .form-actions button{background-color:#007bff;color:#fff;padding:12px 25px;border:none;border-radius:5px;cursor:pointer;font-size:1.1em;transition:background-color .3s ease}
        .form-actions button:hover{background-color:#0056b3}
        .back-link{display:block;text-align:center;margin-top:20px;color:#007bff;text-decoration:none}
        .back-link:hover{text-decoration:underline}
        .spinner { display: none; margin-left: 10px; border: 3px solid #f3f3f3; border-top: 3px solid #3498db; border-radius: 50%; width: 16px; height: 16px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        #mensajeUsuario { margin-top: 15px; padding: 10px; border-radius: 4px; text-align: center; display: none; }
        #mensajeUsuario.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        #mensajeUsuario.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        #mensajeUsuario.info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Radicación de Correspondencia</h1>

        <div id="mensajeUsuario"></div>

        <form action="#" method="POST" id="radicacionForm">
            
            <div class="form-group">
                <label for="serie">Serie:</label>
                <input type="text" id="serie" name="serie" placeholder="Ej: FECR, GLSA" required>
            </div>

            <div class="form-group">
                <label for="numero_factura">N° de Factura: <span id="spinnerFactura" class="spinner"></span></label>
                <input type="number" id="numero_factura" name="numero_factura" placeholder="Ej: 12345" required>
            </div>

            <div class="form-group">
                <label for="NIT">NIT:</label>
                <input type="text" id="NIT" name="NIT" value="" disabled>
            </div>

            <div class="form-group">
                <label for="tipo_glosa">Tipo de Glosa:</label> <!-- Cambiado de "Tipo de Glosa (Prioritario)" -->
                <input type="text" id="tipo_glosa" name="tipo_glosa" value="" disabled>
            </div>

            <div class="form-group">
                <label for="descripcion_glosa">Descripción de la Glosa:</label> <!-- Cambiado de "Descripción(es) de la Glosa" -->
                <input type="text" id="descripcion_glosa" name="descripcion_glosa" value="" disabled>
            </div>

            <div class="form-group">
                <label for="estado_consolidado">Estado Consolidado:</label>
                <input type="text" id="estado_consolidado" name="estado_consolidado" value="" disabled>
            </div>

            <div class="form-actions">
                <button type="submit">Guardar</button>
            </div>
        </form>
        
        <a href="index.php" class="back-link">Volver al Listado de Trazabilidad</a>
    </div>

    <script>
        // Asegúrate que estos IDs coincidan EXACTAMENTE con tu HTML
        const serieInput = document.getElementById('serie');
        const numeroFacturaInput = document.getElementById('numero_factura');
        const nitInput = document.getElementById('NIT');
        const tipoGlosaInput = document.getElementById('tipo_glosa');
        const descripcionGlosaInput = document.getElementById('descripcion_glosa');
        const estadoConsolidadoInput = document.getElementById('estado_consolidado');
        const spinnerFactura = document.getElementById('spinnerFactura');
        const mensajeUsuarioDiv = document.getElementById('mensajeUsuario');
        let debounceTimer;

        console.log("Script de correspondencia.php cargado."); 

        function limpiarCamposResultado() {
            console.log("Limpiando campos de resultado.");
            nitInput.value = '';
            tipoGlosaInput.value = '';
            descripcionGlosaInput.value = '';
            estadoConsolidadoInput.value = '';
        }

        function mostrarMensaje(tipo, mensaje) {
            console.log(`Mostrando mensaje: ${tipo} - ${mensaje}`);
            mensajeUsuarioDiv.textContent = mensaje;
            mensajeUsuarioDiv.className = '';
            mensajeUsuarioDiv.classList.add(tipo);
            mensajeUsuarioDiv.style.display = 'block';
        }

        function ocultarMensaje() {
            console.log("Ocultando mensaje.");
            mensajeUsuarioDiv.style.display = 'none';
        }

        function popularCamposConDatos(datosObjeto) {
            console.log("Populando campos con datos:", datosObjeto);
            if (datosObjeto) { // Asegura que datosObjeto no es null o undefined
                nitInput.value = datosObjeto.nit || ''; 
                tipoGlosaInput.value = datosObjeto.tipo_glosa || ''; 
                descripcionGlosaInput.value = datosObjeto.descripcion_glosa || ''; 
                estadoConsolidadoInput.value = datosObjeto.estado_consolidado || '';
            } else {
                console.warn("Intentando popular campos pero el objeto de datos es nulo/indefinido.");
                limpiarCamposResultado(); // Si no hay datos, limpiar.
            }
        }


        function buscarDatosFactura() {
            ocultarMensaje();
            const serie = serieInput.value.trim();
            const numeroFactura = numeroFacturaInput.value.trim();
            console.log(`Iniciando búsqueda para Serie: ${serie}, N° Factura: ${numeroFactura}`);

            if (serie === '' || numeroFactura === '') {
                console.log("Serie o N° Factura vacíos, no se busca.");
                limpiarCamposResultado();
                return;
            }

            spinnerFactura.style.display = 'inline-block';
            limpiarCamposResultado(); 

            const formData = new FormData();
            formData.append('serie', serie);
            formData.append('numero_factura', numeroFactura);

            console.log("Enviando fetch a buscar_factura_fox.php");
            fetch('buscar_factura_fox.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log("Respuesta recibida del fetch:", response);
                if (!response.ok) {
                    console.error("Respuesta no OK:", response.status, response.statusText);
                    // Intentar obtener el texto del error para más detalles
                    return response.text().then(text => {
                        console.error("Cuerpo de la respuesta de error:", text);
                        throw new Error(`Error de red o servidor: ${response.status} ${response.statusText}. Respuesta: ${text.substring(0,200)}...`);
                    });
                }
                
                const contentType = response.headers.get("content-type");
                console.log("Content-Type de la respuesta:", contentType);
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    console.log("La respuesta es JSON, intentando parsear...");
                    return response.json();
                } else {
                    console.warn("La respuesta NO es JSON. Intentando obtener como texto...");
                    return response.text().then(text => {
                        console.error("Cuerpo de la respuesta no JSON:", text);
                        throw new Error("Respuesta no es JSON. Contenido: " + text.substring(0, 200) + "...");
                    });
                }
            })
            .then(data => {
                spinnerFactura.style.display = 'none';
                console.log("JSON parseado exitosamente. Datos recibidos del servidor:", data);

                // Siempre intentar popular campos si data.datos existe, independientemente de factura_cobrada
                if (data.datos) {
                    popularCamposConDatos(data.datos);
                } else {
                    console.warn("El objeto 'data' no contiene la propiedad 'datos'.");
                    // Limpiar campos si no hay data.datos podría ser una opción,
                    // pero ya se limpian al inicio de buscarDatosFactura.
                }

                // Manejar el caso de factura_cobrada
                if (data.factura_cobrada === true || data.factura_cobrada === 1) { // FoxPro puede enviar 1 para true
                    console.log("Factura marcada como cobrada.");
                    mostrarMensaje('info', data.message || 'La factura está totalmente cobrada.');
                    // Los campos ya se habrían populado si data.datos existe
                    return; // Termina el procesamiento normal aquí
                }

                // Manejar otros casos de éxito o error lógico del backend
                if (data.success && data.datos) { // success puede ser true/false o 1/0
                    console.log("Procesando datos normales (no cobrada, éxito).");
                    // Los campos ya se populado si data.datos existe
                    if (data.datos.tipo_glosa === 'NU') {
                        mostrarMensaje('info', data.datos.descripcion_glosa || 'Factura no registrada para glosas.');
                    } else if (data.message) {
                         mostrarMensaje('success', data.message);
                    }
                } else if (typeof data.success !== 'undefined' && !data.success && data.message) {
                    console.warn("Respuesta del servidor (no exitosa):", data.message);
                    mostrarMensaje('error', data.message);
                    // Los campos ya se limpian al inicio de la búsqueda, no es necesario aquí de nuevo.
                } else {
                    console.error("Respuesta del servidor con estructura inesperada (faltan 'success' o 'datos/message'):", data);
                    mostrarMensaje('error', 'Respuesta inesperada del servidor.');
                }
            })
            .catch(error => {
                spinnerFactura.style.display = 'none';
                console.error('CATCH: Error en la petición AJAX o procesamiento de respuesta:', error);
                mostrarMensaje('error', 'Error al consultar la factura: ' + error.message.substring(0,150) + '...');
                limpiarCamposResultado();
            });
        }

        function debouncedBuscarDatosFactura() {
            clearTimeout(debounceTimer);
            spinnerFactura.style.display = 'none';
            debounceTimer = setTimeout(buscarDatosFactura, 700); 
        }

        // Event Listeners
        if(serieInput && numeroFacturaInput) { // Asegurarse que los elementos existen antes de añadir listeners
            serieInput.addEventListener('input', debouncedBuscarDatosFactura);
            numeroFacturaInput.addEventListener('input', debouncedBuscarDatosFactura);
        } else {
            console.error("No se encontraron los inputs de serie o número de factura para añadir listeners.");
        }
        
        const form = document.getElementById('radicacionForm');
        if (form) {
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                console.log('Formulario "Guardar" presionado. Lógica de guardado no implementada aquí.');
                mostrarMensaje('info', 'Funcionalidad de "Guardar" no implementada en este ejemplo.');
            });
        } else {
            console.error("No se encontró el formulario 'radicacionForm'.");
        }

    </script>
</body>
</html>