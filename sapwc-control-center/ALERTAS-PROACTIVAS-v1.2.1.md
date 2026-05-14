# Control Center v1.2.1 — Sistema de Alertas Proactivas

## 📊 Resumen

**Versión:** v1.2.1  
**Fecha:** 31 marzo 2026  
**Tipo:** Enhancement — Sistema de monitoreo proactivo

Esta versión añade **detección automática de warnings transitorios** al Control Center, permitiendo identificar problemas de sincronización incluso cuando el sistema se auto-recupera exitosamente.

## 🎯 Caso de Uso Real

**Problema detectado:** Pedido 10498 de banbancosmetics.com

```
2026-03-30 22:41:39 [warning] Login SAP fallido - cURL error 28: Connection timed out
                             ↓ (sistema esperando)
2026-03-31 13:19:16 [info]    Pedido 10498 sincronizado correctamente
```

**Antes de v1.2.1:**  
✅ Sistema se recuperó → ✅ Pedido sincronizado → ❌ **Nadie se enteró del problema**

**Con v1.2.1:**  
✅ Sistema se recuperó → ✅ Pedido sincronizado → ✅ **Alert badge visible** → ✅ **Recomendación generada**

---

## 🚀 Nuevas Funcionalidades

### 1. Método `get_transient_warnings()`

**Ubicación:** `SAPWCC_Sites::get_transient_warnings( $site_key, $hours = 24 )`

**Qué hace:**
- Consulta logs remotos del sitio (últimas 200 entradas)
- Identifica warnings/errors con order_id
- Busca si ese pedido se sincronizó exitosamente DESPUÉS del error
- Devuelve solo los warnings que se auto-resolvieron

**Retorna:**
```php
[
    'count'            => 3,
    'warnings'         => [
        [
            'timestamp' => '2026-03-30 22:41:39',
            'level'     => 'warning',
            'operation' => 'sync_order',
            'message'   => 'Login SAP fallido - timeout',
            'order_id'  => 10498,
        ],
        // ...
    ],
    'orders_affected'  => [ 10498, 10502, 10511 ],
]
```

**Ventana temporal:** 24h por defecto (configurable hasta 200 logs)

### 2. Badge Visual en Site Cards

**Ubicación:** Dashboard → Sitios → Site Card Header

**Apariencia:**
```
┌─────────────────────────────────────────┐
│ 🟢 BanBan Cosmetics  [ℹ️ 3]  ← BADGE   │
│    HEALTHY                               │
├─────────────────────────────────────────┤
│ URL: banbancosmetics.com                │
│ Site ID: banban                         │
│ Plan: Business                          │
│ ...                                     │
└─────────────────────────────────────────┘
```

**Características:**
- Solo aparece si `count > 0`
- Color azul suave `#f0f6fc` (info, no alarma)
- Tooltip: "3 warnings auto-resueltos en 24h"
- Icono dashicons-info

### 3. Recomendación Automática

**Ubicación:** Dashboard → Recomendaciones

**Formato:**
```
ℹ️ BanBan Cosmetics
   3 warnings auto-resueltos en 48h (pedidos: 10498, 10502, 10511).
   → Sistema se recuperó automáticamente. Revisar logs para patrones.
```

**Tipo:** `info` (no `warning` ni `error`)

**Lookback period:** 48h (el doble que el badge para capturar patrones de fin de semana)

---

## 🔍 Detección de Patrones

### Algoritmo de Matching

1. **Extraer order_id de mensajes:**
   ```regex
   /pedido[^\d]*(\d+)/i
   ```
   Coincide con:
   - "Pedido 10498 sincronizado"
   - "Error pedido #10498"
   - "Sincronizando pedido: 10498"

2. **Clasificar logs:**
   - `warning|error` + order_id → `$warnings[]`
   - `info` + "sincronizado|creado" + order_id → `$successes[]`

3. **Filtrar solo transitorios:**
   - Solo warnings que tienen un success POSTERIOR
   - Comparación de timestamps: `success_timestamp > warning_timestamp`

### Ejemplos de Lo Que Captura

✅ **Captura:**
- Timeouts que se resolvieron con retry
- "CardCode not found" → luego se creó el cliente → pedido sincronizado
- Service Layer caído → se levantó → pedidos procesados

