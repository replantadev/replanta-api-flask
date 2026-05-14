# 🚀 Archivos Modificados - Subir al Servidor

## 📁 Archivos que Necesitas Reemplazar

### 1. **includes/admin/class-report-editor.php**
**Cambios:**
- ✅ Eliminado `wp_update_post()` del hook `save_post` (causaba loop infinito 503)
- ✅ Añadida constante `TEW_SAVING` para prevenir re-entrada
- ✅ Usada consulta directa `$wpdb->update()` para actualizar título
- ✅ Añadido `remove_action` / `add_action` alrededor de la actualización
- ✅ Validación: no guarda si URL está vacía
- ✅ Optimización con `update_payload_bulk()` para actualizar todos los campos en una sola operación
- ✅ Añadido backslash a todas las funciones `\remove_meta_box()`
- ✅ **NUEVO:** Campo de fecha personalizable para informes manuales
- ✅ **NUEVO:** Conversión automática de fecha HTML5 datetime-local a formato MySQL UTC
- ✅ **NUEVO:** Si no se especifica fecha, usa la actual automáticamente

**Líneas críticas:**
- Línea ~62-68: Lectura de `META_GENERATED` y conversión a formato `datetime-local`
- Línea ~95-110: Campo HTML `<input type="datetime-local">` para seleccionar fecha
- Línea ~295-308: Procesamiento de fecha del formulario y conversión a MySQL UTC
- Línea ~329: Guardar fecha con `update_post_meta( $post_id, Report_Storage::META_GENERATED, $date )`

---

### 2. **includes/admin/class-admin-page.php**
**Cambios:**
- ✅ Todas las llamadas `wp_send_json_error()` cambiadas a `\wp_send_json_error()`
- ✅ Todas las llamadas `wp_send_json_success()` cambiadas a `\wp_send_json_success()`
- ✅ Esto soluciona el error 400 al eliminar casos de éxito

**Líneas críticas:**
- Línea ~720, 726, 731, 743: `\wp_send_json_error()`
- Línea ~738: `\wp_send_json_success()`

---

### 3. **includes/reporting/class-report-storage.php**
**Cambios previos (ya aplicados):**
- ✅ Método `delete_success_case()` añadido
- ✅ Método `get_success_cases()` devuelve `before_id` y `after_id`
- ✅ Método `find()` incluye `report_url` en metadata

---

### 4. **assets/js/admin.js**
**Cambios previos (ya aplicados):**
- ✅ Handler AJAX para eliminar casos de éxito
- ✅ Confirmación antes de eliminar
- ✅ Animación fadeOut al eliminar
- ✅ Notificaciones toast

---

### 5. **assets/css/admin.css**
**Cambios previos (ya aplicados):**
- ✅ Variables CSS actualizadas al manual de marca
- ✅ Colores: `#2d5c3f`, `#f4f1ea`, `#d4af37`

---

### 6. **assets/css/frontend-showcase.css**
**Cambios previos (ya aplicados):**
- ✅ Mismo sistema de colores del manual de marca

---

## 📤 Cómo Subir los Archivos

### Opción A: FTP/SFTP
```
1. Conecta a tu servidor con FileZilla/WinSCP
2. Ve a: /wp-content/plugins/test-eco-website/
3. Sube estos archivos (sobreescribir):
   - includes/admin/class-report-editor.php
   - includes/admin/class-admin-page.php
   - includes/reporting/class-report-storage.php
   - assets/js/admin.js
   - assets/css/admin.css
   - assets/css/frontend-showcase.css
```

### Opción B: cPanel File Manager
```
1. Accede a cPanel → Administrador de archivos
2. Navega a: public_html/wp-content/plugins/test-eco-website/
3. Sube los 6 archivos (sobreescribir existentes)
```

### Opción C: SSH
```bash
# Conecta por SSH
ssh usuario@tu-servidor.com

# Ve al directorio del plugin
cd /ruta/a/wp-content/plugins/test-eco-website/

# Sube los archivos con scp desde tu máquina local
# (ejecuta esto en tu terminal local, no en el servidor)
scp includes/admin/class-report-editor.php usuario@servidor:/ruta/plugin/includes/admin/
scp includes/admin/class-admin-page.php usuario@servidor:/ruta/plugin/includes/admin/
```

---

## ⚠️ Importante

1. **Haz backup antes de subir**
   ```bash
   # En el servidor
   cp includes/admin/class-report-editor.php includes/admin/class-report-editor.php.backup
   cp includes/admin/class-admin-page.php includes/admin/class-admin-page.php.backup
   ```

2. **Limpia caché después de subir**
   - Si usas caché de objetos (Redis/Memcached), limpialo
   - Si usas un plugin de caché (WP Rocket, W3 Total Cache), purga todo
   - Borra caché del navegador (Ctrl+Shift+R)

3. **Verifica que los archivos se subieron correctamente**
   - Edita `class-report-editor.php` en el servidor
   - Busca la línea con `define( 'TEW_SAVING', true );`
   - Si está ahí, el archivo se subió bien

---

## 🔍 Después de Subir

1. Ve a WordPress admin
2. Sigue el archivo `verificar-funcionalidad.md` para probar
3. Si algo falla, revisa los logs de PHP:
   ```bash
   tail -f /var/log/apache2/error.log  # o la ruta de tu servidor
   ```

---

## 📞 Si Necesitas Ayuda

Si después de subir los archivos sigues teniendo problemas:

1. Copia el error EXACTO de la consola del navegador (F12)
2. Revisa los logs de PHP del servidor
3. Comparte capturas de pantalla del error
4. Verifica que los permisos de archivos sean correctos (644 para archivos, 755 para directorios)

---

**TODO ARREGLADO EN ESTOS 2 ARCHIVOS:**
- ✅ `includes/admin/class-report-editor.php` → Fix 503
- ✅ `includes/admin/class-admin-page.php` → Fix 400

Los demás archivos ya estaban correctos de modificaciones anteriores.
