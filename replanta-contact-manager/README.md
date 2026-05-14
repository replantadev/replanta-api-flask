# Replanta Contact Manager

🎯 **Sistema centralizado** para gestionar TODAS las solicitudes de contacto en un solo lugar.

## ¿Qué hace este plugin?

Unifica la gestión de formularios:
- ✅ **Plan Solidario** - Solicitudes de plan solidario
- ✅ **Auditorías WordPress** - Solicitudes de auditoría/mantenimiento  
- ✅ **Contacto General** - Formularios de contacto genéricos
- ✅ **Elementor Forms** - Captura automática de TODOS los formularios Elementor (incluido Contact Form ff38150)

Todo en un **único panel** super visual con filtros, estados y columnas personalizadas.

## Características principales

### 🎨 UI Visual y funcional
- Tabla de solicitudes con columnas: Tipo, Nombre, Email, Teléfono, Estado, País, Fecha
- Badges de colores para tipos (solidario verde, auditoría azul, contacto morado, elementor rosa)
- Filtros por tipo y estado
- Estados: Pendiente → Contactado → Procesado → Convertido → Spam

### 🔒 Seguridad multicapa
- **Cloudflare Turnstile** (usa constantes de wp-config.php)
- **Geo-blocking** España, Andorra y LATAM
- **Rate limiting** por IP (5 solicitudes / 15 min)
- **Honeypot** anti-bots configurable
- **X-WP-Nonce** CSRF protection
- **Detección de duplicados** (24h)

### 📝 Captura Elementor automática
- Hook `elementor_pro/forms/new_record`
- Detecta automáticamente: name, email, phone, message
- Guarda TODOS los campos del form en metadata
- Incluye Contact Form (ff38150) y cualquier otro

### 📊 Dashboard widget
- Resumen visual de pendientes, contactados y convertidos
- Acceso directo al listado completo

### 🔗 REST API endpoints
```
POST /wp-json/replanta/v1/contact/solidario
POST /wp-json/replanta/v1/contact/auditoria
POST /wp-json/replanta/v1/contact/general
```

## Instalación

1. Copiar carpeta a `/wp-content/plugins/`
2. Activar desde WordPress admin
3. Aparecerá "Solicitudes" en el menú lateral
4. Configurar en **Solicitudes → Ajustes**

## Configuración

### Cloudflare Turnstile (recomendado)

Añade en `wp-config.php`:
```php
define('CF_TURNSTILE_SITEKEY', '0x4AAAAAAB9AM8TVibxJ797V');
define('CF_TURNSTILE_SECRET', 'tu-secret-key');
```

### Ajustes disponibles

**Notificaciones**
- Email receptor de notificaciones

**Seguridad**
- Activar/desactivar Turnstile
- Activar/desactivar geo-blocking
- Rate limit (solicitudes por IP)
- Campo honeypot personalizado

**Elementor**
- Activar captura automática de formularios
- Excluir formularios específicos

## Ejemplo: Actualizar formulario Plan Solidario

Tu formulario actual que apuntaba a `/wp-json/replanta/v1/solidario` ahora debe apuntar a:

```html
<form id="solidario-form">
    <input type="text" name="name" required>
    <input type="email" name="email" required>
    <input type="text" name="entity_type" required>
    <input type="text" name="entity_name" required>
    <textarea name="message"></textarea>
    
    <!-- Honeypot -->
    <input type="text" name="fax_number" value="" 
           style="position:absolute;left:-9999px;opacity:0;height:0;width:0;" 
           autocomplete="off" tabindex="-1">
    
    <!-- Turnstile -->
    <div class="cf-turnstile" data-sitekey="0x4AAAAAAB9AM8TVibxJ797V"></div>
    
    <button type="submit">Enviar</button>
</form>

<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<script>
document.getElementById('solidario-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = {
        name: formData.get('name'),
        email: formData.get('email'),
        entity_type: formData.get('entity_type'),
        entity_name: formData.get('entity_name'),
        message: formData.get('message') || '',
        fax_number: formData.get('fax_number') || '',
        turnstile_token: formData.get('cf-turnstile-response')
    };
    
    try {
        const response = await fetch('/wp-json/replanta/v1/contact/solidario', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': replantaContactNonce.nonce
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (response.ok && result.success) {
            alert('¡Gracias! Hemos recibido tu solicitud.');
            e.target.reset();
        } else {
            alert(result.message || 'Error al enviar');
        }
    } catch (error) {
        alert('Error de conexión');
    }
});
</script>
```

