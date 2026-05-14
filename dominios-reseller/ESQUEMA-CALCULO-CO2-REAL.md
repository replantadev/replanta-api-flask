# Esquema: Cálculo Real de CO2 Evitado

## 🎯 Objetivo
Calcular automáticamente el CO2 evitado cuando se pulsa "Calcular" en un dominio de servidor USA/UK, basándose en:
- Tráfico real del dominio (obtenido de WHM)
- Tiempo activo del plan de hosting
- API de Website Carbon o CO2.js para cálculos precisos

---

## 📊 Situación Actual

### Frontend (admin.js línea 92)
```javascript
$(document).on('click', '.calculate-emissions', function() {
    // ❌ PROBLEMA: No hace nada útil con el cálculo
    // Solo valida que haya valores, pero no recalcula automáticamente
});
```

### Backend (emisiones-functions.php)
```php
function calcular_co2_ev($trafico_bytes, $dias_activo) {
    // ✅ EXISTE pero usa valores fijos muy básicos:
    $co2_base_por_dia = 0.015;       // g CO2 por día
    $co2_por_gb_trafico = 0.075;     // g CO2 por GB
    
    // ❌ NO consulta APIs externas
    // ❌ NO considera factores como ubicación del servidor, eficiencia energética
}
```

### AJAX Handler (ajax-handlers.php línea 51)
```php
function dominios_reseller_recalcular_co2() {
    // ✅ Endpoint existe: 'recalcular_co2'
    // ✅ Obtiene tráfico real de WHM
    // ✅ Guarda en BD
    // ❌ NO conecta con el botón "Calcular" en cards
}
```

---

## 🔧 Solución Propuesta

### Opción A: Usar Website Carbon API

**Ventajas:**
- ✅ API gratuita y bien documentada
- ✅ Datos actualizados del Grid Intensity
- ✅ Considera ubicación geográfica del servidor
- ✅ Incluye cálculo de transferencia de datos

**Desventajas:**
- ⚠️ Rate limits (necesita caché)
- ⚠️ Requiere que el dominio esté online y accesible

**Endpoint:**
```
GET https://api.websitecarbon.com/site?url={domain}
```

**Respuesta ejemplo:**
```json
{
  "url": "ejemplo.com",
  "bytes": 2456789,
  "cleanerThan": 0.65,
  "statistics": {
    "adjustedBytes": 2456789,
    "energy": 0.00163,
    "co2": {
      "grid": {
        "grams": 0.65,
        "litres": 0.43
      },
      "renewable": {
        "grams": 0.16,
        "litres": 0.11
      }
    }
  },
  "timestamp": 1704480000
}
```

### Opción B: Usar CO2.js (JavaScript Library)

**Ventajas:**
- ✅ Cálculos locales (sin depender de API externa)
- ✅ Metodología Sustainable Web Design Model
- ✅ Sin rate limits
- ✅ Open source y mantenido

**Desventajas:**
- ⚠️ Requiere instalar librería npm en el plugin
- ⚠️ Cálculos menos precisos sin datos reales de grid intensity

**Instalación:**
```bash
npm install @tgwf/co2
```

**Uso en PHP (vía Node.js):**
```javascript
const { co2 } = require('@tgwf/co2');
const emissions = new co2();

// Por página vista
const bytes = 2456789; // Tamaño de transferencia
const co2PerVisit = emissions.perVisitCO2(bytes);

// Por visita de retorno (caché)
const co2Return = emissions.perVisitCO2(bytes, true);
```

### Opción C: Híbrida (Recomendada) 🏆

Combinar ambas:
1. **Intentar Website Carbon API** para dominios activos
2. **Fallback a CO2.js** si la API falla o está offline
3. **Cachear resultados** por 24 horas

---

## 📝 Implementación Detallada

### PASO 1: Crear función de cálculo con Website Carbon

**Archivo:** `includes/emisiones-co2-api.php` (NUEVO)

