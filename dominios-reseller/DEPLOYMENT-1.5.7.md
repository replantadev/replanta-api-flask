# ⚡ DEPLOYMENT RÁPIDO - Versión 1.5.7

## 📦 Archivos a Subir (FTP a replanta.net)

### MODIFICADOS (sobrescribir):
```
/wp-content/plugins/dominios-reseller/dominios-reseller.php (versión 1.5.7)
/wp-content/plugins/dominios-reseller/CHANGELOG.md
/wp-content/plugins/dominios-reseller/includes/class-onboarding-db.php
/wp-content/plugins/dominios-reseller/includes/class-onboarding-worker.php
/wp-content/plugins/dominios-reseller/includes/class-debug-hub.php
/wp-content/plugins/dominios-reseller/includes/class-openprovider-service.php
/wp-content/plugins/dominios-reseller/SOLUCION-NS-ES.md (NUEVO)
```

---

## ✅ Checklist de Deployment

- [ ] **1. Backup del plugin actual**
- [ ] **2. Conectar FTP a replanta.net**
- [ ] **3. Navegar a:** `/wp-content/plugins/dominios-reseller/`
- [ ] **4. Sobrescribir:** `dominios-reseller.php` (versión 1.5.7)
- [ ] **5. Sobrescribir:** `CHANGELOG.md`
- [ ] **6. Sobrescribir:** `includes/class-onboarding-db.php`
- [ ] **7. Sobrescribir:** `includes/class-onboarding-worker.php`
- [ ] **8. Sobrescribir:** `includes/class-debug-hub.php`
- [ ] **9. Sobrescribir:** `includes/class-openprovider-service.php`
- [ ] **10. Subir:** `SOLUCION-NS-ES.md` (archivo NUEVO)
- [ ] **11. Verificar permisos:** 644 para todos los archivos .php
- [ ] **12. Limpiar cache de WordPress**

---

## 🧪 Verificación Post-Deployment

### Test 1: Verificar versión del plugin
Ir a Plugins → Dominios Reseller → debería mostrar "Versión: 1.5.7"

### Test 2: Probar Debug Hub
- Ir a Herramientas → Debug Hub
- Ejecutar "🔍 Diagnóstico Openprovider"
- Debería mostrar información detallada de carani.es

### Test 3: Probar onboarding
- Encolar carani.es con preset WordPress
- Debería crear zona en Cloudflare correctamente
- NS deberían requerir configuración manual (esperado para .es)

### Test 6: Actualizar presets
- Ir a Herramientas → Debug Hub → Gestión de Datos
- Ejecutar "🔄 Actualizar Presets"
- Verificar que los presets se actualicen a v3.0

### Test 7: Verificar modal de logs mejorado
- En el panel de CF Onboarding, hacer click en "📋 Logs" de cualquier dominio
- Verificar que se muestre información detallada con datos JSON
- Los errores de "Setting no reconocido" deberían tener datos detallados

---

## 🔄 Rollback Plan
Si hay problemas:
1. Restaurar backup del plugin
2. Revisar logs de error en `/wp-content/debug.log`
3. Contactar soporte si es necesario

---

## 📋 Notas Importantes
- **Dominios .es**: NS requieren configuración manual hasta autorización de Cloudflare
- **Preset actualizado**: Compatible con Cloudflare API v4
- **Autenticación**: Ahora prioriza API Token sobre Global API Key
- **Diagnóstico mejorado**: Más información para troubleshooting