# Changelog - Plugin Dominios Reseller

## [1.7.3] - 2026-06-22
### Production readiness for Hub operations
- Fixed Cloudflare onboarding final states: domains whose real NS do not point to Cloudflare remain in `pending_ns` instead of being marked as `onboarded`.
- Registered the custom WP-Cron interval before scheduling the queue worker, avoiding silent schedule failures on first load.
- Allowed retry/requeue from recoverable states (`partial`, `needs_manual_ns`, `pending_ns`) and blocked duplicate runs for active states.
- Made onboarding logs tolerate system-level entries without a run id.
- Updated Cloudflare User-Agent versioning and hardened zone sync update formats.

## [1.6.44] - 2025-12-21
### 🚀 **HTTP Detection Avanzada para Modal PHP**

#### 🔧 **Mejora del Fallback de Detección PHP**
- **IMPROVED**: `get_php_fallback_info()` ahora usa 3 métodos de detección
- **MÉTODO 1**: Plugin Endpoint - datos 100% precisos si DR está instalado en destino
- **MÉTODO 2**: HTTP Headers - detecta X-Powered-By, Server, Cloudflare, LiteSpeed Cache
- **MÉTODO 3**: Estimación inteligente basada en tipo de servidor

#### 📊 **Información Real Detectada**
- **ADDED**: Detección de LiteSpeed Web Server
- **ADDED**: Detección de LiteSpeed Cache activo
- **ADDED**: Detección de Cloudflare
- **ADDED**: Campo `detection_method` para indicar origen de datos
- **ADDED**: Campo `detected_info` con detalles de detección
- **ADDED**: Campo `server_type` (litespeed/apache/nginx/unknown)
- **ADDED**: Campo `source` (Real Data vs HTTP Detection)

#### ✨ **Extensiones y Settings Mejorados**
- **IMPROVED**: Lista de 47 extensiones típicas de CloudLinux hosting
- **IMPROVED**: INI settings realistas (512M memory, 128M upload, etc.)
- **ADDED**: Recomendaciones contextuales según versión y servidor
- **ADDED**: Indicador de datos reales vs estimados

---

## [1.6.43] - 2025-12-21
### 🔌 **WHM API v1.6.43 - Conexiones UK y USA Funcionando**

Este changelog corresponde a múltiples iteraciones de depuración...

---

## [1.6.13] - 2025-12-21
### 🔧 **Corrección Definitiva de Ruta de Script Admin.js**

#### 🐛 **Error 404 persistente en admin.js**
- **FIXED**: Cambio de `DOMINIOS_RESELLER_PLUGIN_URL` a `plugins_url()` para construcción de URL
- **FIXED**: Uso de `plugins_url()` que es la función recomendada por WordPress
- **FIXED**: Ruta ahora se construye de forma confiable en tiempo de enqueue
- **IMPROVED**: Eliminación del error 404 de una vez por todas

#### 🎯 **Enqueue más robusto**
- **ADDED**: Construcción dinámica de URL usando `dirname(__DIR__) . '/dominios-reseller.php'`
- **IMPROVED**: Cumple con estándares de WordPress para carga de scripts
- **IMPROVED**: Compatible con symlinks y diferentes configuraciones de servidor

## [1.6.12] - 2025-12-21
### 🔧 **Corrección de Detección de Página para Enqueue de Scripts**

#### 🐛 **Admin.js no se cargaba en página de onboarding**
- **FIXED**: Cambio de verificación de hook a detección basada en parámetro GET
- **FIXED**: Ahora usa `$_GET['page'] === 'dominios-reseller-onboarding'` en lugar de nombre del hook
- **IMPROVED**: Más confiable que depender del nombre exacto del WordPress hook suffix
- **IMPROVED**: Script admin.js ahora se carga correctamente en todas las condiciones

#### 🎯 **Modal PHP Completamente Funcional**
- **FIXED**: Botón de engranaje ⚙ ahora abre el modal sin errores 404
- **FIXED**: Event handlers registrados correctamente en el documento
- **IMPROVED**: JavaScript completamente operativo para interacciones del modal