**Importante:** Añade el nonce en tu tema:
```php
wp_localize_script('tu-script', 'replantaContactNonce', [
    'nonce' => wp_create_nonce('wp_rest'),
    'sitekey' => CF_TURNSTILE_SITEKEY
]);
```

## Ejemplo: Formulario de Auditoría

```html
<form id="auditoria-form">
    <input type="text" name="name" required>
    <input type="email" name="email" required>
    <input type="url" name="url" required placeholder="https://tuweb.com">
    <textarea name="note"></textarea>
    
    <input type="text" name="fax_number" value="" style="position:absolute;left:-9999px;opacity:0;height:0;width:0;" autocomplete="off" tabindex="-1">
    
    <div class="cf-turnstile" data-sitekey="0x4AAAAAAB9AM8TVibxJ797V"></div>
    
    <button type="submit">Solicitar auditoría</button>
</form>

<script>
document.getElementById('auditoria-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = {
        name: formData.get('name'),
        email: formData.get('email'),
        url: formData.get('url'),
        note: formData.get('note') || '',
        fax_number: formData.get('fax_number') || '',
        turnstile_token: formData.get('cf-turnstile-response')
    };
    
    const response = await fetch('/wp-json/replanta/v1/contact/auditoria', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': replantaContactNonce.nonce
        },
        body: JSON.stringify(data)
    });
    
    const result = await response.json();
    
    if (response.ok && result.success) {
        alert('¡Gracias! Revisaremos tu sitio web.');
        e.target.reset();
    } else {
        alert(result.message || 'Error');
    }
});
</script>
```

## Integración con Elementor

**¡Automática!** No necesitas hacer nada.

Todos los formularios Elementor se capturarán automáticamente incluyendo:
- Contact Form (ff38150)
- Cualquier otro formulario que crees

Aparecerán en el listado con el tipo "Elementor Form" (badge rosa).

### Excluir formularios Elementor

Si NO quieres capturar algún formulario específico:

1. Ve a **Solicitudes → Ajustes**
2. Desactiva "Capturar formularios Elementor" globalmente, O
3. Añade el ID del formulario a la lista de exclusión (próximamente)

## Estructura de datos

### Custom Post Type
- **Post Type:** `rcm_contact`
- **Taxonomía:** `rcm_contact_type` (solidario, auditoria, contacto, elementor)

### Metadatos guardados
- `_rcm_name` - Nombre completo
- `_rcm_email` - Email (requerido)
- `_rcm_phone` - Teléfono
- `_rcm_message` - Mensaje original (readonly)
- `_rcm_notes` - Notas internas (editables)
- `_rcm_ip` - IP de origen
- `_rcm_country` - Código país (ES, MX, etc)
- `_rcm_user_agent` - Navegador
- `_rcm_source` - Fuente (REST API, Elementor)
- `_rcm_data` - Array con datos específicos del tipo:
  - **Solidario:** `entity_type`, `entity_name`
  - **Auditoría:** `url`
  - **Elementor:** `form_id`, `form_name`, `all_fields`

### Estados personalizados
- `rcm_pending` - Pendiente (rojo)
- `rcm_contacted` - Contactado (amarillo)
- `rcm_processed` - Procesado (azul)
- `rcm_converted` - Convertido (verde)
- `rcm_spam` - Spam (gris)

## Columnas en el admin

