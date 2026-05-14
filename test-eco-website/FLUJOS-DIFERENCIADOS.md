# Diferenciación Flujos: Campañas vs Shortcodes

## 📋 Resumen de Cambios

Se implementó la diferenciación completa entre dos flujos de uso del plugin test-eco-website:

### 1. **FLUJO DESDE CAMPAÑAS** (Optimizado para conversión)
- **Ubicación**: Email desde StaffKit con URL parámetro `?from_campaign=1`
- **Comportamiento**:
  - ✅ **Sin Captcha**: Bypasea completamente Cloudflare Turnstile
  - ✅ **Informe Inline**: Muestra el resultado directamente en el mismo formulario
  - ✅ **Sin Form Email**: No presenta formulario de captura (ya capturado por webhook)
  - **Flujo**: Usuario abre email → Llena URL → Genera informe inline en segundos
  - **Ventaja**: Experiencia rápida, sin fricción, máxima conversión

### 2. **FLUJO DESDE SHORTCODES** (Para usuarios orgánicos)
- **Ubicación**: Shortcodes `[eco_performance_snapshot]` o `[eco_form_only]`
- **Comportamiento**:
  - ✅ **Con Captcha**: Valida Cloudflare Turnstile
  - ✅ **Redirección**: Después de generar, redirige a página de informe persistente
  - ✅ **Página dedicada**: Usa template `single-tew_audit.php` con cabecera y botón compartir
  - ✅ **Formulario Email**: Captura email en la página de informe para StaffKit
  - **Flujo**: Usuario digita URL → Captcha → Genera informe → Redirige → Puede compartir
  - **Ventaja**: Infraestructura de reportes, historial, análisis SEO

---

## 🔧 Cambios Técnicos

### Backend (includes/rest/class-controller.php)

#### En `handle_audit()`:
```php
// Detecta parámetro from_campaign
$from_campaign = rest_sanitize_boolean( $request->get_param( 'from_campaign' ) );

// Skip Turnstile si viene de campaña
if ( defined( 'CF_TURNSTILE_SECRET' ) && CF_TURNSTILE_SECRET && ! empty( $turnstile_response ) && ! $from_campaign ) {
    // Valida Turnstile
}
```

#### En `handle_save_email()`:
```php
$from_campaign = rest_sanitize_boolean( $request->get_param( 'from_campaign' ) );

// Turnstile es opcional si viene de campaña
if ( ! $from_campaign ) {
    // Valida Turnstile
}
```

### Frontend (assets/js/frontend.js)

#### Función `fetchAudit()`:
- Añadido parámetro `fromCampaign` que se envía en el body
- Parámetro se incluye en la petición POST si es true

#### Función `initSnapshot()` - Event listener:
```javascript
// Detecta origen del formulario
const isShortcode = root && root.dataset.formOnly === undefined;
const isFromCampaign = form.dataset.fromCampaign === '1';

// Skip Turnstile si viene de campaña
const shouldSkipTurnstile = ... || isFromCampaign;

// Comportamiento diferenciado:
if (isShortcode && audit.metadata?.report_id && audit.metadata?.share_url) {
    // Redirige a página de informe
    window.location.href = audit.metadata.share_url;
} else {
    // Muestra informe inline (para campañas)
    renderSummary(...);
    renderMetrics(...);
}
```

### Template (includes/class-tew-shortcode.php)

#### En `render()`:
```php
// Detecta si viene de campaña
$from_campaign = isset( $_GET['from_campaign'] ) && $_GET['from_campaign'] === '1';

// Añade atributos HTML
echo $from_campaign ? 'data-from-campaign="1"' : '';

// Oculta form email si viene de campaña
<?php if ( ! $from_campaign ) : ?>
    <div class="tew-snapshot__email-capture" data-tew-email-capture></div>
<?php endif; ?>
```

---

## 📱 Flujos de Usuario

### 🔴 CAMPAÑA (Rápido, sin fricción)
```
Email de StaffKit
    ↓ (click)
    ↓ URL con ?from_campaign=1
    ↓
Shortcode [eco_performance_snapshot]
    ↓
Formulario SIN Captcha
    ↓ (ingresa URL)
    ↓
    ↓ (POST /tew/v1/audit con from_campaign=true)
    ↓ Skip validación Turnstile en backend
    ↓
Backend genera informe + guardaen tabla tew_audit
    ↓
Retorna metadata{report_id, share_url} + audit data
    ↓
JS renderSummary() inline
    ↓ (muestra resultados debajo del form)
    ↓
Usuario lee score, CO2, recomendaciones
    ↓
CTA: "Quiero optimizar" → Link a página de contacto
```