## [1.6.11] - 2025-12-21
### 🔧 **Corrección de Carga de Textdomain**

#### 🐛 **Translation Loading Too Early**
- **FIXED**: Movido `load_plugin_textdomain` del hook `plugins_loaded` al hook `init`
- **FIXED**: Eliminación del error "Function _load_textdomain_just_in_time was called incorrectly"
- **IMPROVED**: Carga de traducciones en el momento correcto del ciclo de WordPress

#### 📋 **Compatibilidad Mejorada**
- **ADDED**: Hook `init` para carga de textdomain
- **IMPROVED**: Evita conflictos con otros plugins que cargan traducciones temprano

## [1.6.10] - 2025-12-21
### 🔧 **Corrección Crítica de Carga de Scripts**

#### 🐛 **Script admin.js no se cargaba por timing del hook**
- **FIXED**: Movido enqueue de `admin.js` al hook `admin_enqueue_scripts` en lugar de `render_page`
- **FIXED**: Scripts ahora se cargan en el momento correcto del ciclo de WordPress
- **FIXED**: Eliminación definitiva del error 404 en carga de `admin.js`
- **IMPROVED**: Separación de enqueue de scripts y scripts inline para mejor organización

#### 📦 **Reorganización de Scripts**
- **ADDED**: Método `enqueue_scripts()` que verifica la página actual antes de cargar
- **ADDED**: Método `render_inline_scripts()` para lógica específica de la página
- **IMPROVED**: Carga condicional solo en página de onboarding para optimización
- **IMPROVED**: Mejor separación de responsabilidades entre enqueue y lógica

#### 🎯 **Funcionalidad del Modal PHP Restaurada**
- **FIXED**: Modal PHP ahora se abre correctamente con el botón ⚙
- **FIXED**: Event handlers registrados en el momento adecuado
- **FIXED**: JavaScript completamente funcional en página de CF Onboarding

## [1.6.9] - 2025-12-21
### 📊 **Logging de Configuración CF Antes de Aplicar Preset**

#### 🔍 **Verificación de Estado Actual**
- **ADDED**: Obtención de configuración actual de zona CF antes de aplicar preset
- **ADDED**: Logging detallado de settings actuales en BD de onboarding
- **IMPROVED**: Trazabilidad completa del estado de zona antes de modificaciones
- **IMPROVED**: Debugging mejorado para comparar configuraciones existentes

#### 📋 **Registro de Cambios**
- **ADDED**: Log de configuración actual guardado en `run_data`
- **ADDED**: Mensajes de warning si no se puede obtener configuración actual
- **IMPROVED**: Historial completo de estado de zona para troubleshooting

## [1.6.8] - 2025-12-21
### 🛡️ **Prevención de Onboarding Duplicado en Dominios CF**

#### 🚫 **Validación de Estado CF Antes de Onboarding**
- **ADDED**: Verificación en `ajax_enqueue()` si el dominio ya está en Cloudflare
- **ADDED**: Prevención de re-onboarding si el dominio ya está completamente configurado
- **ADDED**: Mensaje de error claro cuando se intenta procesar un dominio ya onboarded
- **IMPROVED**: Flujo robusto que evita enviar dominios ya en CF a proceso innecesario

#### 🔧 **Corrección de Ruta de Assets**
- **FIXED**: Definición de constante `DOMINIOS_RESELLER_PLUGIN_URL` para rutas consistentes
- **FIXED**: Uso de `DOMINIOS_RESELLER_PLUGIN_URL` en lugar de `plugin_dir_url()` relativo
- **FIXED**: Eliminación del error 404 en carga de `admin.js` en todas las páginas

#### 📊 **Mejora en Lógica de Estados**
- **IMPROVED**: Verificación de estado de onboarding antes de permitir acciones
- **IMPROVED**: Mensajes de error más descriptivos para estados inválidos
- **IMPROVED**: Prevención de operaciones duplicadas en dominios ya procesados

## [1.6.7] - 2025-12-21
### 🔧 **Corrección de Ruta de Script**

