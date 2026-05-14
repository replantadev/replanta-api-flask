# Módulo Awin Tracking — Replanta Prices

## Resumen

Este módulo implementa tracking de afiliados Awin integrado en el plugin `replanta-prices`. Captura el parámetro `awc` de las URLs de entrada y lo añade automáticamente a los enlaces de compra hacia Upmind (`clientes.replanta.net`).

## Arquitectura

```
includes/awin/
├── class-awin-manager.php     # Controlador principal
├── class-awin-cookie.php      # Captura y gestión de AWC
├── class-awin-url-helper.php  # Modificación de URLs
├── class-awin-logger.php      # Logging en base de datos
├── class-awin-webhook.php     # Endpoint REST para Upmind
└── class-awin-admin.php       # Dashboard de administración

assets/
├── css/awin-admin.css         # Estilos del dashboard
└── js/awin-fallback.js        # Fallback JS para links dinámicos
```

## Flujo de Funcionamiento

1. **Captura AWC**: Cuando un visitante llega con `?awc=XXXXX`, se guarda en cookie first-party (90 días por defecto).

2. **Modificación URLs**: Todas las llamadas a `Replanta_Prices_Geo::get_order_url($pid)` incluyen automáticamente el AWC en la URL de Upmind.

3. **Webhook Upmind**: Cuando ocurre una venta, Upmind envía webhook a `/wp-json/replanta/v1/upmind-webhook` con datos de la transacción.

4. **Logging**: Todos los eventos (capturas, clics, webhooks) se registran en `wp_replanta_awin_events`.

## Instalación

### Paso 1: Activar el Plugin

El módulo se activa automáticamente al actualizar o desactivar/activar el plugin. Esto crea la tabla de base de datos.

### Paso 2: Configurar en WordPress

1. Ve a **Ajustes → Awin Tracking**
2. Activa "Activar Tracking Awin"
3. Revisa/genera el Webhook Secret
4. Guarda los cambios

### Paso 3: Configurar Webhook en Upmind

En tu panel de Upmind:

1. Ve a **Settings → Webhooks**
2. Añade un nuevo webhook:
   - **URL**: `https://replanta.net/wp-json/replanta/v1/upmind-webhook`
   - **Events**: 
     - `invoice.payment.captured`
     - `transaction.captured`
     - `order.completed`
   - **Secret** (si lo soporta): Copia el secret generado en WordPress

### Paso 4: MasterTag de Awin

Añade el MasterTag de Awin en tu sitio (normalmente via GTM):

```html
<script 
  src="https://www.dwin1.com/XXXXXXX.js" 
  type="text/javascript" 
  defer="defer">
</script>
```

_(Reemplaza XXXXXXX con tu ID de anunciante Awin)_

## Configuración Detallada

| Opción | Defecto | Descripción |
|--------|---------|-------------|
| Activar Tracking | Off | Habilita/deshabilita todo el módulo |
| Nombre Cookie | `replanta_awin_awc` | Nombre de la cookie AWC |
| Duración Cookie | 90 días | TTL de la cookie |
| Dominio Objetivo | `clientes.replanta.net` | Dominio donde añadir AWC |
| Webhook Secret | (generado) | Secret para validar webhooks |
| JS Fallback | On | Modifica links via JavaScript |
| Logs Detallados | Off | Registra modificaciones de URL |
| Retención Logs | 90 días | Auto-limpieza de eventos antiguos |

## API de Desarrollo

### Obtener AWC actual

```php
$awc = Replanta_Awin_Manager::get_awc();
```

### Construir URL con AWC

```php
$url = Replanta_Awin_Manager::build_order_url( $pid, 'EUR' );
```

### Añadir AWC a cualquier URL

```php
$url = Replanta_Awin_Manager::append_awc( $url );
```

### Registrar evento personalizado

```php
Replanta_Awin_Manager::log( 'custom_event', [
    'reference' => 'ORDER-123',
    'amount'    => 49.99,
    'currency'  => 'EUR'
], 'success' );
```

## Verificación

### Test 1: Captura de AWC

1. Visita `https://replanta.net/?awc=TEST_123456`
2. Ve a **Ajustes → Awin Tracking → Herramientas**
3. Verifica que "AWC actual en tu sesión" muestra `TEST_123456`

### Test 2: Modificación de URL

1. Con AWC activo, ve a una página con grid de precios
2. Inspecciona un botón de compra
3. La URL debe incluir `&awc=TEST_123456`

### Test 3: Webhook

1. Ve a **Herramientas → Probar Webhook**
2. Click "Enviar Test"
3. Verifica respuesta OK
4. Revisa tabla de Eventos

## Fase 2 (Futuro)

- [ ] Llamadas S2S a Awin API para confirmar conversiones
- [ ] Reintentos automáticos si falla S2S
- [ ] Dashboard con gráficos de conversiones
- [ ] Validación de firma HMAC del webhook
- [ ] Integración con página de "Gracias" post-compra

## Troubleshooting

### AWC no se captura
- Verifica que el tracking está **activado**
- Comprueba que la cookie no está bloqueada por consentimiento

### URLs no incluyen AWC
- Verifica que hay AWC en cookie
- Comprueba que `class_exists('Replanta_Awin_URL_Helper')` devuelve true
- Activa "Log Detallados" y revisa eventos

### Webhook no recibe datos
- Verifica URL correcta en Upmind
- Comprueba que el endpoint responde: `GET /wp-json/replanta/v1/upmind-webhook/test`
- Revisa errores en tabla de Eventos

## Changelog

### 1.0.0 (2024-01-XX)
- Implementación inicial
- Captura AWC en cookie first-party
- Modificación automática de URLs de compra
- Endpoint webhook para Upmind
- Panel de administración con estadísticas
- JS fallback para links dinámicos
