<?php
// ============================================
// 1. CONFIGURACIÓN INICIAL
// ============================================
// Esto muestra errores para que podamos depurar si algo falla
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=UTF-8');

// ============================================
// 2. VARIABLES QUE VAMOS A USAR
// ============================================
$resultados = [];          // Aquí guardaremos los paraderos encontrados
$localidad_seleccionada = ''; // Aquí guardamos lo que el usuario escogió
$mensaje = '';             // Aquí guardamos mensajes de error o éxito
$modo_depuracion = false;   // Cambiar a true si quieres ver los datos del dataset

// ============================================
// 3. LISTA DE LOCALIDADES DE BOGOTÁ
// ============================================
// IMPORTANTE: Usar nombres en MAYÚSCULAS para coincidir con el dataset
$localidades = [
    'USAQUEN', 'CHAPINERO', 'SANTA FE', 'SAN CRISTOBAL', 'USME',
    'TUNJUELITO', 'BOSA', 'KENNEDY', 'FONTIBON', 'ENGATIVA',
    'SUBA', 'BARRIOS UNIDOS', 'TEUSAQUILLO', 'LOS MARTIRES',
    'ANTONIO NARIÑO', 'PUENTE ARRENDONDO', 'LA CANDELARIA',
    'RAFAEL URIBE URIBE', 'CIUDAD BOLIVAR', 'SUMAPAZ'
];

// ============================================
// 4. FUNCIÓN PARA FILTRAR PARADEROS POR LOCALIDAD
// ============================================
function filtrar_paraderos($geojson, $localidad) {
    $paraderos = [];
    
    // Verificamos que los datos tengan la estructura esperada
    if (!isset($geojson['features']) || !is_array($geojson['features'])) {
        return $paraderos;
    }
    
    // Recorremos cada paradero en el archivo
    foreach ($geojson['features'] as $feature) {
        // Obtenemos las propiedades del paradero
        $propiedades = $feature['properties'] ?? [];
        
        // El campo correcto es 'LocNombre' (contiene la localidad en MAYÚSCULAS)
        $localidad_dato = $propiedades['LocNombre'] ?? '';
        
        // Comparación sin distinción de mayúsculas
        if (stripos($localidad_dato, $localidad) !== false) {
            // Extraemos las coordenadas
            $coordenadas = $feature['geometry']['coordinates'] ?? null;
            
            // Guardamos los datos del paradero
            $paraderos[] = [
                'nombre' => $propiedades['nombre'] ?? 'Sin nombre',
                'direccion' => $propiedades['direccion_'] ?? $propiedades['direccion'] ?? 'Sin dirección',
                'latitud' => $coordenadas[1] ?? 'No disponible',
                'longitud' => $coordenadas[0] ?? 'No disponible'
            ];
        }
    }
    
    return $paraderos;
}