| Columna | Descripción |
|---------|-------------|
| **Tipo** | Badge con color según tipo (solidario/auditoría/contacto/elementor) |
| **Nombre** | Título del post (nombre del solicitante) |
| **Email** | Link mailto: |
| **Teléfono** | Número de contacto |
| **Estado** | Badge con color según estado |
| **País** | Código país (ES, MX, AR...) |
| **Fecha** | Fecha de recepción |

### Filtros disponibles
- Filtrar por **Tipo** (solidario, auditoría, contacto, elementor)
- Filtrar por **Estado** (pendiente, contactado, procesado, convertido, spam)

## REST API

### Headers requeridos
```
Content-Type: application/json
X-WP-Nonce: [nonce-de-wordpress]
```

### Campos comunes
| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `name` | string | Sí | Nombre completo |
| `email` | string | Sí | Email válido |
| `phone` | string | No | Teléfono |
| `message` | string | No | Mensaje del cliente |
| `fax_number` | string | No | Honeypot (debe estar vacío) |
| `turnstile_token` | string | Sí* | Token de Turnstile |

*Requerido solo si Turnstile está activo

### Endpoint Solidario
**POST** `/wp-json/replanta/v1/contact/solidario`

Campos adicionales:
- `entity_type` (string, requerido) - Tipo de entidad
- `entity_name` (string, requerido) - Nombre de la entidad

### Endpoint Auditoría
**POST** `/wp-json/replanta/v1/contact/auditoria`

Campos adicionales:
- `url` (string, requerido) - URL del sitio web

### Endpoint General
**POST** `/wp-json/replanta/v1/contact/general`

Solo campos comunes.

### Respuestas

**Éxito (200)**
```json
{
  "success": true,
  "message": "Solicitud recibida correctamente",
  "post_id": 123
}
```

**Error (400/403/429)**
```json
{
  "success": false,
  "message": "Descripción del error"
}
```

### Códigos de estado
- **200** - Solicitud creada
- **400** - Datos inválidos
- **403** - País bloqueado, honeypot, Turnstile fallido
- **429** - Rate limit excedido
- **500** - Error del servidor

## Ventajas vs múltiples plugins

### ❌ Antes (con plugins separados)
- `replanta-solidario` - Solo plan solidario
- `replanta-auditoria` - Solo auditorías
- Cada uno con su CPT, settings, code duplicado
- Difícil ver el panorama general
- Mantenimiento x3

### ✅ Ahora (un solo plugin)
- **Una sola tabla** con todas las solicitudes
- **Filtros visuales** por tipo y estado
- **Un solo código** de seguridad reutilizable
- **Captura Elementor** incluida automáticamente
- **Dashboard widget** con resumen
- **Escalable** - Añadir nuevos tipos es trivial

## FAQ

**¿Puedo seguir usando los plugins anteriores?**  
No es necesario. Este plugin los reemplaza completamente.

**¿Se pueden migrar solicitudes antiguas?**  
Sí, se puede crear un script de migración de los CPT antiguos al nuevo.

**¿Funciona sin Elementor?**  
Sí, los endpoints REST funcionan independientemente. La captura Elementor solo se activa si Elementor Pro está instalado.

**¿Puedo personalizar los tipos?**  
Sí, son términos de taxonomía. Puedes añadir más desde **Solicitudes → Tipos de solicitud**.

**¿Los emails se siguen enviando?**  
Sí, cada endpoint envía notificación al email configurado en Ajustes.

## Changelog

### 1.0.0 (2025-01-05)
- Release inicial
- CPT unificado con taxonomía de tipos
- 3 REST endpoints (solidario, auditoría, general)
- Captura automática de Elementor Forms
- Admin UI con filtros y columnas personalizadas
- 5 estados personalizados
- Dashboard widget con stats
- Seguridad: Turnstile, geo-blocking, rate limit, honeypot
- Meta boxes dinámicas según tipo

---

**Autor:** Replanta Team  
**Versión:** 1.0.0  
**Requiere:** WordPress 5.8+  
**Compatible:** PHP 7.4+  
**Licencia:** GPLv2 or later