#### 🐛 **Error 404 en carga de admin.js**
- **FIXED**: Ruta incorrecta para cargar `admin.js` en página de onboarding
- **FIXED**: Cambiado `plugin_dir_url(dirname(__FILE__))` por `plugin_dir_url(dirname(dirname(__FILE__)))`
- **FIXED**: Ahora apunta correctamente al directorio raíz del plugin desde `includes/`

#### 📁 **Ruta Corregida**
- **Antes**: `includes/assets/js/admin.js` (inexistente)
- **Ahora**: `assets/js/admin.js` (correcta)

## [1.6.6] - 2025-12-21
### 🔧 **Carga de Scripts en Página de Onboarding**

#### 🐛 **Script admin.js no se cargaba en onboarding**
- **FIXED**: La página de onboarding ahora carga correctamente `admin.js`
- **FIXED**: Modal PHP ahora funciona en la página de CF Onboarding
- **ADDED**: Enqueue del script admin.js en `render_scripts()` de onboarding
- **ADDED**: Localización correcta del script con nonces para AJAX

#### 🎯 **Funcionalidad del Modal PHP Restaurada**
- **FIXED**: Botón ⚙ ahora abre el modal de configuración PHP
- **FIXED**: Todas las interacciones del modal (tabs, botones, cierre) funcionan
- **FIXED**: Event handlers registrados correctamente

## [1.6.5] - 2025-12-21
### 🔍 **Debugging Exhaustivo del Modal PHP**

#### 🐛 **Logging Detallado Agregado**
- **ADDED**: Console logging en apertura del modal PHP
- **ADDED**: Logging en inicialización del modal
- **ADDED**: Logging en clicks de tabs
- **ADDED**: Logging en cierre del modal
- **ADDED**: Verificación de datos PHP antes de mostrar modal
- **IMPROVED**: Trazabilidad completa del flujo del modal

#### 🔧 **Verificación de Funcionalidad**
- **ADDED**: Verificación de que `initializePHPModal()` se ejecuta
- **ADDED**: Verificación de que event handlers se registran
- **ADDED**: Logging de elementos DOM creados
- **IMPROVED**: Debugging paso a paso del modal PHP

## [1.6.4] - 2025-12-21
### ⚙️ **Nuevo Botón de Configuración PHP**

#### ✨ **Botón de Engranaje Dedicado**
- **ADDED**: Botón ⚙ debajo del botón de play (▶) en cada card
- **ADDED**: Event handler unificado para badges PHP y botón de config
- **ADDED**: CSS styling para el botón de configuración PHP
- **IMPROVED**: Mejor accesibilidad - botón siempre visible y clicable
- **FIXED**: Eliminación de dependencia de badges PHP para acceder al modal

#### 🎯 **Flujo de Usuario Mejorado**
- **CHANGED**: Acceso al modal PHP ahora desde botón dedicado en lugar de badges
- **IMPROVED**: Interfaz más intuitiva y confiable
- **ADDED**: Console logging mejorado para debugging

## [1.6.3] - 2025-12-21
### 🔧 **Compatibilidad Mejorada - Sin Emojis**

#### 🎯 **Reemplazo de Emojis por Caracteres ASCII**
- **CHANGED**: PHP pilots ahora usan caracteres ASCII simples (✓, ✗, !) en lugar de emojis Unicode
- **IMPROVED**: Mejor compatibilidad cross-browser y cross-platform
- **FIXED**: Eliminación de posibles problemas de renderizado con emojis

## [1.6.2] - 2025-12-21
### 🎨 **Mejoras Visuales en PHP Pilots**

#### ✨ **Badges PHP Clicables y Visuales**
- **FIXED**: PHP pilots ahora muestran iconos visuales (✅⚠️❌) en lugar de texto plano
- **FIXED**: Badges cambian de "PHP" a "PHP✅", "PHP⚠️", "PHP❌" según estado
- **FIXED**: Mejor feedback visual cuando los badges tienen datos cargados
- **ADDED**: Logging de debug para clicks en PHP pilots
- **IMPROVED**: Estados visuales más claros para identificar badges clicables