❌ **No captura:**
- Errores persistentes sin resolución
- Logs sin order_id (errores genéricos)
- Logs fuera de la ventana temporal (24h/48h)

---

## 📁 Archivos Modificados

### 1. `includes/class-sites.php`

**Líneas añadidas:** ~120 líneas

**Cambios:**
- Añadido método `get_transient_warnings()` (líneas 124-230)
- Modificado `generate_recommendations()` — añadido check de transient warnings (líneas 465-481)

### 2. `templates/page-dashboard.php`

**Líneas modificadas:** 10 líneas

**Cambios:**
- Añadida llamada a `get_transient_warnings()` en loop de site cards (línea 80)
- Añadido badge visual en `<h3>` del header (líneas 87-93)

### 3. `assets/control-center.css`

**Líneas añadidas:** 12 líneas

**Cambios:**
- Añadidas reglas `.sapwcc-tw-badge` (líneas 68-78)
- Estilo: fondo azul claro, borde sutil, icon + número

### 4. `sapwc-control-center.php`

**Líneas modificadas:** 2 líneas

**Cambios:**
- Versión: `1.2.0` → `1.2.1` (línea 5)
- Constante: `SAPWCC_VERSION` → `1.2.1` (línea 15)

---

## ✅ Verificación

### PHP Lint
```bash
php -l includes/class-sites.php        # ✅ No syntax errors
php -l templates/page-dashboard.php    # ✅ No syntax errors
php -l sapwc-control-center.php        # ✅ No syntax errors
```

### Signature Test
```php
Method: SAPWCC_Sites::get_transient_warnings( string $key, int $hours = 24 ): array
   ✅ Público
   ✅ Estático
   ✅ Parámetros correctos
   ✅ Return type array
```

---

## 🎨 Interfaz de Usuario

### Estado Normal (Sin Warnings)
```
🟢 BanBan Cosmetics
   HEALTHY
```

### Con Warnings Transitorios (Nuevo)
```
🟢 BanBan Cosmetics  [ℹ️ 3]
   HEALTHY
```
Tooltip: "3 warnings auto-resueltos en 24h"

### Recomendaciones (Nuevo)
```
┌─────────────────────────────────────────────────────┐
│ ℹ️ 3 warnings auto-resueltos en 48h                 │
│    (pedidos: 10498, 10502, 10511).                  │
│    Sistema se recuperó automáticamente.             │
│    → Revisar logs para patrones.                    │
└─────────────────────────────────────────────────────┘
```

---

## 🔮 Próximos Pasos (Futuras Versiones)

### v1.3.0 — Notificaciones Opcionales
- [ ] Email alert cuando `count > X` en 24h
- [ ] Webhook notification para integración Slack/Discord
- [ ] Configuración de umbrales por sitio

### v1.4.0 — Analytics Dashboard
- [ ] Histórico de warnings transitorios (últimos 30 días)
- [ ] Gráfica de timing: error → success (cuánto tardó en recuperarse)
- [ ] Top 5 pedidos con más reintentos
- [ ] Patrón horario: ¿los timeouts siempre ocurren de noche?

### v1.5.0 — Auto-Remediation
- [ ] Si pattern detectado (ej: SAP siempre caído 22h-8h)
  → Desactivar crons nocturnos
  → Acumular en queue
  → Ejecutar masivo a las 8h

---

## 📖 Uso del Operador

### Inspeccionar Warnings Transitorios

1. **Ver badge en dashboard:**
   - Abrir Control Center
   - Localizar site card con badge `[ℹ️ N]`
   - Tooltip indica cantidad y período

2. **Ver detalles en recomendaciones:**
   - Scroll a sección "Recomendaciones"
   - Buscar card tipo `info` con icon dashicons-info
   - Ver lista de pedidos afectados

3. **Revisar logs completos:**
   - Click botón `📋 Logs` en site card
   - Filtrar por `level: warning`
   - Buscar los order_ids listados
   - Verificar timeline: warning → success

4. **Evaluar si requiere acción:**
   - **1-2 warnings/día** → Normal (red glitches, SAP reinicio)
   - **5-10 warnings/día** → Patrón identificable (horario, producto específico)
   - **>20 warnings/día** → Problema subyacente (actualizar a `warning` real)

### Casos de Uso

