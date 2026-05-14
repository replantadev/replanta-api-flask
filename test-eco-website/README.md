# Eco-Performance Snapshot

Plugin de WordPress que genera auditorías rápidas de eco-performance combinando datos de PageSpeed Insights, Website Carbon, Green Web Foundation y un Eco Snapshot Score propio. Pensado para equipos que necesitan producir informes accionables en menos de 90 minutos.

## Características clave

- **Shortcode `[eco_performance_snapshot]`** con formulario elegante que recoge una URL y entrega un informe completo.
- **Resumen ejecutivo automático**: calificación global (A–E), 3 hallazgos y 5 acciones priorizadas según impacto/esfuerzo.
- **Métricas objetivas** con capturas: Lighthouse (móvil/escritorio), Eco Snapshot Score, Website Carbon y verificación de hosting verde.
- **Cache inteligente** configurable (1–168 h) para evitar límites de API.
- **Modo sandbox** con datos simulados para demos o entornos sin claves.
- **Panel de ajustes ultra-cuidado** con Material/Bootstrap style, pruebas de conexión y toasts.

## Requisitos

- WordPress 6.0 o superior.
- PHP 7.4 o superior.
- Claves/API tokens según el servicio:
  - PageSpeed Insights (Google Cloud, necesaria).
  - Website Carbon y Green Web Foundation se consultan sin credenciales; ambos alimentan el Eco Snapshot Score.

## Instalación

1. Copia el directorio `test-eco-website` dentro de `wp-content/plugins/`.
2. Activa **Eco-Performance Snapshot** desde el panel de plugins.
3. Visita **Eco Snapshot → Credenciales** en el escritorio para introducir la clave de PageSpeed.
4. Inserta el shortcode `[eco_performance_snapshot]` en la página donde quieras ofrecer el informe.

## Uso del panel de ajustes

- **Credenciales**: guarda la API key de PageSpeed (obligatoria). Ajusta la caducidad del caché.
- **Modo sandbox**: activa para trabajar con datos ficticios (ideal para demos).
- **Activar registro**: volcado de incidencias en el log (`WP_DEBUG_LOG`).
- **Probar conexiones**: botón por servicio que valida las credenciales vía REST.

## Flujo del shortcode

1. El usuario introduce la URL a auditar.
2. Se llama a `tew/v1/audit` (requiere nonce REST). Se usa caché si aplica.
3. El runner ejecuta las integraciones externas (PageSpeed, Website Carbon, Green Web), calcula el Eco Snapshot Score y devuelve JSON.
4. El frontend crea tarjetas con métricas, capturas, hallazgos y acciones.
5. Si alguna API falla se muestra aviso, pero el informe continúa con el resto.

## Personalización

- Filtra `tew_cache_key_prefix` (ver `Cache::build_key`) para cambiar el almacenamiento.
- Extiende los clientes en `includes/api/` o engancha a filtros futuros (próxima versión) para adaptar los endpoints.
- Añade estilos adicionales en tu tema; los assets del plugin usan prefijos `.tew-`.

## Integración con StaffKit Connector

El plugin detecta automáticamente si **StaffKit Connector** está instalado y activo. Cuando un usuario completa una auditoría y proporciona su email, se envía automáticamente como lead a StaffKit con los siguientes datos:

- **Email del usuario**
- **Dominio auditado**
- **Score eco-performance** (A, B, C, D, E)
- **CO2 por visita**
- **Datos completos de la auditoría**: scores móvil/escritorio, eco snapshot score, peso de página, hosting verde

Esta integración permite:
- Captura automática de leads cualificados (empresas interesadas en sostenibilidad)
- Segmentación por score para campañas personalizadas
- Tracking completo desde la auditoría hasta la conversión

**Requisitos**:
1. Plugin **StaffKit Connector v1.1.0+** instalado y activo
2. Configuración en Ajustes → StaffKit:
   - URL: `https://staff.replanta.dev`
   - API Key: `sk_live_replanta_2026_webhook_secure_key`
   - Auto-sincronizar: Activado

**Nota**: Si StaffKit Connector no está instalado, el plugin funciona normalmente sin enviar leads.

## Desarrollo

- Código organizado por dominios: `admin/`, `api/`, `reporting/`, `rest/`.
- Autoload PSR-4 ligero vía `includes/class-tew-autoloader.php`.
- Ejecuta `php -l` sobre los archivos modificados antes de desplegar.
- Próxima iteración: integrar PDF export, scheduler y filtros para insights personalizados.

## Notas sobre límites de API

- PageSpeed Insights: respeta `queries per second` de Google. Ajusta la caducidad del caché para evitar bloqueos.
- Eco Snapshot Score: se calcula localmente combinando PageSpeed, Website Carbon y Green Web; no añade límites adicionales.
- Website Carbon: funciona con endpoint público `/data`; requiere que podamos calcular el peso en bytes y el estado de hosting verde.

## Soporte y evolución

- `CHANGELOG.md` documenta las versiones.
- Para bugs o mejoras, abre issue en el repositorio interno o contacta con el equipo Replanta Dev.