```php
<?php
if (!defined('ABSPATH')) exit;

/**
 * Calcula CO2 usando Website Carbon API
 * 
 * @param string $domain Dominio a analizar
 * @param int $trafico_bytes Tráfico mensual en bytes
 * @param int $dias_activo Días desde creación
 * @return array|false Array con datos o false si error
 */
function dr_calcular_co2_website_carbon($domain, $trafico_bytes, $dias_activo) {
    
    // 1. Verificar caché (24h)
    $cache_key = 'dr_co2_' . md5($domain);
    $cached = get_transient($cache_key);
    
    if ($cached !== false) {
        return $cached;
    }
    
    // 2. Llamar a Website Carbon API
    $url = 'https://api.websitecarbon.com/site?url=' . urlencode($domain);
    
    $response = wp_remote_get($url, [
        'timeout' => 10,
        'headers' => [
            'User-Agent' => 'Replanta-Dominios-Reseller/1.0'
        ]
    ]);
    
    if (is_wp_error($response)) {
        error_log('Website Carbon API error: ' . $response->get_error_message());
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (!isset($data['statistics']['co2']['grid']['grams'])) {
        return false;
    }
    
    // 3. Calcular emisiones totales
    $co2_por_vista = $data['statistics']['co2']['grid']['grams']; // CO2 por visita
    
    // Estimar visitas basadas en tráfico
    $bytes_promedio_por_vista = $data['bytes'] ?? 2000000; // ~2MB por defecto
    $visitas_estimadas = $trafico_bytes / $bytes_promedio_por_vista;
    
    // CO2 total = CO2 por visita × visitas estimadas
    $co2_total_trafico = $co2_por_vista * $visitas_estimadas;
    
    // Añadir emisiones base por hosting (servidor encendido 24/7)
    $co2_base_servidor = $dias_activo * 0.015; // 15mg por día (hosting renovable)
    
    $resultado = [
        'co2_trafico_gramos' => round($co2_total_trafico, 3),
        'co2_base_gramos' => round($co2_base_servidor, 3),
        'co2_total_gramos' => round($co2_total_trafico + $co2_base_servidor, 3),
        'visitas_estimadas' => round($visitas_estimadas),
        'bytes_por_vista' => $bytes_promedio_por_vista,
        'cleaner_than' => $data['cleanerThan'] ?? 0,
        'fuente' => 'website_carbon',
        'timestamp' => time()
    ];
    
    // 4. Cachear por 24 horas
    set_transient($cache_key, $resultado, DAY_IN_SECONDS);
    
    return $resultado;
}

/**
 * Fallback: Calcular CO2 con fórmula local (CO2.js style)
 * 
 * @param int $trafico_bytes Tráfico en bytes
 * @param int $dias_activo Días activo
 * @param string $server_location 'uk' o 'usa'
 * @return array Resultado del cálculo
 */
function dr_calcular_co2_local($trafico_bytes, $dias_activo, $server_location = 'uk') {
    
    // Grid intensity (gramos CO2 por kWh)
    $grid_intensity = [
        'uk' => 233,   // Reino Unido: 233g CO2/kWh (2024)
        'usa' => 417   // USA promedio: 417g CO2/kWh
    ];
    
    $intensity = $grid_intensity[$server_location] ?? 300;
    
    // Sustainable Web Design Model (simplificado)
    // kWh por GB transferido = 0.81 kWh/GB (promedio datacenter + red)
    $kwh_por_gb = 0.81;
    $trafico_gb = $trafico_bytes / (1024 ** 3);
    
    // CO2 del tráfico = GB × kWh/GB × grid intensity
    $co2_trafico = $trafico_gb * $kwh_por_gb * ($intensity / 1000); // en gramos
    
    // CO2 base del servidor (hosting 24/7)
    $co2_base = $dias_activo * 0.015; // 15mg/día para hosting renovable
    
    $resultado = [
        'co2_trafico_gramos' => round($co2_trafico, 3),
        'co2_base_gramos' => round($co2_base, 3),
        'co2_total_gramos' => round($co2_trafico + $co2_base, 3),
        'trafico_gb' => round($trafico_gb, 3),
        'grid_intensity' => $intensity,
        'fuente' => 'local_calculation',
        'timestamp' => time()
    ];
    
    return $resultado;
}

/**
 * Función principal: Intenta API, fallback a local
 * 
 * @param string $domain Dominio
 * @param int $trafico_bytes Tráfico en bytes
 * @param int $dias_activo Días activo
 * @param string $server_location Ubicación servidor
 * @return array Resultado del cálculo
 */
function dr_calcular_co2_inteligente($domain, $trafico_bytes, $dias_activo, $server_location = 'uk') {
    
    // 1. Intentar con Website Carbon API
    $resultado_api = dr_calcular_co2_website_carbon($domain, $trafico_bytes, $dias_activo);
    
    if ($resultado_api !== false) {
        return $resultado_api;
    }
    
    // 2. Fallback a cálculo local
    error_log("Website Carbon API falló para $domain, usando cálculo local");
    return dr_calcular_co2_local($trafico_bytes, $dias_activo, $server_location);
}
```