#### 🔍 **Debugging Mejorado**
- **ADDED**: Console logging cuando se hace clic en PHP pilots
- **ADDED**: Mejor trazabilidad de eventos de click en badges

## [1.6.1] - 2025-12-21
### 🐛 **Correcciones Críticas de AJAX y PHP Pilots**

#### 🔧 **Corrección de Nonces AJAX**
- **FIXED**: Error 403 Forbidden en llamadas AJAX por nonces inconsistentes
- **FIXED**: Unificación de todos los nonces de onboarding a 'dr_onboarding_nonce'
- **FIXED**: Activación de localización de scripts para dominios_reseller_ajax
- **FIXED**: Corrección de nonce en scripts.php para compatibilidad

#### ⚡ **Sistema PHP Pilots Funcional**
- **FIXED**: PHP pilots ahora muestran información correcta en lugar de "error obteniendo info php"
- **FIXED**: Llamadas AJAX para obtener información PHP por lotes funcionando
- **FIXED**: Modal de configuración PHP accesible y funcional
- **FIXED**: Indicadores visuales de estado PHP actualizándose correctamente

#### 🔄 **Botón de Refresh en Modal PHP**
- **FIXED**: Botón de actualizar información PHP funcionando sin errores
- **FIXED**: Logging mejorado para debugging de llamadas AJAX
- **FIXED**: Mensajes de estado más descriptivos durante la carga

## [1.6.0] - 2025-12-21
### 🔥 **Nuevas Features - Integración Completa con Upmind**

#### 🚀 **Onboarding Automático como "Optimización de Bienvenida"**
- **ADDED**: Integración completa con Upmind para detección automática de nuevas órdenes de hosting
- **ADDED**: Webhook receiver para notificaciones instantáneas de Upmind
- **ADDED**: Trigger automático de onboarding en Cloudflare al recibir órdenes
- **ADDED**: Sistema de "welcome optimization" que configura dominios sin intervención manual

#### 🏠 **Integración con WHM/cPanel para WP Readiness**
- **ADDED**: Verificación automática de preparación para WordPress en cPanel
- **ADDED**: Checks de PHP version, extensiones requeridas y límites de configuración
- **ADDED**: Indicador visual "WP Ready" en la lista de dominios
- **ADDED**: Meta box detallado con estado completo de compatibilidad WordPress
- **ADDED**: Sistema de corrección automática de configuraciones PHP faltantes

#### 📊 **Dashboard de Monitoreo en Tiempo Real**
- **ADDED**: Página dedicada de monitoreo con KPIs en vivo
- **ADDED**: Gráfico de actividad de onboarding 24/7
- **ADDED**: Sistema de alertas automáticas para problemas
- **ADDED**: Controles administrativos del sistema de onboarding
- **ADDED**: Estado visual de todas las APIs conectadas

#### 🔍 **Auto-Discovery Avanzado**
- **ADDED**: Sistema de polling continuo de APIs de Upmind
- **ADDED**: Escaneo inteligente de emails IMAP en busca de dominios
- **ADDED**: Detección automática desde Openprovider
- **ADDED**: Filtro de duplicados y validación de dominios
- **ADDED**: Triggers automáticos basados en múltiples fuentes

#### ⚙️ **Panel de Configuración de Integraciones**
- **ADDED**: Página dedicada para configurar todas las integraciones
- **ADDED**: Tests de conectividad para Upmind, WHM y Cloudflare APIs
- **ADDED**: Configuración centralizada de webhooks y credenciales
- **ADDED**: Guía visual de URLs de webhook para configurar en Upmind

#### 🐛 **Correcciones de Errores**
- **FIXED**: Error de sintaxis en class-debug-hub.php (línea 876)
- **FIXED**: Código de inicialización fuera de la clase movido correctamente
- **FIXED**: Todos los handlers AJAX movidos dentro de sus respectivas clases

## [1.5.7] - 2025-12-20

### 🔧 Sistema de Onboarding Mejorado - Compatibilidad Cloudflare API v4

