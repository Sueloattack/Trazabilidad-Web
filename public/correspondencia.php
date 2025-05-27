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
                <label for="tipo_glosa">Tipo de Glosa (Prioritario):</label>
                <input type="text" id="tipo_glosa" name="tipo_glosa" value="" disabled>
            </div>

            <div class="form-group">
                <label for="descripcion_glosa">Descripción(es) de la Glosa:</label>
                <input type="text" id="descripcion_glosa" name="descripcion_glosa" value="" disabled>
            </div>

            <div class="form-group">
                <label for="estado_consolidado">Estado Consolidado:</label>
                <input type="text" id="estado_consolidado" name="estado_consolidado" value="" disabled>
            </div>

            <div class="form-actions">
                <button type="submit">Guardar</button> <!-- Este botón es para el formulario, la búsqueda es automática -->
            </div>
        </form>
        
        <a href="index.php" class="back-link">Volver al Listado de Trazabilidad</a>
    </div>

    <script>
        const serieInput = document.getElementById('serie');
        const numeroFacturaInput = document.getElementById('numero_factura');
        const nitInput = document.getElementById('NIT');
        const tipoGlosaInput = document.getElementById('tipo_glosa');
        const descripcionGlosaInput = document.getElementById('descripcion_glosa');
        const estadoConsolidadoInput = document.getElementById('estado_consolidado');
        const spinnerFactura = document.getElementById('spinnerFactura');
        const mensajeUsuarioDiv = document.getElementById('mensajeUsuario');
        let debounceTimer;

        function limpiarCamposResultado() {
            nitInput.value = '';
            tipoGlosaInput.value = '';
            descripcionGlosaInput.value = '';
            estadoConsolidadoInput.value = '';
        }

        function mostrarMensaje(tipo, mensaje) {
            mensajeUsuarioDiv.textContent = mensaje;
            mensajeUsuarioDiv.className = ''; // Limpiar clases previas
            mensajeUsuarioDiv.classList.add(tipo); // 'success', 'error', 'info'
            mensajeUsuarioDiv.style.display = 'block';
        }

        function ocultarMensaje() {
            mensajeUsuarioDiv.style.display = 'none';
        }

        function buscarDatosFactura() {
            ocultarMensaje(); // Ocultar mensajes previos al iniciar una nueva búsqueda
            const serie = serieInput.value.trim();
            const numeroFactura = numeroFacturaInput.value.trim();

            if (serie === '' || numeroFactura === '') {
                limpiarCamposResultado();
                // Podrías opcionalmente ocultar el spinner si estaba visible y no se hará petición
                // spinnerFactura.style.display = 'none'; 
                return;
            }

            spinnerFactura.style.display = 'inline-block';
            limpiarCamposResultado(); // Limpiar antes de la nueva búsqueda

            const formData = new FormData();
            formData.append('serie', serie);
            formData.append('numero_factura', numeroFactura);

            fetch('buscar_factura_fox.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    // Si es un error HTTP, intentar obtener texto para más contexto.
                    return response.text().then(text => {
                        throw new Error(`Error de red o servidor: ${response.status} ${response.statusText}. Detalles: ${text}`);
                    });
                }
                // Verificar si la respuesta es JSON antes de intentar parsearla
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        throw new Error("Respuesta no es JSON. Contenido: " + text.substring(0, 100) + "...");
                    });
                }
            })
            .then(data => {
                spinnerFactura.style.display = 'none';

                if (data.factura_cobrada === true) {
                    mostrarMensaje('info', data.message || 'La factura está totalmente cobrada.');
                    limpiarCamposResultado(); // Asegurarse que los campos estén vacíos
                    return;
                }

                if (data.success && data.datos) {
                    nitInput.value = data.datos.nit || '';
                    tipoGlosaInput.value = data.datos.tipo_glosa || '';
                    descripcionGlosaInput.value = data.datos.descripcion_glosa || '';
                    estadoConsolidadoInput.value = data.datos.estado_consolidado || '';
                    
                    if (data.datos.tipo_glosa === 'NU') {
                        mostrarMensaje('info', data.datos.descripcion_glosa || 'Factura no registrada para glosas.');
                    } else if (data.message) {
                         mostrarMensaje('success', data.message);
                    }

                } else if (!data.success && data.message) {
                    // Error de lógica de negocio reportado por el backend
                    mostrarMensaje('error', data.message);
                    limpiarCamposResultado();
                    console.warn('Respuesta del servidor (no exitosa):', data.message);
                } else {
                    // Estructura inesperada
                    throw new Error('La respuesta del servidor no tiene la estructura esperada.');
                }
            })
            .catch(error => {
                spinnerFactura.style.display = 'none';
                console.error('Error en la petición AJAX o procesamiento de respuesta:', error);
                mostrarMensaje('error', 'Error al consultar la factura: ' + error.message);
                limpiarCamposResultado();
            });
        }

        function debouncedBuscarDatosFactura() {
            clearTimeout(debounceTimer);
            spinnerFactura.style.display = 'none'; // Ocultar spinner si se cancela timer
            debounceTimer = setTimeout(buscarDatosFactura, 700); 
        }

        serieInput.addEventListener('input', debouncedBuscarDatosFactura);
        numeroFacturaInput.addEventListener('input', debouncedBuscarDatosFactura);

        // Prevenir envío del formulario si es solo para la búsqueda AJAX
        document.getElementById('radicacionForm').addEventListener('submit', function(event) {
            event.preventDefault();
            // Aquí podrías agregar la lógica para "Guardar" los datos si el formulario
            // también tiene esa funcionalidad, de lo contrario, no hacer nada o
            // incluso disparar `buscarDatosFactura` si los campos están llenos.
            console.log('Formulario "Guardar" presionado. Lógica de guardado no implementada aquí.');
            mostrarMensaje('info', 'Funcionalidad de "Guardar" no implementada en este ejemplo.');
        });

    </script>
</body>
</html>