---

### PASO 2: Actualizar AJAX Handler

**Archivo:** `includes/ajax-handlers.php`

**MODIFICAR la función `dominios_reseller_recalcular_co2()`:**

```php
function dominios_reseller_recalcular_co2()
{
    verificar_nonce_ajax();
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'No autorizado.']);
    }

    $domain = sanitize_text_field($_POST['domain'] ?? '');
    $server = sanitize_text_field($_POST['server'] ?? 'uk');

    if (!$domain) {
        wp_send_json_error(['message' => 'Dominio no válido.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'dominios_reseller';

    // Obtener datos del dominio
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE domain = %s", $domain));
    
    if (!$row) {
        wp_send_json_error(['message' => 'Dominio no encontrado en BD.']);
    }

    // Calcular días activo
    $fecha_emision = $row->fecha_emision ? strtotime($row->fecha_emision) : strtotime('-30 days');
    $dias_activo = max(1, round((time() - $fecha_emision) / 86400));

    // Obtener tráfico real de WHM
    $opts = get_option('dominios_reseller_options');
    $token_key = $server . '_whm_token';
    $token = sanitize_text_field($opts[$token_key] ?? '');

    if (!$token) {
        wp_send_json_error(['message' => 'API token no configurado para ' . strtoupper($server)]);
    }

    $trafico_bytes = obtener_trafico_real($domain, $token, $server);

    if ($trafico_bytes === false) {
        wp_send_json_error(['message' => 'Error al obtener tráfico de WHM.']);
    }

    // 🆕 NUEVO: Usar cálculo inteligente con API
    require_once plugin_dir_path(__FILE__) . 'emisiones-co2-api.php';
    
    $resultado = dr_calcular_co2_inteligente($domain, $trafico_bytes, $dias_activo, $server);

    // Guardar en BD
    $updated = $wpdb->update($table, [
        'co2_evaded' => $resultado['co2_total_gramos']
    ], ['domain' => $domain]);

    if ($updated !== false) {
        wp_send_json_success([
            'co2_evaded' => $resultado['co2_total_gramos'],
            'detalles' => $resultado,
            'message' => sprintf(
                '✅ CO2 calculado: %.2f g (Fuente: %s)',
                $resultado['co2_total_gramos'],
                $resultado['fuente'] === 'website_carbon' ? 'Website Carbon API' : 'Cálculo local'
            )
        ]);
    } else {
        wp_send_json_error(['message' => 'Error al guardar en BD.']);
    }
}
```

---

### PASO 3: Conectar botón "Calcular" con AJAX

**Archivo:** `assets/js/admin.js`

**REEMPLAZAR el evento click de `.calculate-emissions-card`:**

```javascript
// Calcular emisiones para un dominio (CARDS VIEW)
$(document).on('click', '.calculate-emissions-card', function(e) {
    e.preventDefault();
    
    const button = $(this);
    const domain = button.data('domain');
    const server = button.data('server');
    const card = button.closest('.domain-card');
    const co2Input = card.find('.co2-input-card');

    if (!domain || !server) {
        showNotice('⚠️ Datos incompletos del dominio', 'warning');
        return;
    }

    // Deshabilitar botón y mostrar loading
    button.text('⏳ Calculando...').prop('disabled', true);

    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'recalcular_co2',
            domain: domain,
            server: server,
            nonce: dominios_reseller_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                const co2 = response.data.co2_evaded;
                const detalles = response.data.detalles;
                
                // Actualizar input con nuevo valor
                co2Input.val(co2);
                
                // Mostrar detalles en tooltip o modal
                const mensaje = `
                    ✅ ${response.data.message}
                    
                    Detalles:
                    • Tráfico: ${detalles.trafico_gb || 'N/A'} GB
                    • CO2 tráfico: ${detalles.co2_trafico_gramos} g
                    • CO2 base: ${detalles.co2_base_gramos} g
                    • Total: ${detalles.co2_total_gramos} g
                    ${detalles.visitas_estimadas ? `• Visitas estimadas: ${detalles.visitas_estimadas}` : ''}
                `;
                
                showNotice(mensaje, 'success');
                
                // Marcar el campo como modificado para que se guarde
                co2Input.addClass('changed');
                
            } else {
                showNotice('❌ ' + (response.data?.message || 'Error desconocido'), 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            showNotice('❌ Error de conexión al calcular CO2', 'error');
        },
        complete: function() {
            button.text('Calcular').prop('disabled', false);
        }
    });
});

// También para la vista de tabla (.calculate-emissions)
$(document).on('click', '.calculate-emissions', function(e) {
    e.preventDefault();
    
    const button = $(this);
    const domain = button.data('domain');
    const server = button.data('server');
    const row = button.closest('tr');
    const co2Input = row.find('.co2-input');

    button.text('⏳ Calculando...').prop('disabled', true);

    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'recalcular_co2',
            domain: domain,
            server: server,
            nonce: dominios_reseller_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                co2Input.val(response.data.co2_evaded);
                showNotice('✅ ' + response.data.message, 'success');
                co2Input.addClass('changed');
            } else {
                showNotice('❌ ' + (response.data?.message || 'Error'), 'error');
            }
        },
        error: function() {
            showNotice('❌ Error de conexión', 'error');
        },
        complete: function() {
            button.text('Calcular').prop('disabled', false);
        }
    });
});
```

