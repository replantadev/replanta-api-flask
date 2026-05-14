# Guía de Integración con Upmind

## Configuración del Webhook en Upmind

### Paso 1: Acceder al Panel de Upmind
1. Inicia sesión en tu panel de Upmind como administrador
2. Ve a **Settings → Webhooks**

### Paso 2: Crear Nuevo Webhook
Crea un webhook con la siguiente configuración:

- **Nombre**: Dominios Reseller - Onboarding Automático
- **URL**: `<?php echo rest_url('dominios-reseller/v1/webhook/upmind'); ?>`
- **Eventos**: Selecciona únicamente `order.completed`
- **Método**: POST
- **Formato**: JSON
- **Estado**: Activo

### Paso 3: Configuración en WordPress
1. Ve a **Dominios Reseller → Configuración de Integraciones**
2. En el tab **Configuración**:
   - Marca "Habilitar integración con Upmind"
   - URL de API: Tu URL de API de Upmind (ej: https://api.upmind.com/v1)
   - API Key: Tu clave de API de Upmind
   - Webhook Secret: El secret generado al crear el webhook
3. Guarda los cambios

## Eventos Procesados

El webhook procesa el evento `order.completed` cuando:
- Un cliente completa una compra que incluye dominio + hosting
- El pedido se marca como completado en Upmind
- Se envían los datos del dominio y tipo de hosting

## Formato de Datos Esperado

```json
{
  "event": "order.completed",
  "data": {
    "order_id": "ORD-12345",
    "domain": "cliente.com",
    "hosting_type": "wordpress",
    "customer_email": "cliente@email.com",
    "services": [
      {
        "type": "hosting",
        "plan": "wordpress_pro"
      },
      {
        "type": "domain",
        "name": "cliente.com"
      }
    ]
  }
}
```

## Flujo de Operación

1. **Recepción**: Plugin recibe webhook de Upmind
2. **Validación**: Verifica integridad del webhook con HMAC
3. **Extracción**: Obtiene dominio y tipo de hosting
4. **WP Readiness**: Consulta WHM/cPanel para verificar compatibilidad
5. **Cloudflare**: Crea zona y aplica configuración
6. **Preset**: Aplica configuración según tipo de hosting
7. **NS Update**: Actualiza nameservers si está habilitado
8. **Notificación**: Envía email al cliente con instrucciones

## Configuración de WHM/cPanel

Para verificar automáticamente el readiness de WordPress:

### En WHM:
1. Crea un usuario con permisos de API
2. Ve a **Home → Development → Manage API Tokens**
3. Genera un nuevo token con permisos de lectura

### En WordPress:
1. Ve a **Dominios Reseller → Configuración de Integraciones**
2. Configura:
   - WHM Hostname: tu-servidor.com
   - WHM Username: usuario_whm
   - API Token: token_generado
   - Puerto: 2087 (por defecto)
3. Prueba la conexión con el botón "Probar Conexión con WHM"

## Monitoreo y Troubleshooting

### Debug Hub
Usa el Debug Hub para testing y diagnóstico:

- **Test de Flujo Completo**: Simula todo el proceso de onboarding
- **Estado de Dominios**: Revisa progreso individual
- **Logs**: Diagnóstico detallado de operaciones
- **Reintentos**: Para dominios con problemas

### Dashboard de Monitoreo
Accede desde el menú principal para ver:
- KPIs en tiempo real (éxito, fallos, tiempos)
- Alertas activas que requieren atención
- Historial de operaciones recientes
- Estado de conectividad de APIs

## Configuración de Auto-Discovery

Para detectar dominios automáticamente sin intervención manual:

### Email Scanning
- Configura servidor IMAP
- El sistema escanea emails en busca de nuevos dominios
- Compatible con Gmail, Outlook, etc.

### Upmind Polling
- Consulta API de Upmind periódicamente
- Detecta pedidos completados automáticamente
- Sincronización bidireccional

### Openprovider Integration
- Sincronización con registros de dominio
- Detección automática de transferencias
- Actualización de estados en tiempo real

## Resolución de Problemas Comunes

### Webhook no se recibe
- Verifica que la URL del webhook sea accesible
- Confirma que el secret esté configurado correctamente
- Revisa logs de WordPress para errores de validación HMAC

### Error de conexión con WHM
- Verifica hostname y puerto
- Confirma credenciales de API
- Asegúrate de que el firewall permita conexiones

### Fallo en creación de zona Cloudflare
- Verifica API key y email de Cloudflare
- Confirma que el dominio no esté ya registrado
- Revisa límites de API (3 zonas gratuitas por defecto)

### Problemas de permisos
- Asegúrate de que el usuario de WordPress tenga permisos manage_options
- Verifica que las APIs externas estén habilitadas
- Confirma configuración de CORS si es necesario

## Logs y Auditoría

Todos los eventos se registran en la tabla `dr_onboarding_logs`:
- Webhook receptions
- API calls externos
- Errores y excepciones
- Cambios de estado

Los logs incluyen:
- Timestamp preciso
- ID de operación
- Nivel de severidad
- Mensaje detallado
- Contexto adicional en JSON
Webhook Secret: [el_secreto_que_configuraste]
✅ Onboarding Automático: [X]
```

#### **WHM/cPanel Settings:**
```
✅ Habilitar WHM: [X]
WHM Hostname: whm.tudominio.com
WHM Username: root (o tu usuario WHM)
API Token: [tu_whm_api_token]
Puerto: 2087
```

---

## 🔄 **Flujo de Onboarding Automático**

### **Cuando un cliente compra hosting en Upmind:**

1. **🏷️ Upmind** recibe la orden y envía webhook
2. **🔍 Plugin** detecta orden de hosting con dominio
3. **⚡ Cloudflare** crea zona automáticamente
4. **🔧 Preset** aplica configuración optimizada
5. **📧 Cliente** recibe notificación de "optimización completada"
6. **✅ WP Ready** indicador muestra estado en panel

### **Indicadores WP Ready:**
- **✅ Verde**: Todo listo para WordPress
- **❌ Rojo**: Requiere ajustes
- **🔍 Azul**: Verificando...

---

## 🎨 **Nuevas Features en la UI**

### **Columna "WP Ready" en Lista de Dominios**
Cada dominio ahora muestra un indicador visual del estado de preparación para WordPress.

### **Meta Box "WordPress Readiness Check"**
En cada dominio individual, puedes:
- **Verificar** estado completo de PHP/extensions
- **Corregir** problemas automáticamente
- **Ver detalles** de configuraciones requeridas

### **Dashboard de Monitoreo**
Nueva página **📊 Monitoreo** con:
- KPIs en tiempo real
- Gráfico de actividad 24h
- Alertas automáticas
- Controles del sistema

---

## 🔍 **Verificación de WP Readiness**

### **Checks Automáticos:**
- ✅ **PHP Version**: 7.4+ requerido, 8.0+ recomendado
- ✅ **Extensiones PHP**: curl, gd, mbstring, mysqlnd, openssl, xml, zip
- ✅ **Límites PHP**: memory_limit, execution_time, upload_size
- ✅ **Permisos**: Directorios y archivos con permisos correctos

### **Corrección Automática:**
- 🔄 **Actualizar PHP** versión
- 🔄 **Instalar extensiones** faltantes
- 🔄 **Ajustar límites** de configuración
- 🔄 **Corregir permisos** de archivos

---

## 📊 **Monitoreo y Alertas**

### **KPIs en Tiempo Real:**
- 🏷️ **Dominios hoy**: Procesados en el día actual
- 🎯 **Tasa de éxito**: Porcentaje de onboardings exitosos
- ⏱️ **Tiempo promedio**: Minutos para completar onboarding
- 📋 **En cola**: Dominios esperando procesamiento

### **Alertas Automáticas:**
- 🚨 **Cola larga**: Más de 10 dominios pendientes
- 🚨 **Tasa baja**: Menos del 80% de éxito
- 🚨 **Worker inactivo**: Sistema sin actividad reciente

---

## 🛠️ **Solución de Problemas**

### **Webhook no llega:**
```bash
# Verificar URL del webhook
curl -X POST https://tudominio.com/wp-json/dominios-reseller/v1/webhook/upmind \
  -H "Content-Type: application/json" \
  -d '{"test": "webhook"}'
```

### **WHM API no conecta:**
```bash
# Test manual de conexión
curl -H "Authorization: whm root:TOKEN" \
  https://whm.tudominio.com:2087/json-api/version
```

### **Debug Mode:**
```php
// En wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Ver logs en wp-content/debug.log
```

---

## 📈 **Próximos Pasos Recomendados**

### **Fase 1: Configuración Básica (Esta semana)**
- [ ] Configurar webhook en Upmind
- [ ] Probar conexión con WHM API
- [ ] Verificar onboarding automático

### **Fase 2: Optimización (2 semanas)**
- [ ] Ajustar presets de Cloudflare
- [ ] Configurar notificaciones al cliente
- [ ] Monitorear KPIs iniciales

### **Fase 3: Automatización Completa (1 mes)**
- [ ] Implementar corrección automática de PHP
- [ ] Añadir más checks de readiness
- [ ] Integrar con sistema de soporte

---

## 💡 **¿Necesitas Ayuda?**

**Configuración inicial:**
1. ¿Tienes acceso a la API de Upmind?
2. ¿Puedes generar un API token en WHM?
3. ¿Qué dominios usar para testing?

**Personalización:**
1. ¿Qué eventos específicos de Upmind quieres monitorear?
2. ¿Qué checks adicionales de WP readiness necesitas?
3. ¿Cómo quieres que se notifique a los clientes?

¿Empezamos configurando los webhooks en Upmind? 🚀