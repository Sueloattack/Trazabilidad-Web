
# Feature: Optimización de Consultas de Reportes Extensos

## 1. Resumen

**Problema:** Actualmente, los reportes que abarcan rangos de fechas muy amplios (varios meses) ejecutan una única consulta a la base de datos. Esto resulta en tiempos de carga prolongados, una mala experiencia de usuario y un alto riesgo de que la consulta falle por `timeout` en el servidor o la base de datos.

**Solución:** Se implementará una estrategia de "divide y vencerás" en el frontend. Para los reportes más pesados (`ingreso` y `analistas`), cuando el rango de fechas supere los 31 días, la aplicación dividirá la solicitud en múltiples sub-consultas (una por cada mes), las ejecutará en paralelo y luego agregará los resultados en el lado del cliente para presentar una vista unificada.

## 2. Criterios de Aceptación

- **Activación Condicional:** La lógica de división de consultas SÓLO debe activarse para los reportes `ingreso` y `analistas` cuando el rango de fechas seleccionado sea mayor a 31 días.
- **División por Meses:** El rango de fechas debe ser segmentado en rangos mensuales. Por ejemplo, una consulta del 15 de Enero al 20 de Marzo se dividirá en tres consultas: (15-Ene a 31-Ene), (01-Feb a 29-Feb) y (01-Mar a 20-Mar).
- **Ejecución Paralela:** Las sub-consultas mensuales deben ejecutarse de forma simultánea (en paralelo) para minimizar el tiempo total de espera.
- **Feedback al Usuario:** Mientras se cargan los datos, la interfaz debe mostrar un mensaje claro que indique el progreso, como `Cargando X meses...`.
- **Agregación de Datos:** Una vez que todas las sub-consultas se completen, sus resultados (tanto los datos principales como los mapas de detalles) deben ser agregados en un único conjunto de datos consolidado en el frontend.
- **Consistencia del Resultado:** El resultado final agregado y mostrado al usuario debe ser idéntico al que se obtendría si se hubiera ejecutado una única consulta para todo el período.
- **Sin Regresiones:** Los reportes que no sean `ingreso` o `analistas`, o las consultas con rangos de 31 días o menos, deben seguir funcionando como hasta ahora, con una única petición a la API.

## 3. Plan de Implementación Técnico

El trabajo se centrará en el archivo `assets/js/main.js`.

### Paso 1: Modificar la función `fetchReporte`

Esta función debe ser el punto de entrada para la nueva lógica.

```javascript
const fetchReporte = async (reporte, fechaInicio, fechaFin) => {
    // ... (limpieza inicial del contenedor)

    try {
        // 1.1. Calcular la diferencia de días
        const startDate = new Date(fechaInicio + 'T00:00:00');
        const endDate = new Date(fechaFin + 'T00:00:00');
        const diffDays = /* ...lógica para calcular días... */;

        let responses = [];

        // 1.2. Implementar la condición principal
        if (diffDays > 31 && (reporte === 'ingreso' || reporte === 'analistas')) {
            // --- Lógica de división (Pasos 2 y 3) ---
        } else {
            // --- Lógica existente para una sola consulta ---
        }
        
        // 1.3. Procesar y renderizar
        const aggregatedData = aggregateMonthlyData(responses); // (Paso 4)
        detallesPorResponsable = aggregatedData.detalle_mapa || {};
        renderContent(aggregatedData, reporte);

    } catch (error) {
        // ... (manejo de errores)
    }
};
```

### Paso 2: Crear la función `getMonthlyRanges`

Esta función auxiliar generará los rangos mensuales.

- **Input:** `startDate` (Date), `endDate` (Date).
- **Output:** Un array de objetos, ej: `[{ start: '2023-01-15', end: '2023-01-31' }, ...]`.

```javascript
const getMonthlyRanges = (startDate, endDate) => {
    const ranges = [];
    let current = new Date(startDate);

    while (current <= endDate) {
        const startOfMonth = new Date(current.getFullYear(), current.getMonth(), 1);
        const endOfMonth = new Date(current.getFullYear(), current.getMonth() + 1, 0);

        ranges.push({
            start: (startOfMonth < startDate ? startDate : startOfMonth).toISOString().slice(0, 10),
            end: (endOfMonth > endDate ? endDate : endOfMonth).toISOString().slice(0, 10)
        });

        current.setMonth(current.getMonth() + 1);
    }
    return ranges;
};
```