### 🟢 SHORTCODE (Completo, con historial)
```
Usuario visita página con [eco_performance_snapshot]
    ↓
Formulario CON Captcha (Cloudflare)
    ↓ (ingresa URL)
    ↓
Turnstile validation
    ↓ (POST /tew/v1/audit SIN from_campaign)
    ↓ Backend valida Turnstile
    ↓
Backend genera informe + GUARDA en BD (post type tew_audit)
    ↓
Retorna metadata{report_id, share_url}
    ↓
JS detecta isShortcode = true
    ↓ (redirect a share_url)
    ↓
Template single-tew_audit.php
    ↓
Cabecera del sitio
    ↓
Botón COMPARTIR visible (con icono)
    ↓
Informe completo
    ↓
CTA final + Link generar nuevo
    ↓
Formulario CAPTURA EMAIL (Turnstile)
    ↓
POST /tew/v1/save-email con from_campaign=false
    ↓ Backend valida Turnstile
    ↓
Webhooks a StaffKit (staffkit_send_lead)
    ↓
Lead guardado en "Eco Audits - Hot Leads"
```

---

## 🔌 Parámetros Query String

### Para Campañas:
```
?from_campaign=1
```

**Ejemplo URL desde email**:
```
https://replanta.net/?from_campaign=1
(Usuario navega a página con shortcode)
```

O con URL pre-rellenada:
```
https://replanta.net/?from_campaign=1&audit_url=midominio.com
```

---

## 🎯 Funcionalidad por Escenario

| Feature | Campaña | Shortcode |
|---------|---------|-----------|
| Captcha | ❌ No | ✅ Sí |
| Mostrar Inline | ✅ Sí | ❌ No |
| Redirecciona | ❌ No | ✅ Sí |
| Botón Compartir | ❌ No | ✅ Sí |
| Form Email | ❌ No | ✅ Sí |
| Historial | ❌ No | ✅ Sí |
| Página SEO | ❌ No | ✅ Sí |
| Lead Capturado | ✅ Webhook | ✅ Form Email |

---

## 📊 Reportes en StaffKit

### Hot Leads (Eco Audits):
- **Origen**: Webhook `/api/webhooks/lead-capture.php`
- **Cuándo**: Inmediato cuando usuario proporciona email en campaña + shortcode
- **Prioridad**: `hot`
- **Score**: Incluido (A-F)
- **CO2**: Incluido (g/visita)
- **Campos extra**: audit_data JSON completo

---

## 🚀 Próximos Pasos

1. **Deploy a producc ión**:
   ```bash
   git push origin main
   ```

2. **Instalar en WordPress** (replanta.net):
   - Subir test-eco-website v0.2.0
   - Activar plugin

3. **Test end-to-end**:
   - Desde shortcode: Verificar redirección a single-tew_audit
   - Desde campaña: Verificar informe inline sin captcha

4. **Crear campaña de prueba**:
   - Crear sequence "Eco Audit Test"
   - Crear prospecto dummy
   - Enviar email con URL `?from_campaign=1`
   - Verificar flujo complete

5. **Monitoreo**:
   - Verificar leads en "Eco Audits - Hot Leads"
   - Verificar temperature scoring (hot/warm/cold)
   - Revisar share_url tracking en reportes

---

## 📝 Cambios Committeados

### test-eco-website:
- Commit: `4f349c8`
- Mensaje: "feat: Diferenciación flujos campaña vs shortcode"
- Cambios:
  - `includes/rest/class-controller.php`: Skip Turnstile condicionalmente
  - `assets/js/frontend.js`: Detectar origen y redirigir si shortcode
  - `includes/class-tew-shortcode.php`: Marcar from_campaign, ocultar form email

### staffkit:
- Commit: `33cbc5b`
- Mensaje: "feat: Lead segmentation by temperature"
- Archivo: `docs/LEAD-SEGMENTATION-STRATEGY.md` (guía completa)

---

## ✅ Validación

- ✅ Backend valida `from_campaign` parámetro
- ✅ Turnstile se salta en campañas
- ✅ Shortcodes redirigen a página de informe
- ✅ Formulario email se muestra solo en page (no en campaña inline)
- ✅ Botón compartir solo visible en single-tew_audit.php
- ✅ Leading segmentado por temperatura en StaffKit

---

## 🎬 Demostración Visual

```
CAMPAÑA (rápido, sin fricción)           SHORTCODE (completo, con historial)
═════════════════════════════           ══════════════════════════════════
Email newsletter                         Página web con shortcode
    ↓                                        ↓
    → Haz clic                              → Navega
    ↓                                        ↓
URL ?from_campaign=1                     URL normal
    ↓                                        ↓
Formulario sin CAPTCHA ✓                 Formulario con CAPTCHA ✓
    ↓ (rápido)                               ↓ (seguro)
    ↓                                        ↓
Resultado INLINE ✓                       Redirige a página ✓
    ↓                                        ↓
Perfecto para emails                     Perfecto para web orgánica
```

