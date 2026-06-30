# 🚀 GUÍA COMPLETA: Conectar Replanta Care con HUB

## 📋 RESUMEN DE PROBLEMAS SOLUCIONADOS

### ✅ Problemas CORS en HUB - SOLUCIONADOS
- Reemplazadas todas las funciones `fetch()` por `jQuery.ajax()`
- Corregidas URLs inconsistentes 
- Variable `ajaxurl` disponible en todos los contextos

### ✅ Generación de Tokens Únicos - IMPLEMENTADO
- Cada sitio ahora genera un token único automáticamente
- Token se muestra al usuario después de crear el sitio
- Token se incluye en la respuesta AJAX

### ✅ APIs para Care - IMPLEMENTADAS
- `/api/sites/plan` - Para obtener información del plan
- `/api/test-connection` - Para probar conexión
- `rphub_test_care_connection` - Handler AJAX

---

## 🔧 PROCESO DE CONFIGURACIÓN

### **PASO 1: Registrar sitio en el HUB**

1. **Ve al HUB local:**
   ```
   http://repo.local/wp-admin
   ```

2. **Navega a:**
   ```
   Replanta HUB → Sites → Añadir Sitio
   ```

3. **Llena el formulario:**
   - **Nombre:** ADFC Colombia
   - **URL:** https://adfc.com.co
   - **Plan:** Semilla/Raíz/Ecosistema (el que corresponda)

4. **Al guardar, aparecerá un ALERT con el token:**
   ```
   ✅ Sitio creado exitosamente!
   
   🔑 TOKEN GENERADO:
   [TOKEN_ÚNICO_DE_32_CARACTERES]
   
   Copia este token y configúralo en Replanta Care del sitio.
   ```

5. **COPIA ESE TOKEN** - lo necesitarás en el siguiente paso.

---

### **PASO 2: Configurar Replanta Care en el sitio externo**

1. **Ve al sitio donde está instalado Care:**
   ```
   https://adfc.com.co/wp-admin
   ```

2. **Opción A - Configuración Manual:**
   - Ve a `Replanta Care → Settings`
   - **HUB URL:** `http://repo.local` 
   - **Site Token:** [PEGA_EL_TOKEN_COPIADO]
   - Haz clic en "Probar Conexión"

3. **Opción B - Helper de Configuración:**
   - Sube el archivo `configure.php` a `/wp-content/plugins/replanta-care/`
   - Visita: `https://adfc.com.co/wp-content/plugins/replanta-care/configure.php`
   - Sigue las instrucciones automáticas

---

### **PASO 3: Verificar conexión**

1. **En el sitio Care (adfc.com.co):**
   - Ve a `Replanta Care → Settings`
   - Haz clic en "Probar Conexión"
   - Deberías ver: "✅ Conexión exitosa con el Hub"

2. **Verificar widget:**
   - Ve al Dashboard de WordPress
   - Busca el widget "🛡️ Replanta Care"
   - Debería mostrar el plan correcto (no solo fondo morado)

3. **En el HUB (repo.local):**
   - Ve a `Replanta HUB → Sites`
   - Busca el sitio ADFC
   - Haz clic en "Test Connection" - debería funcionar
   - Haz clic en "Sync Data" - debería funcionar sin errores CORS

---

## 🐛 TROUBLESHOOTING

### Problema: "Error del servidor: 404 en https://sitios.replanta.dev/..."
**Causa:** Care está usando URL incorrecta
**Solución:** 
1. Verificar que HUB URL = `http://repo.local` (NO sitios.replanta.dev)
2. Usar el helper `configure.php` para auto-configurar

### Problema: "Site not registered in Hub"
**Causa:** El sitio no está registrado en el HUB
**Solución:**
1. Ir al HUB → Sites → Añadir Sitio
2. Usar exactamente la misma URL que muestra el Care

### Problema: "Invalid token"
**Causa:** Token incorrecto o no configurado
**Solución:**
1. Verificar que el token en Care coincida con el del HUB
2. Regenerar el sitio en el HUB si es necesario

### Problema: Errores CORS
**Causa:** Ya solucionado, pero si aparecen nuevos
**Solución:**
1. Verificar que todas las funciones JavaScript usen `jQuery.ajax()`
2. Verificar que `ajaxurl` esté definido

---

## 📁 ARCHIVOS CREADOS/MODIFICADOS

```
replanta-hub/
├── inc/admin-sites.php (Corregido CORS + mostrar token)
├── inc/class-site-manager.php (APIs Care + token en respuesta)
├── register-site.php (Helper registro manual)

replanta-care/
├── configure.php (Helper configuración)
```

---

## 🎯 PRÓXIMOS PASOS

1. **Ejecutar PASO 1:** Registrar adfc.com.co en el HUB
2. **Copiar el token** que aparezca en el alert
3. **Ejecutar PASO 2:** Configurar Care en adfc.com.co con ese token
4. **Verificar** que todo funcione según PASO 3

¡El sistema debería funcionar perfectamente después de estos pasos! 🚀