#### 🆕 Nuevas Funcionalidades
- **ADDED**: Diagnóstico completo de Openprovider en Debug Hub
- **ADDED**: Validación previa de nameservers antes de actualizar
- **ADDED**: Manejo robusto de errores específicos para dominios .es
- **ADDED**: Logging detallado para errores de NS rechazados
- **ADDED**: Estado "parcial" para onboarding cuando NS requieren configuración manual

#### 🔄 Mejoras en Preset de Cloudflare
- **FIXED**: Eliminados settings obsoletos de Cloudflare (0rtt, polish, mirage, challenge_ttl, etc.)
- **UPDATED**: Preset compatible con Cloudflare API v4 actual
- **IMPROVED**: Validación de settings antes de aplicar

#### � Sistema de Autenticación Cloudflare
- **CHANGED**: Prioridad cambiada - API Token primero, Global API Key como fallback
- **IMPROVED**: Interfaz de administración clarificada con prioridades
- **UPDATED**: Logging de autenticación para mostrar método usado
- **FIXED**: Sistema ahora usa Bearer Token (API Token) como método principal

#### �🛠️ Sistema de Diagnóstico Openprovider
- **ADDED**: Test "🔍 Diagnóstico Openprovider" en Debug Hub
- **ADDED**: Verificación detallada de estado de dominio
- **ADDED**: Instrucciones específicas para dominios .es
- **ADDED**: Detección de restricciones de registro español

#### �️ Sistema de Logs Centralizado
- **ADDED**: Modal de logs mejorado con información detallada
- **ADDED**: Visualización de datos JSON adicionales en logs
- **ADDED**: Estilos mejorados para diferentes niveles de log (info, warning, error)
- **ADDED**: Historial completo de acciones de sincronización y onboarding

#### 🔄 Actualización Automática de Presets
- **ADDED**: Método `update_existing_presets()` para actualizar presets en DB
- **ADDED**: Detección automática de versiones desactualizadas
- **ADDED**: Actualización forzada en activación del plugin
- **ADDED**: Test "Actualizar Presets" en Debug Hub

#### 🎨 Interfaz Mejorada
- **IMPROVED**: Modal de logs con estructura jerárquica
- **IMPROVED**: Visualización clara de timestamps, steps y niveles
- **IMPROVED**: Formato JSON legible para datos técnicos
- **IMPROVED**: Colores distintivos por tipo de log

#### 📋 Documentación
- **ADDED**: `SOLUCION-NS-ES.md` - Guía completa para problemas de NS en .es
- **UPDATED**: Instrucciones de configuración manual de NS
- **ADDED**: Contactos de soporte para autorización de NS

---

## [1.5.0] - 2025-12-19

### 🔧 Configuración Dinámica de Servidores WHM
Eliminación de IPs hardcodeadas - ahora los servidores son completamente configurables desde el panel de administración.

#### 🆕 Nuevas Funcionalidades
- **ADDED**: Campo "IP/Hostname Servidor" para servidor UK en la pestaña de configuración
- **ADDED**: Campo "IP/Hostname Servidor" para servidor USA en la pestaña de configuración
- **ADDED**: Función helper `dominios_reseller_get_server_ip()` para obtener la IP configurada
- **ADDED**: Validación de IP vacía antes de realizar conexiones WHM
- **ADDED**: Mensajes de error claros cuando la IP no está configurada

#### 🔄 Cambios
- **CHANGED**: `test_whm_connection()` ahora usa IPs dinámicas de las opciones
- **CHANGED**: `obtener_cuentas_whm()` ahora usa IPs dinámicas
- **CHANGED**: `obtener_addons_de_usuario()` ahora usa IPs dinámicas
- **CHANGED**: `obtener_trafico_real()` ahora usa IPs dinámicas
- **CHANGED**: `mostrar_servidor_dominios()` ya no requiere parámetro IP
- **REMOVED**: IPs hardcodeadas (198.38.92.238, 192.250.227.159)

#### 🗄️ Nuevas Opciones
- `uk_server_ip` - IP o hostname del servidor WHM de UK
- `usa_server_ip` - IP o hostname del servidor WHM de USA

