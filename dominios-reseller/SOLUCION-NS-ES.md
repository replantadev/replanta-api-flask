# SOLUCIÓN PROBLEMA NS CLOUDFLARE PARA .ES

## Problema Identificado
Openprovider está rechazando los NS de Cloudflare (`doug.ns.cloudflare.com`, `nia.ns.cloudflare.com`) para dominios .es con el error "Invalid request".

## Causa
Los dominios .es tienen restricciones especiales del registro español (Red.es). Los NS deben estar autorizados o cumplir criterios específicos.

## Soluciones

### Opción 1: Autorización de NS (Recomendada)
1. Contactar a Openprovider support
2. Solicitar autorización de los NS de Cloudflare para dominios .es
3. Proporcionar los NS: `doug.ns.cloudflare.com` y `nia.ns.cloudflare.com`

### Opción 2: Configuración Manual
Si la autorización no es posible inmediatamente:

1. **Zona Cloudflare ya está creada** - El sistema crea la zona correctamente
2. **Configurar NS manualmente** en el panel de Openprovider:
   - Ir a Dominios > carani.es > Nameservers
   - Cambiar a:
     - `doug.ns.cloudflare.com`
     - `nia.ns.cloudflare.com`
3. **Verificar propagación** - Puede tomar 24-48 horas

### Opción 3: NS Alternativos
Si Cloudflare no está autorizado, usar NS alternativos autorizados:
- ns1.hostinger.es / ns2.hostinger.es
- ns1.siteground.es / ns2.siteground.es
- ns1.cdmon.net / ns2.cdmon.net

## Estado Actual
- ✅ Zona Cloudflare creada correctamente
- ✅ SSL y configuraciones aplicadas
- ❌ NS no actualizados automáticamente
- 🔄 Requiere configuración manual o autorización

## Verificación
Después de configurar los NS manualmente, verificar:
1. El dominio resuelve a la IP de Cloudflare
2. SSL funciona correctamente
3. El sitio carga desde Cloudflare

## Contacto Openprovider
Para solicitar autorización:
- Email: support@openprovider.com
- Indicar: "Solicitud de autorización NS Cloudflare para dominios .es"
- Proporcionar: doug.ns.cloudflare.com, nia.ns.cloudflare.com