**Ejemplo 1: SAP apagado de noche**
```
Badge: [ℹ️ 15]
Logs: 15 timeouts entre 22:00-23:59
      15 successes entre 08:00-09:00
Acción: Configurar crons para no ejecutar 22h-8h
```

**Ejemplo 2: CardCode duplicado intermitente**
```
Badge: [ℹ️ 3]
Logs: 3 x "CardCode ya existe" → retry → success
Acción: Mejorar lógica de dedup en addon
```

**Ejemplo 3: Red glitches aleatorios**
```
Badge: [ℹ️ 2]
Logs: 2 timeouts (martes 14:23, jueves 09:11)
Acción: Ninguna (ruido de red normal)
```

---

## 🧪 Testing en Producción

### Sitio de Prueba: banbancosmetics.com

1. **Esperar hasta v2.15.1:**
   - BanBan necesita actualizar a Suite v2.15.1 (tiene Control API)

2. **Configurar en Control Center:**
   - Añadir site con URL + secret
   - Guardar site_id en flags.json
   - Git push

3. **Generar warning transitorio:**
   ```bash
   # Apagar SAP Service Layer
   sudo systemctl stop sapb1-sl
   
   # Esperar 5 minutos (cron intenta sync)
   
   # Encender SAP
   sudo systemctl start sapb1-sl
   
   # Esperar 10 minutos (cron reintenta)
   ```

4. **Verificar badge aparece:**
   - Refresh Control Center dashboard
   - Badge `[ℹ️ 1]` debe aparecer en BanBan card
   - Recomendación debe estar en la lista

---

## 📝 Notas Técnicas

### Performance

- **Cache:** No hay cache (consulta realtime API)
- **Timeout:** 15s por sitio
- **Costo:** 1 request REST por site card render
- **Optimización futura:** Añadir transient cache de 5 min

### Limitaciones

- **Lookback:** Máximo 200 logs (límite del endpoint REST)
- **Order ID detection:** Regex simple (puede fallar con formatos custom)
- **Timezone:** Usa `current_time()` del Control Center, no del sitio remoto

### Security

- **Authentication:** X-SAPWC-Secret header (ya existente)
- **Data exposure:** Solo cuenta de warnings (no expone datos sensibles)
- **No logging:** No guarda warnings localmente (privacy)

---

## 📦 Instalación

**En Control Center (sitio local/HQ):**

1. Actualizar plugin:
   ```bash
   cd wp-content/plugins/sapwc-control-center
   git pull origin main
   ```

2. Verificar versión en WP admin:
   - Plugins → SAP Woo Control Center
   - Debe mostrar `v1.2.1`

3. Listo — feature activa automáticamente

**En sitios remotos:**

- Requieren Suite v2.15.1+ (para endpoint `/control/logs`)
- Auto-update via PUC una vez publicada v2.15.1

---

## 🐛 Troubleshooting

### Badge no aparece

**Posibles causas:**
1. Sitio remoto < v2.15.1 → No tiene endpoint `/control/logs`
2. Secret incorrecto → API returns 401
3. No hay warnings en ventana de 24h → `count = 0`
4. CSS no cargado → Badge existe en DOM pero invisible

**Debug:**
```javascript
// En browser console del Control Center
document.querySelectorAll('.sapwcc-tw-badge').forEach(b => {
    console.log(b.textContent, b.title);
});
```

### Recomendación no aparece

**Posibles causas:**
1. Lookback period 48h vacío
2. Warnings sin order_id (no se pueden correlacionar)
3. Warnings sin success posterior (todavía pendientes)

**Debug:**
```php
// En console PHP del Control Center
$tw = SAPWCC_Sites::get_transient_warnings('banban', 48);
print_r($tw);
```

---

## 🎓 Conclusión

El sistema de alertas proactivas convierte el Control Center de un panel **reactivo** (solo ves problemas activos) a un panel **predictivo** (ves patrones antes de que se conviertan en crisis).

**Antes:** "¿Por qué el pedido X no se sincronizó?"  
**Ahora:** "El sistema tuvo 15 timeouts anoche pero se recuperó solo. Quizás sea hora de cambiar el horario de crons."

---

**Desarrollado por:** Replanta Dev  
**Fecha release:** 31 marzo 2026  
**Branch:** `main`  
**Commit:** `feat: v1.2.1 proactive alerts for transient warnings`
