# 🔍 Instrucciones de Debugging - Error 400 al Eliminar Caso

## Archivos modificados

He agregado **logging forzado** que escribe directamente en un archivo, sin depender de WP_DEBUG:

### 1. `includes/admin/class-admin-page.php`
- Ahora usa `file_put_contents()` para escribir logs en **`wp-content/tew-debug.log`**
- Se escribe en CADA paso del proceso

### 2. `assets/js/admin.js`
- Agregué `console.log()` con emoji 🔍 para ver los datos enviados
- Muestra el status, headers y respuesta completa del servidor

## Pasos para testear

### 1. Sube los archivos modificados:
```
includes/admin/class-admin-page.php
assets/js/admin.js
```

### 2. Limpia la caché del navegador:
- **Chrome/Edge**: Ctrl + Shift + Delete
- O abre DevTools (F12) → Network → Marcar "Disable cache"

### 3. Recarga la página del admin:
```
https://replanta.net/wp-admin/admin.php?page=test-eco-website
```

### 4. Abre la consola del navegador:
- Presiona **F12**
- Ve a la pestaña **Console**

### 5. Intenta eliminar el caso 7432

### 6. Observa en consola:
Deberías ver algo como:
```
🔍 TEW DELETE - Datos enviados: {
  action: "tew_delete_success_case",
  case_id: 7432,
  nonce: "5f86f7b157",
  ajaxurl: "https://replanta.net/wp-admin/admin-ajax.php"
}
🔍 TEW DELETE - Response status: 400
🔍 TEW DELETE - Response headers: Headers { ... }
🔍 TEW DELETE - Response text: [AQUÍ VEREMOS EL VERDADERO ERROR]
```

### 7. Revisa el archivo de log:
Conecta por FTP/SFTP y descarga:
```
/wp-content/tew-debug.log
```

## Qué esperamos encontrar

### ✅ Escenario 1: El código PHP SÍ se ejecuta
Si ves líneas en `tew-debug.log` como:
```
[2025-10-20 15:30:45] TEW AJAX - Inicio ajax_delete_success_case
[2025-10-20 15:30:45] TEW AJAX - POST: Array ( [action] => tew_delete_success_case ...
```

Entonces el problema está en la **lógica PHP** (nonce, permisos, o delete_success_case).

### ❌ Escenario 2: El archivo NO existe o está vacío
Si `tew-debug.log` no existe o está vacío, significa que:
- **El hook no está registrado correctamente**
- **WordPress está devolviendo 400 ANTES de llegar al código PHP**

### 🔍 Escenario 3: La consola muestra HTML en lugar de JSON
Si en "Response text" ves HTML (como `<!DOCTYPE html>`), significa que:
- PHP está generando un error FATAL
- El output se corrompe antes de llegar a `wp_send_json`

## Posibles causas del 400

### Causa 1: Hook no registrado
El hook está en el constructor de `Admin_Page`:
```php
add_action( 'wp_ajax_tew_delete_success_case', [ $this, 'ajax_delete_success_case' ] );
```

Si la clase no se instancia, el hook no existe → 400.

### Causa 2: Error fatal en PHP
Si hay un error de sintaxis o un `Fatal Error` en `class-admin-page.php`:
- PHP devuelve 500 o 400
- No se genera ningún log

### Causa 3: Output previo
Si hay un `echo`, `print_r`, o whitespace ANTES de `wp_send_json`:
- La respuesta se corrompe
- WordPress devuelve 400

### Causa 4: Security plugin bloqueando
Algunos plugins de seguridad bloquean peticiones AJAX con ciertos nonces.

## Siguiente paso según resultado

### Si ves logs en tew-debug.log:
Comparte TODO el contenido del archivo aquí.

### Si NO ves logs:
1. Verifica que `class-admin-page.php` se subió correctamente
2. Comparte el "Response text" de la consola
3. Revisa el log de errores de PHP del servidor

### Si ves HTML en Response text:
Hay un error fatal en PHP. Comparte el HTML completo.

---

## 📤 Qué compartir conmigo

Después de probar, envíame:

1. **Screenshot de la consola** (con los logs 🔍)
2. **Contenido de `wp-content/tew-debug.log`** (si existe)
3. **El texto completo de "Response text"** de la consola

Con esto podré identificar EXACTAMENTE dónde falla.
