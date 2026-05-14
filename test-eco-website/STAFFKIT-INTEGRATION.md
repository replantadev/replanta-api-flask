# Integración Test Eco Website ↔ StaffKit

## 📋 Resumen

Este plugin ahora se integra automáticamente con **StaffKit Connector** para capturar leads cualificados cuando usuarios completan auditorías eco-performance y proporcionan su email.

## 🎯 ¿Qué hace?

Cuando un usuario:
1. Completa una auditoría eco-performance
2. Proporciona su email para recibir consejos
3. Pasa la verificación Turnstile (captcha)

El sistema **automáticamente**:
- Envía el lead a StaffKit con datos completos de la auditoría
- Registra el evento en `lead_events`
- Crea/actualiza el prospecto en la lista "Web Leads"
- Permite segmentación por eco-score para campañas personalizadas

## ⚙️ Requisitos

### En WordPress (replanta.net)

1. **Plugin StaffKit Connector v1.1.0+** instalado y activo
2. **Configuración** en `Ajustes → StaffKit`:
   ```
   URL de StaffKit: https://staff.replanta.dev
   API Key: sk_live_replanta_2026_webhook_secure_key
   ✅ Auto-sincronizar: Activado
   ```

### En StaffKit (staff.replanta.dev)

1. **Webhook endpoint** operativo: `/api/webhooks/lead-capture.php`
2. **Tabla `lead_events`** creada en base de datos
3. **Lista "Web Leads"** (se crea automáticamente)
4. **Clase LeadCapture.php** funcionando

## 📊 Datos Enviados

```php
[
    'email' => 'usuario@ejemplo.com',
    'website' => 'ejemplo.com',
    'source' => 'Eco Performance Audit - Replanta',
    'source_url' => 'https://replanta.net/r/ejemplo.com',
    'eco_score' => 'B',           // A, B, C, D, E
    'co2_visit' => 2.35,          // Gramos de CO2
    'audit_data' => [
        'mobile_score' => 85,
        'desktop_score' => 92,
        'eco_snapshot_score' => 78,
        'page_weight' => 2457,    // KB
        'green_hosting' => true,
        'carbon_rating' => 'B',
        'report_id' => 123
    ]
]
```

## 🔍 Segmentación Automática

Los leads se pueden segmentar por:

- **Score A (90-100)**: Empresas con excelente sostenibilidad → Email educativo, upsell servicios premium
- **Score B (75-89)**: Buenos candidatos → Tips para llegar a A, optimizaciones puntuales
- **Score C (60-74)**: Necesitan mejoras → Auditoría completa, consultoría
- **Score D (40-59)**: Problemas serios → Propuesta de rediseño sostenible
- **Score E (<40)**: Urgente → Campaña agresiva de transformación

## 🧪 Testing

### Probar Integración Completa

1. En replanta.net, ir a página con shortcode `[eco_performance_snapshot]`
2. Auditar un dominio (ej: `ejemplo.com`)
3. En el popup de email, ingresar un email válido
4. Resolver el captcha Turnstile
5. Verificar en StaffKit:
   ```
   - Ir a app/lists.php → Lista "Web Leads"
   - Debería aparecer el lead con el email
   - En lead_events debería haber evento "lead_captured"
   ```

### Debug

Si no funciona:

1. **Verificar StaffKit Connector instalado**:
   ```php
   var_dump(function_exists('staffkit_send_lead')); // Debe ser true
   ```

2. **Verificar configuración**:
   - WordPress Admin → Ajustes → StaffKit
   - Auto-sincronizar debe estar ACTIVADO
   - URL y API Key correctas

3. **Ver logs WordPress**:
   ```php
   // wp-content/debug.log
   [error] StaffKit API Error: HTTP 401 - Invalid API Key
   ```

4. **Ver logs StaffKit**:
   ```bash
   ssh replanta@staff.replanta.dev
   tail -f ~/staff.replanta.dev/error_log
   ```

## 🚀 Uso en Campañas

### Ejemplo: Campaña Score B

1. En StaffKit, crear secuencia "Mejora a Score A"
2. Templates:
   - **Día 0**: "¡Tu sitio tiene Score B! 3 mejoras rápidas para llegar a A"
   - **Día 3**: "Caso de estudio: Cómo X redujo 40% su CO2"
   - **Día 7**: "¿Hablamos? 15 min para un plan personalizado"

3. Crear campaña:
   - Lista: Web Leads
   - Filtro: eco_score = 'B'
   - Secuencia: "Mejora a Score A"
   - Auto-enroll: Activado

### Ejemplo: Campaña Score D/E (Urgente)

1. Secuencia "Transformación Sostenible"
2. Templates más agresivos:
   - **Día 0**: "Tu sitio emite X kg CO2/mes - Esto te está costando clientes"
   - **Día 2**: "Auditoría completa GRATIS (válido 48h)"
   - **Día 5**: "Última oportunidad - Precio especial rediseño sostenible"

## 📈 Métricas a Trackear

En StaffKit Analytics (próxima fase):

- **Leads capturados/día** desde eco-audits
- **Distribución por score** (cuántos A, B, C, D, E)
- **Tasa de apertura** por segmento de score
- **Conversión** score → reunión → cliente
- **CO2 promedio** de leads capturados
- **Dominios más auditados** (tendencias)

## 🔧 Mantenimiento

### Actualizar API Key

Si cambias la API Key en StaffKit:

1. Generar nueva en `admin/api-keys.php`
2. Actualizar en WordPress: `Ajustes → StaffKit → API Key`
3. Guardar cambios
4. Probar conexión (botón "Probar Conexión")

### Troubleshooting

**Error: "Invalid API Key"**
- Verificar que la API Key no tenga espacios
- Copiar/pegar directamente desde StaffKit

**Leads no aparecen**
- Verificar que "Auto-sincronizar" esté activado
- Revisar error_log de WordPress
- Verificar que el webhook responda 200

**Datos incompletos**
- Verificar que el reporte tiene todos los campos
- Revisar `$report` array en handle_save_email()

## 📝 Changelog

- **v0.2.0** (2026-01-10): Integración inicial con StaffKit Connector
  - Envío automático de leads al guardar email
  - Tracking completo con eco-score y datos de auditoría
  - Documentación completa de integración

## 🤝 Soporte

Para issues o mejoras:
- Repositorio StaffKit: https://github.com/replantadev/staffkit
- Repositorio TEW: (interno Replanta Dev)
- Contacto: equipo técnico Replanta