// ============================================
// 5. PROCESAR EL FORMULARIO (cuando el usuario hace clic en "Consultar")
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['localidad'])) {
    $localidad_seleccionada = trim($_POST['localidad']);
    
    if (!empty($localidad_seleccionada)) {
        // ==========================================
        // 6. CONSULTAR LA API DE DATOS ABIERTOS
        // ==========================================
        // URL corregida usando ArcGIS REST API (funciona correctamente)
        $url = "https://services1.arcgis.com/J5ltM0ovtzXUbp7B/ArcGIS/rest/services/ParaderosSITP/FeatureServer/0/query?where=1%3D1&outFields=*&returnGeometry=true&f=geojson";
        
        // Configuramos la petición HTTP
        $opciones = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
            ]
        ];
        $contexto = stream_context_create($opciones);
        
        // Hacemos la petición a la API
        $respuesta = @file_get_contents($url, false, $contexto);
        
        // Verificamos si la API respondió correctamente
        if ($respuesta === false) {
            $mensaje = "❌ Error: No se pudo conectar con el servidor de datos. Verifica tu conexión a internet.";
        } else {
            // Convertimos el JSON en un arreglo de PHP
            $datos = json_decode($respuesta, true);
            
            // ==========================================
            // MODO DEPURACIÓN: Ver qué datos tiene el dataset
            // ==========================================
            if ($modo_depuracion) {
                echo "<div style='max-width:1000px;margin:20px auto;padding:20px;background:#f0f4f8;border-radius:10px;font-family:monospace;'>";
                echo "<h2 style='color:#1a365d;'>🔍 MODO DEPURACIÓN - Datos del Dataset</h2>";
                
                // Verificar si hay datos
                if (!isset($datos['features']) || empty($datos['features'])) {
                    echo "<p style='color:red;'>❌ No se encontraron datos en el dataset.</p>";
                } else {
                    // Mostrar el primer paradero para ver su estructura
                    $primer_paradero = $datos['features'][0]['properties'] ?? [];
                    echo "<h3>📋 Estructura del primer paradero:</h3>";
                    echo "<pre>";
                    print_r($primer_paradero);
                    echo "</pre>";
                    
                    // Extraer todas las localidades disponibles
                    $localidades_encontradas = [];
                    foreach ($datos['features'] as $feature) {
                        $prop = $feature['properties'] ?? [];
                        if (!empty($prop['LocNombre'])) {
                            $localidades_encontradas[] = $prop['LocNombre'];
                        }
                    }
                    
                    // Limpiar y ordenar
                    $localidades_encontradas = array_unique($localidades_encontradas);
                    sort($localidades_encontradas);
                    
                    echo "<h3>📍 Localidades disponibles en el dataset (total: " . count($localidades_encontradas) . "):</h3>";
                    echo "<pre>";
                    print_r($localidades_encontradas);
                    echo "</pre>";
                    
                    echo "<h3>💡 Recomendación:</h3>";
                    echo "<p>Busca el nombre exacto de la localidad que quieres consultar en la lista de arriba.</p>";
                    echo "<p><strong>Ejemplo:</strong> Si buscas 'Usaquén', pero en el dataset aparece como 'USAQUEN' (mayúsculas), debes seleccionar 'USAQUEN'.</p>";
                }
                echo "</div>";
                
                // Salir para no mostrar la página normal
                exit;
            }
            
            // Verificamos que sea un JSON válido
            if ($datos === null) {
                $mensaje = "❌ Error: Los datos recibidos no son válidos.";
            } else {
                // Filtramos los paraderos por la localidad seleccionada
                $resultados = filtrar_paraderos($datos, $localidad_seleccionada);
                
                if (empty($resultados)) {
                    $mensaje = "ℹ️ No se encontraron paraderos en la localidad '$localidad_seleccionada'.";
                }
            }
        }
    } else {
        $mensaje = "⚠️ Por favor, selecciona una localidad válida.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paraderos SITP por Localidad</title>
    <style>
        /* ==========================================
           7. ESTILOS (para que se vea bonito)
           ========================================== */
        * { box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
            background: #f0f4f8;
        }
        .container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { 
            color: #1a365d;
            border-bottom: 3px solid #2b6cb0;
            padding-bottom: 10px;
        }
        .form-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin: 20px 0;
        }
        select {
            padding: 10px 15px;
            border-radius: 5px;
            border: 2px solid #cbd5e0;
            font-size: 16px;
            flex: 1;
            min-width: 200px;
        }
        button {
            background: #2b6cb0;
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
        }
        button:hover { background: #1a4f8b; }
        .mensaje {
            padding: 12px 18px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .mensaje.error { background: #fed7d7; color: #742a2a; }
        .mensaje.info { background: #fefcbf; color: #744210; }
        .mensaje.exito { background: #c6f6d5; color: #22543d; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background: #2b6cb0;
            color: white;
            padding: 12px;
            text-align: left;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        tr:hover { background: #f7fafc; }
        .contador {
            background: #edf2f7;
            padding: 8px 16px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 10px;
        }
        .footer {
            margin-top: 30px;
            font-size: 14px;
            color: #718096;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
        }
        .documentacion {
            margin-top: 30px;
            padding: 20px;
            background: #f7fafc;
            border-radius: 8px;
            border-left: 4px solid #2b6cb0;
        }
        .documentacion h3 { margin-top: 0; color: #1a365d; }
        .documentacion code {
            background: #edf2f7;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚌 Paraderos SITP por Localidad</h1>
        <p>Selecciona una localidad de Bogotá para ver sus paraderos.</p>
        
        <!-- ==========================================
             8. FORMULARIO (el menú que ve el usuario)
             ========================================== -->
        <form method="POST" action="">
            <div class="form-group">
                <select name="localidad" required>
                    <option value="">-- Selecciona una localidad --</option>
                    <?php foreach ($localidades as $loc): ?>
                        <option value="<?php echo htmlspecialchars($loc); ?>" 
                            <?php echo ($localidad_seleccionada === $loc) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($loc); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">🔍 Consultar Paraderos</button>
            </div>
        </form>
        
        <!-- ==========================================
             9. MOSTRAR MENSAJES (si hay errores o avisos)
             ========================================== -->
        <?php if (!empty($mensaje)): ?>
            <div class="mensaje <?php echo strpos($mensaje, 'Error') !== false ? 'error' : (strpos($mensaje, 'No se encontraron') !== false ? 'info' : 'exito'); ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>
        
        <!-- ==========================================
             10. TABLA DE RESULTADOS
             ========================================== -->
        <?php if (!empty($resultados)): ?>
            <div class="contador">
                📍 <?php echo count($resultados); ?> paraderos encontrados en <strong><?php echo htmlspecialchars($localidad_seleccionada); ?></strong>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre del Paradero</th>
                        <th>Dirección</th>
                        <th>Latitud</th>
                        <th>Longitud</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($resultados as $paradero): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($paradero['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($paradero['direccion']); ?></td>
                            <td><?php echo htmlspecialchars($paradero['latitud']); ?></td>
                            <td><?php echo htmlspecialchars($paradero['longitud']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <!-- ==========================================
             11. DOCUMENTACIÓN DEL CÓDIGO (lo que pide el profe)
             ========================================== -->
        <div class="documentacion">
            <h3>📝 Documentación del Código</h3>
            <p><strong>¿Qué hace este código?</strong></p>
            <ul>
                <li><strong>Líneas 1-6:</strong> Configuración inicial. Activa la visualización de errores para facilitar la depuración y establece el tipo de contenido como HTML con codificación UTF-8.</li>
                <li><strong>Líneas 9-11:</strong> Variables principales. <code>$resultados</code> almacena los paraderos encontrados, <code>$localidad_seleccionada</code> guarda la opción del usuario y <code>$mensaje</code> contiene avisos o errores.</li>
                <li><strong>Líneas 14-20:</strong> Arreglo con las 20 localidades de Bogotá en mayúsculas para coincidir con el formato del dataset. Esto se usa para llenar el menú desplegable.</li>
                <li><strong>Líneas 23-56:</strong> Función <code>filtrar_paraderos()</code>. Recibe los datos de la API y la localidad seleccionada. Recorre cada paradero, verifica si pertenece a la localidad usando el campo <code>LocNombre</code> y extrae nombre, dirección y coordenadas.</li>
                <li><strong>Líneas 59-86:</strong> Procesamiento del formulario. Cuando el usuario envía el formulario (método POST), se obtiene la localidad seleccionada y se hace la petición a la API usando <code>file_get_contents()</code>.</li>
                <li><strong>Líneas 65-67:</strong> La URL de la API. <code>https://services1.arcgis.com/J5ltM0ovtzXUbp7B/ArcGIS/rest/services/ParaderosSITP/FeatureServer/0/query?where=1%3D1&outFields=*&returnGeometry=true&f=geojson</code> es el endpoint público de datos abiertos del SITP usando ArcGIS REST API.</li>
                <li><strong>Líneas 79-85:</strong> Procesamiento de la respuesta. Se convierte el JSON en un arreglo de PHP y se llama a la función <code>filtrar_paraderos()</code> para obtener los resultados.</li>
                <li><strong>Líneas 90-140:</strong> HTML y CSS. La estructura visual de la página: formulario, tabla de resultados y estilos para que se vea profesional.</li>
                <li><strong>Líneas 143-172:</strong> Generación del menú desplegable. Recorre el arreglo de localidades y crea las opciones del <code>&lt;select&gt;</code>.</li>
                <li><strong>Líneas 175-181:</strong> Visualización de mensajes. Muestra errores o avisos según el tipo de mensaje.</li>
                <li><strong>Líneas 184-206:</strong> Tabla de resultados. Muestra los paraderos encontrados con numeración, nombre, dirección y coordenadas.</li>
                <li><strong>Líneas 209-224:</strong> Documentación. Esta sección explica el funcionamiento del código para cumplir con el requisito del profesor.</li>
            </ul>
            <p><strong>🔐 Seguridad:</strong> El código usa <code>htmlspecialchars()</code> para evitar inyección de código (XSS) y verifica que la respuesta de la API sea válida antes de procesarla.</p>
            <p><strong>🌐 Conexión:</strong> La API es pública y no requiere autenticación, lo que la hace ideal para este ejercicio.</p>
            <p><strong>📌 Nota sobre el dataset:</strong> El campo que contiene la localidad es <code>LocNombre</code> y los nombres están en mayúsculas (ej: USAQUEN).</p>
        </div>
        
        <div class="footer">
            <p>📝 Evidencia SENA - Consumo de API Pública (Datos Abiertos Bogotá - SITP)</p>
            <p>Desarrollado con PHP - Fecha: <?php echo date('d/m/Y'); ?></p>
        </div>
    </div>
</body>
</html>