### Paso 3: Ejecutar Consultas en Paralelo

Dentro del bloque `if` del `Paso 1`.

```javascript
// ... (dentro del if)
const monthlyRanges = getMonthlyRanges(startDate, endDate);

// 3.1. Crear un array de promesas fetch
const fetchPromises = monthlyRanges.map(range => {
    const params = new URLSearchParams();
    params.append('fecha_inicio', range.start);
    params.append('fecha_fin', range.end);
    const url = `api/reporte_${reporte}.php?${params.toString()}`;
    return fetch(url).then(res => res.json()); // Simplificado, añadir manejo de errores
});

// 3.2. Mostrar feedback al usuario
dashboardContainer.innerHTML = `<div class="...estilos...">Cargando ${monthlyRanges.length} meses...</div>`;

// 3.3. Ejecutar todas las promesas
responses = await Promise.all(fetchPromises);
```

### Paso 4: Crear la función `aggregateMonthlyData`

Esta función consolidará los resultados.

- **Input:** `responses` (Array de objetos JSON de la API).
- **Output:** Un objeto con la misma estructura que una respuesta de la API, ej: `{ data: [...], detalle_mapa: {...} }`.

```javascript
const aggregateMonthlyData = (responses) => {
    if (!responses || responses.length === 0) {
        return { data: [], detalle_mapa: {} };
    }
    // Si solo hay una respuesta (rango corto), devolverla directamente.
    if (responses.length === 1) {
        return responses[0];
    }

    const aggregatedDataMap = new Map();
    const finalDetalleMapa = {};

    // 4.1. Iterar sobre cada respuesta mensual
    for (const res of responses) {
        // 4.2. Unir los mapas de detalles
        if (res.detalle_mapa) {
            for (const responsable in res.detalle_mapa) {
                if (!finalDetalleMapa[responsable]) {
                    finalDetalleMapa[responsable] = {};
                }
                Object.assign(finalDetalleMapa[responsable], res.detalle_mapa[responsable]);
            }
        }

        // 4.3. Agregar los datos de cada responsable
        if (res.data) {
            for (const item of res.data) {
                const key = item.responsable;
                if (!aggregatedDataMap.has(key)) {
                    // Si es la primera vez que vemos a este responsable, inicializarlo.
                    aggregatedDataMap.set(key, { ...item, /* reiniciar contadores/valores que se suman */ });
                } else {
                    // Si ya existe, sumar los valores.
                    const responsableData = aggregatedDataMap.get(key);
                    responsableData.cantidad_glosas_ingresadas += item.cantidad_glosas_ingresadas;
                    responsableData.valor_total_glosas += item.valor_total_glosas;
                    // ... sumar todos los demás campos numéricos ...
                    
                    // Fusionar desgloses
                    for (const estado in item.desglose_ratificacion) {
                        // ... lógica para sumar cantidades y facturas del desglose ...
                    }
                }
            }
        }
    }
    
    const finalData = Array.from(aggregatedDataMap.values());
    // Opcional: re-ordenar `finalData` si es necesario.

    return { data: finalData, detalle_mapa: finalDetalleMapa };
};
```
**Nota:** La lógica de agregación en `aggregateMonthlyData` debe ser cuidadosa para sumar correctamente todos los campos numéricos y fusionar los objetos de desglose. El pseudocódigo anterior es una guía.

### Paso 5: Integración Final

Asegurarse de que el `else` en `fetchReporte` coloque la única respuesta en el array `responses` para que `aggregateMonthlyData` (que puede manejar un solo elemento) funcione sin cambios.

```javascript
// ... (dentro de fetchReporte)
} else {
    // Para rangos cortos o reportes no divisibles, hacer una sola petición.
    const params = new URLSearchParams();
    params.append('fecha_inicio', fechaInicio);
    params.append('fecha_fin', fechaFin);
    const url = `api/reporte_${reporte}.php?${params.toString()}`;
    const response = await fetch(url);
    // ... (manejo de errores)
    responses.push(await response.json());
}
```