---

### PASO 4: Registrar nuevo AJAX action

**Archivo:** `dominios-reseller.php`

**VERIFICAR que existe (debería estar ya):**

```php
add_action('wp_ajax_recalcular_co2', 'dominios_reseller_recalcular_co2');
```

Si no existe, añadirlo en la sección de AJAX handlers.

---

### PASO 5: Incluir nuevo archivo en el plugin

**Archivo:** `dominios-reseller.php`

**AÑADIR después de las demás inclusiones:**

```php
// Incluir funciones de emisiones
require_once plugin_dir_path(__FILE__) . 'includes/emisiones-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/emisiones-co2-api.php'; // 🆕 NUEVO
require_once plugin_dir_path(__FILE__) . 'includes/ajax-handlers.php';
```

---

## 🧪 Testing

### Test 1: Calcular con API activa

1. Ir a `/wp-admin/admin.php?page=dominios-reseller`
2. Seleccionar un dominio de servidor USA o UK
3. Clic en "Calcular"
4. Verificar:
   - ✅ Campo CO2 se actualiza
   - ✅ Mensaje indica "Fuente: Website Carbon API"
   - ✅ Valor parece realista (no 0)

### Test 2: Fallback si API falla

1. Desconectar internet o bloquear `api.websitecarbon.com`
2. Clic en "Calcular"
3. Verificar:
   - ✅ Usa cálculo local
   - ✅ Mensaje indica "Fuente: Cálculo local"
   - ✅ Valor calculado igualmente

### Test 3: Caché funciona

1. Calcular un dominio
2. Borrar el valor del campo CO2
3. Calcular de nuevo inmediatamente
4. Verificar:
   - ✅ Respuesta instantánea (< 1s)
   - ✅ Valor idéntico

---

## 📈 Mejoras Futuras

### Fase 2: Integración CO2.js nativa

Si Website Carbon API da problemas de rate limit:

1. Instalar CO2.js en el plugin
2. Crear endpoint Node.js local
3. Llamar desde PHP vía `exec()` o REST local

### Fase 3: Dashboard de emisiones

- Gráfico de CO2 total evitado por mes
- Comparativa por servidor (UK vs USA)
- Top dominios más eficientes

### Fase 4: Certificados PDF

- Generar PDF con:
  - CO2 evitado total
  - Equivalente en árboles
  - Comparativa con hosting tradicional
  - Badge de "Hosting Verde Certificado"

---

## 🔗 Referencias

- [Website Carbon API](https://www.websitecarbon.com/api/)
- [CO2.js Documentation](https://developers.thegreenwebfoundation.org/co2js/overview/)
- [Sustainable Web Design Model](https://sustainablewebdesign.org/calculating-digital-emissions/)
- [Grid Intensity Data](https://app.electricitymaps.com/)

---

## ✅ Checklist de Implementación

- [ ] Crear `includes/emisiones-co2-api.php`
- [ ] Modificar `includes/ajax-handlers.php` (función `dominios_reseller_recalcular_co2`)
- [ ] Actualizar `assets/js/admin.js` (eventos click)
- [ ] Verificar AJAX action registrado en `dominios-reseller.php`
- [ ] Incluir nuevo archivo en `dominios-reseller.php`
- [ ] Test 1: API funciona
- [ ] Test 2: Fallback funciona
- [ ] Test 3: Caché funciona
- [ ] Documentar en README del plugin
- [ ] Commit y desplegar

---

**Estimación:** 2-3 horas de desarrollo + 1 hora de testing
**Prioridad:** Alta (funcionalidad core del plugin)
**Riesgo:** Bajo (tiene fallback robusto)
