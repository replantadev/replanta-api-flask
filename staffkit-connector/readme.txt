=== StaffKit Connector ===
Contributors: replanta
Tags: crm, leads, automation, sustainability, forms
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Conecta WordPress con StaffKit para capturar y gestionar leads automáticamente.

== Description ==

StaffKit Connector integra tu sitio WordPress con StaffKit, permitiéndote:

* ✅ Capturar leads automáticamente desde Contact Form 7, Gravity Forms y WPForms
* ✅ Envío automático al webhook de StaffKit
* ✅ Tracking completo: fuente, URL, IP, user agent
* ✅ Compatible con auditorías eco-performance
* ✅ API REST completa para integraciones personalizadas

= Características =

**Captura Automática de Leads**
Todos los envíos de formularios se sincronizan automáticamente con StaffKit.

**Webhooks**
Envía datos en tiempo real al webhook de captura de StaffKit.

**Tracking Completo**
- Fuente del lead (formulario, página)
- URL de origen
- IP del visitante
- User agent
- Datos eco-performance (opcional)

**Compatible con**
* Contact Form 7
* Gravity Forms
* WPForms
* Formularios personalizados (vía API)
* Plugin eco-performance audit

== Installation ==

1. Sube la carpeta `staffkit-connector` a `/wp-content/plugins/`
2. Activa el plugin desde el menú 'Plugins' en WordPress
3. Ve a Ajustes → StaffKit para configurar
4. Configura:
   - URL: https://staff.replanta.dev
   - API Key: sk_live_replanta_2026_webhook_secure_key
5. Activa "Auto-sincronizar"
6. ¡Listo! Los leads se capturarán automáticamente

== Configuration ==

**URL de StaffKit**: https://staff.replanta.dev
**API Key**: Obtén tu API Key desde StaffKit → Integraciones

El plugin enviará los leads a:
`https://staff.replanta.dev/api/webhooks/lead-capture.php`

== Frequently Asked Questions ==

= ¿Necesito una cuenta de StaffKit? =

Sí, necesitas tener StaffKit instalado y una API Key generada.

= ¿Funciona con otros formularios? =

Por defecto soporta Contact Form 7, Gravity Forms y WPForms. Para otros formularios, usa la función `staffkit_send_lead()`.

= ¿Cómo envío leads manualmente? =

```php
staffkit_send_lead([
    'email' => 'lead@example.com',
    'name' => 'John Doe',
    'website' => 'example.com',
    'source' => 'Custom Form',
    'eco_score' => 'A',
    'co2_visit' => 1.5
]);
```

= ¿Es seguro? =

Sí, toda la comunicación usa HTTPS y autenticación mediante API Key.

== Changelog ==

= 1.1.0 =
* Actualizado para usar webhook de captura
* Normalización de nombres de campos (name, company, website)
* Tracking mejorado: source_url, IP, user agent
* Soporte para datos eco-performance
* get_client_ip() para IPs detrás de proxies/CDN

= 1.0.0 =
* Lanzamiento inicial
* Integración con Contact Form 7, Gravity Forms, WPForms
* Sistema de webhooks
* Widget de sostenibilidad