---

## [1.4.0] - 2025-12-16

### ☁️ FASE 2: Cloudflare Onboarding Automatizado
Automatización completa del proceso de alta de dominios en Cloudflare con aplicación de presets y actualización de nameservers.

#### 🆕 Nuevas Funcionalidades
- **ADDED**: Sistema de cola asíncrona para onboarding (no bloquea admin)
- **ADDED**: Creación automática de zonas en Cloudflare
- **ADDED**: Presets configurables (WordPress, WooCommerce) con settings óptimos
- **ADDED**: Integración con Openprovider para actualización automática de NS
- **ADDED**: Panel de administración "CF Onboarding" con cards por dominio
- **ADDED**: Logs detallados de cada paso del proceso
- **ADDED**: Soporte para dominios con TLDs compuestos (co.uk, com.es, etc.)

#### 🔄 Workflow de Onboarding
1. **ensure_zone**: Crea zona en CF si no existe (idempotente)
2. **apply_preset**: Aplica configuración de seguridad, SSL, cache
3. **update_ns**: Actualiza nameservers en Openprovider (opcional)

#### 📁 Nuevos Archivos
- `includes/class-onboarding-db.php` - Esquema de DB y gestión de presets
- `includes/class-onboarding-worker.php` - Worker de cola con locking
- `includes/class-openprovider-service.php` - Integración API Openprovider
- `includes/class-onboarding-admin.php` - UI de administración

#### 🗄️ Nuevas Tablas
- `{prefix}_dominios_reseller_cf_onboarding` - Estado por dominio
- `{prefix}_dominios_reseller_cf_onboarding_logs` - Logs de ejecución
- `{prefix}_dominios_reseller_cf_presets` - Presets almacenados

#### ⚙️ Presets Incluidos
- **wp**: WordPress optimizado (SSL Full, TLS 1.2, WAF, cache bypass admin)
- **woo**: WooCommerce optimizado (WP + bypass checkout/cart/account)

#### 🔐 API Token Cloudflare - Permisos Requeridos
Para Fase 2, el token necesita:
- `Zone:Read` - Listar y leer zonas
- `Zone:Edit` - Crear zonas y modificar settings
- `Zone Settings:Edit` - Modificar configuración SSL, TLS, etc.
- `Cache Rules:Edit` - Crear reglas de cache (Rulesets API)

#### 🔄 Estrategia de Idempotencia
- Antes de crear zona: verificar si ya existe por nombre
- Antes de aplicar preset: comparar valores actuales vs deseados
- Antes de actualizar NS: verificar si ya están configurados
- Safe to run multiple times without side effects

#### ⏱️ Manejo de Rate Limits
- Cloudflare: 1200 req/5min → Backoff exponencial si 429
- Openprovider: 60 req/min → Transient-based throttling
- Token de OP cacheado 23h (expira a las 24h)

---

## [1.3.0] - 2025-12-16

### ☁️ Integración Cloudflare
- **ADDED**: Nueva tabla `{prefix}_dominios_reseller_cf_zones` para almacenar zonas de Cloudflare
- **ADDED**: Clase `Dominios_Reseller_Cloudflare_Service` para gestionar sincronización con CF API
- **ADDED**: Panel de configuración de Cloudflare en pestaña Settings
- **ADDED**: Columna "CF" en el listado de dominios mostrando ✅/❌/❓
- **ADDED**: Sincronización manual con botón y automática via WP-Cron (cada 8h)
- **ADDED**: Matching inteligente: exacto (`example.com`) y subdominio (`tienda.example.com`)
- **ADDED**: El matching usa `primary_domain` (no `domain`) para evitar falsos positivos

### 🚀 Optimizaciones de Rendimiento
- **ADDED**: Cache de índice de zonas en transient (1 hora)
- **ADDED**: Batch matching para listados (1 query a DB, 0 a CF API)
- **ADDED**: Upsert incremental con `INSERT ... ON DUPLICATE KEY UPDATE`
- **ADDED**: Rate limit backoff automático si CF devuelve 429
- **ADDED**: Zonas eliminadas se marcan con `deleted_at` (soft delete)

