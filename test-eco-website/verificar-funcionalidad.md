# ✅ Verificación de Funcionalidad - Test Eco Website

## 🔧 Cambios Realizados

### 1. **FIX CRÍTICO - Loop Infinito en save_metabox (503 Error)**
**Archivo:** `includes/admin/class-report-editor.php`
**Problema:** `wp_update_post()` dentro del hook `save_post` causaba recursión infinita
**Solución:** 
- Añadida constante `TEW_SAVING` para evitar re-entrada
- Reemplazado `wp_update_post()` con consulta directa `$wpdb->update()`
- Añadido `remove_action` y `add_action` alrededor de la actualización
- Añadida validación: si URL está vacía, no guarda nada

### 2. **FIX CRÍTICO - Error 400 en Delete AJAX**
**Archivo:** `includes/admin/class-admin-page.php`
**Problema:** Funciones `wp_send_json_*` sin prefijo de namespace global
**Solución:** Cambiadas todas las llamadas a `\wp_send_json_error()` y `\wp_send_json_success()`

### 3. **Optimización - Metaboxes Innecesarios**
**Archivo:** `includes/admin/class-report-editor.php`
**Solución:** Añadido `\` a todas las llamadas `remove_meta_box()` para usar función global

---

## 🧪 Pasos de Prueba

### **PRUEBA 1: Crear Informe Manual (Fix 503)**
1. Ve a `Informes` → `Añadir nuevo`
2. Rellena:
   - **URL:** `https://ejemplo.com`
   - **Fecha del informe:** Elige la fecha/hora que quieras (o déjala vacía para usar la actual)
   - **Puntuación:** `75.5`
   - **Nota:** `B`
   - **CO₂:** `0.50` (debe aparecer el placeholder)
   - **Hosting verde:** Marca el checkbox
   - **Proveedor:** `GreenGeeks`
3. Clic en **"Publicar"** o **"Actualizar"**
4. ✅ **ESPERADO:** Se guarda sin error 503
5. ✅ **ESPERADO:** El título debe ser "Informe ejemplo.com · Manual"
6. ✅ **ESPERADO:** No debes ver metaboxes de RankMath, Imagen destacada, Elementor
7. ✅ **ESPERADO:** La fecha se guarda correctamente (visible en columna "Fecha" de la lista)

---

### **PRUEBA 2: Verificar Datos Guardados**
1. Edita el informe que acabas de crear
2. ✅ **ESPERADO:** Todos los campos deben mostrar los valores que guardaste, **incluida la fecha**
3. ✅ **ESPERADO:** En la lista de informes, debes ver columnas: URL, Score, CO₂, Green, Date
4. ✅ **ESPERADO:** La columna "Green" debe mostrar "✓ Sí" (verde)
5. ✅ **ESPERADO:** La columna "Date" debe mostrar la fecha que estableciste (o la actual si la dejaste vacía)

---

### **PRUEBA 3: Crear Caso de Éxito**
1. Crea DOS informes manuales:
   - **Informe ANTES:**
     - URL: `https://sitio-antiguo.com`
     - Fecha: Elige una fecha antigua (ej: 1 de enero de 2024)
     - Puntuación: `35`
     - Nota: `F`
     - CO₂: `1.20`
     - Hosting verde: NO
   
   - **Informe DESPUÉS:**
     - URL: `https://sitio-nuevo.com`
     - Fecha: Elige una fecha reciente (ej: hoy)
     - Puntuación: `85`
     - Nota: `A`
     - CO₂: `0.30`
     - Hosting verde: SÍ
     - Proveedor: `GreenGeeks`

2. Ve a `Ajustes` → `Test Eco Website` → pestaña **"Casos de Éxito"**
3. Rellena el formulario:
   - **Informe ANTES:** Selecciona "sitio-antiguo.com"
   - **Informe DESPUÉS:** Selecciona "sitio-nuevo.com"
4. Clic en **"Guardar cambios"**
5. ✅ **ESPERADO:** Aparece el caso de éxito en la lista
6. ✅ **ESPERADO:** Muestra las dos cards (ANTES / DESPUÉS) con datos correctos
7. ✅ **ESPERADO:** No debe faltar ningún campo (URL, **fecha**, puntuación, CO₂)
8. ✅ **ESPERADO:** Las fechas deben mostrarse correctamente en cada card

---

### **PRUEBA 4: Eliminar Caso de Éxito (Fix 400)**
1. En la lista de casos de éxito, busca el botón **"Eliminar"** (ícono de papelera)
2. Clic en **"Eliminar"**
3. ✅ **ESPERADO:** Aparece confirmación: "¿Estás seguro de eliminar este caso de éxito?"
4. Confirma
5. ✅ **ESPERADO:** El caso desaparece de la lista con animación
6. ✅ **ESPERADO:** Aparece notificación verde: "Caso de éxito eliminado correctamente"
7. ❌ **NO DEBE:** Error 400 en la consola del navegador
8. ❌ **NO DEBE:** Error en la respuesta AJAX

---

### **PRUEBA 5: Verificar Colores del Manual de Marca**
1. Revisa los casos de éxito en la página de ajustes
2. ✅ **ESPERADO:** 
   - Badges (A, B, etc.): Color dorado sólido `#d4af37`
   - Fondo de las cards: Beige `#f4f1ea`
   - Encabezados: Verde `#2d5c3f`
   - Bordes suaves (sin sombras fuertes)

---

## 🐛 Si Algo Falla

### **Error 503 al guardar:**
- Revisa que la constante `TEW_SAVING` esté definida al inicio de `save_metabox()`
- Verifica que `$wpdb->update()` esté rodeado de `remove_action` / `add_action`

### **Error 400 al eliminar:**
- Abre la consola del navegador (F12)
- Ve a la pestaña **Network**
- Busca la petición a `admin-ajax.php`
- Revisa la respuesta: debe ser JSON con `success: true`
- Si ves `success: false`, copia el mensaje de error

### **Metaboxes no desaparecen:**
- Asegúrate de que el hook `add_action( 'add_meta_boxes', ... )` esté registrado
- Verifica que `\remove_meta_box()` tenga el backslash

---

## 📋 Checklist Final

- [ ] Puedo crear informes manuales sin error 503
- [ ] Los informes manuales guardan todos los campos correctamente **incluida la fecha**
- [ ] Puedo establecer una fecha personalizada para cada informe
- [ ] Si dejo la fecha vacía, se usa la fecha actual automáticamente
- [ ] Veo el placeholder "0.50" en el campo CO₂
- [ ] No veo metaboxes de RankMath, imágenes, Elementor
- [ ] Veo las columnas personalizadas (URL, Score, CO₂, etc.) en la lista
- [ ] La columna "Date" muestra la fecha correcta de cada informe
- [ ] Puedo crear casos de éxito enlazando dos informes
- [ ] Los casos de éxito muestran todas las tarjetas con datos completos **incluidas las fechas**
- [ ] Puedo eliminar casos de éxito sin error 400
- [ ] Los colores siguen el manual de marca (#2d5c3f, #f4f1ea, #d4af37)

---

## 🎯 Resultado Esperado

**TODO** debe funcionar perfectamente:
✅ Crear informes manuales
✅ Guardar sin errores
✅ Eliminar casos de éxito
✅ Ver datos correctos en listas
✅ Diseño acorde al manual de marca

Si algún test falla, avísame con el error específico que ves.
