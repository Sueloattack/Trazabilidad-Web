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
    </style>
</head>
<body>
    <div class="container">
        <h1>Radicación de Correspondencia</h1>

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
                <label for="tipo_glosa">Tipo de Glosa:</label>
                <input type="text" id="tipo_glosa" name="tipo_glosa" value="" disabled>
            </div>

            <div class="form-group">
                <label for="descripcion_glosa">Descripción de la Glosa:</label>
                <input type="text" id="descripcion_glosa" name="descripcion_glosa" value="" disabled>
            </div>

            <div class="form-actions">
                <button type="submit">Guardar</button>
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
        const spinnerFactura = document.getElementById('spinnerFactura');
        let debounceTimer;

        function buscarDatosFactura() {
            const serie = serieInput.value.trim();
            const numeroFactura = numeroFacturaInput.value.trim();

            // Limpiar campos deshabilitados si no hay suficientes datos para buscar
            if (serie === '' || numeroFactura === '') {
                nitInput.value = '';
                tipoGlosaInput.value = '';
                descripcionGlosaInput.value = '';
                return;
            }

            spinnerFactura.style.display = 'inline-block'; // Mostrar spinner

            // Crear un objeto FormData para enviar los datos
            const formData = new FormData();
            formData.append('serie', serie);
            formData.append('numero_factura', numeroFactura);

            fetch('buscar_factura_fox.php', { // El script PHP que buscará en FoxPro
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la red o respuesta no OK: ' + response.statusText);
                }
                return response.json(); // Esperamos una respuesta JSON
            })
            .then(data => {
                spinnerFactura.style.display = 'none'; 
                if (data && data.datos) { 
                    nitInput.value = data.datos.nit || ''; 
                    tipoGlosaInput.value = data.datos.tipo_glosa || ''; 
                    descripcionGlosaInput.value = data.datos.descripcion_glosa || ''; 
                } else {
                    nitInput.value = '';
                    tipoGlosaInput.value = '';
                    descripcionGlosaInput.value = '';
                    console.error('La respuesta del servidor no contiene la estructura esperada (data.datos).', data);
                }
                if (data.success) {
                    console.log('Datos de factura encontrados y cargados:', data.message);
                } else {
                    console.warn('Respuesta del servidor (no exitosa):', data.message || 'Error desconocido del servidor.');
                }
            })
            .catch(error => {
                spinnerFactura.style.display = 'none'; // Ocultar spinner en caso de error
                console.error('Error en la petición AJAX:', error);
                nitInput.value = ''; // Limpiar en caso de error
                tipoGlosaInput.value = '';
                descripcionGlosaInput.value = '';
            });
        }

        // Función de debounce para no hacer peticiones en cada tecla
        function debouncedBuscarDatosFactura() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(buscarDatosFactura, 700); // Esperar 700ms después de dejar de teclear
        }

        // Escuchar eventos 'input' en los campos habilitados
        serieInput.addEventListener('input', debouncedBuscarDatosFactura);
        numeroFacturaInput.addEventListener('input', debouncedBuscarDatosFactura);

    </script>
</body>
</html>