### 🔐 Seguridad
- **ADDED**: Token de API enmascarado en UI (solo últimos 4 caracteres)
- **ADDED**: Verificación de capacidad `manage_options` en todas las acciones
- **ADDED**: Nonces en todos los formularios AJAX
- **ADDED**: Sanitización y escape de todos los datos

### 📁 Nuevos Archivos
- `includes/class-cloudflare-service.php` - Servicio principal CF
- `includes/cloudflare-admin.php` - UI de administración
- `includes/cloudflare-cron.php` - Sincronización automática

## [1.2.1] - 2025-11-10

### 🎯 CRÍTICO - API REST para Plugin Sello-Replanta
- **ADDED**: Endpoint REST API `/wp-json/replanta/v1/check_domain` para verificación de dominios
- **ADDED**: Archivo `includes/rest-api.php` con endpoints REST
- **ADDED**: Endpoint `/wp-json/replanta/v1/stats` para estadísticas (solo admin)
- **FIXED**: Integración completa entre plugin `sello-replanta` y `dominios-reseller`
- **FIXED**: Problema "El dominio no está en replanta" en webs de clientes

### 🔌 Funcionalidad de API
- **ADDED**: Verificación pública de dominios vía POST
- **ADDED**: Búsqueda automática en ambos servidores (UK y USA)
- **ADDED**: Priorización de dominios Activos sobre Suspendidos
- **ADDED**: Respuesta JSON con datos completos (árboles, CO2, fechas, estado)
- **ADDED**: Validación de formato de dominio antes de búsqueda
- **ADDED**: Protección SQL injection con `$wpdb->prepare()`

### 🛠️ Herramientas de Diagnóstico
- **ADDED**: Script de prueba PHP `test-api-endpoint.php`
- **ADDED**: Script de prueba PowerShell `test-api-endpoint.ps1`
- **ADDED**: Documentación completa en `SOLUCION-API-REST.md`
- **ADDED**: Logs detallados para debug (si WP_DEBUG activo)

### 📊 Detalles Técnicos
- **COMPATIBILITY**: 100% compatible con versiones anteriores
- **SECURITY**: Endpoint público pero solo expone info ecológica
- **PERFORMANCE**: Consultas optimizadas con índices existentes
- **SCALABILITY**: Soporta múltiples servidores automáticamente

## [1.0.1] - 2025-09-07

### 🚨 CRÍTICO - Error Fatal Resuelto
- **FIXED**: Error fatal "Cannot access offset of type string on string" en línea 79
- **FIXED**: Validación robusta de tipos de datos en APIs WHM
- **FIXED**: Manejo seguro de respuestas de addon domains

### 🛡️ Mejoras de Seguridad y Estabilidad
- **ADDED**: Validaciones de tipo array antes de foreach
- **ADDED**: Timeouts en llamadas cURL (30s conexión, 10s timeout)
- **ADDED**: Códigos de estado HTTP en validaciones
- **ADDED**: Logging mejorado con prefijos identificables
- **ADDED**: Manejo de errores en estructura de respuesta API

### 🔧 Mejoras Técnicas
- **IMPROVED**: Función `obtener_addons_de_usuario()` con validaciones completas
- **IMPROVED**: Función `obtener_cuentas_whm()` con manejo robusto de errores
- **IMPROVED**: Función `obtener_trafico_real()` con validaciones de datos
- **IMPROVED**: Logging consistente con formato "[Dominios Reseller]"

### 🚀 Optimizaciones
- **OPTIMIZED**: Reducción de llamadas API fallidas
- **OPTIMIZED**: Mejor handling de respuestas malformadas
- **OPTIMIZED**: Skip automático de addon domains inválidos

## [1.0.0] - 2025-09-06
- **INITIAL**: Versión inicial del plugin
- **ADDED**: Integración con APIs WHM
- **ADDED**: Cálculo de huella de carbono por dominio
- **ADDED**: Gestión de addon domains
- **ADDED**: Interface de administración WordPress
