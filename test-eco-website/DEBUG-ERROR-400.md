# 🔍 DEBUG - Error 400 al Eliminar Caso de Éxito

## ✅ Cambios Aplicados

He añadido **logging extensivo** para identificar exactamente dónde falla la eliminación del caso de éxito.

### Archivos Modificados:

1. **`includes/admin/class-admin-page.php`**
   - Añadidos logs en `ajax_delete_success_case()`
   - Logs en cada paso: recepción de datos, verificación de nonce, permisos, llamada a storage

2. **`includes/reporting/class-report-storage.php`**
   - Añadidos logs en `delete_success_case()`
   - Logs para verificar si el post existe, si es un caso de éxito, y resultado final

---

## 🧪 Pasos para Probar y Ver los Logs

### 1. **Sube los Archivos al Servidor**
```bash
includes/admin/class-admin-page.php
includes/reporting/class-report-storage.php
```

### 2. **Habilita Debug de WordPress** (si no está activo)
Edita `wp-config.php` y añade:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

### 3. **Intenta Eliminar el Caso de Éxito**
- Ve a `Ajustes` → `Test Eco Website` → pestaña `Casos de Éxito`
- Clic en el botón **"Eliminar caso"** del caso 7432
- Observa el error 400 (esperado por ahora)

### 4. **Revisa los Logs de PHP**
```bash
# SSH al servidor
tail -f /var/log/apache2/error.log
# O si usas Nginx:
tail -f /var/log/nginx/error.log
# O el log de WordPress:
tail -f /ruta/a/wp-content/debug.log
```

### 5. **Busca las Líneas que Empiezan con "TEW"**
```
TEW AJAX - Inicio de ajax_delete_success_case
TEW AJAX - POST data: Array ( [action] => tew_delete_success_case [nonce] => 5f86f7b157 [case_id] => 7432 )
TEW AJAX - Case ID recibido: 7432
TEW AJAX - Verificación de nonce: OK (o FALLO)
TEW Delete Case - Intentando eliminar caso: 7432
TEW Delete Case - Meta IS_SUCCESS_CASE para 7432: true (o false)
```

---

## 📋 Posibles Causas y Soluciones

### ❌ **Causa 1: Nonce Inválido**
**Log esperado:**
```
TEW AJAX - Verificación de nonce: FALLO
```

**Solución:** El nonce expira después de 12-24 horas. Recarga la página y vuelve a intentar.

---

### ❌ **Causa 2: El Post 7432 No Es un Caso de Éxito**
**Log esperado:**
```
TEW Delete Case - Meta IS_SUCCESS_CASE para 7432: false
TEW Delete Case - No es un caso de éxito: 7432
```

**Solución:** Verifica en la base de datos:
```sql
SELECT * FROM wp_postmeta WHERE post_id = 7432 AND meta_key = '_tew_is_success_case';
```

Si no existe, el caso no se marcó correctamente al crearlo.

---

### ❌ **Causa 3: El Post 7432 No Existe o Es de Otro Tipo**
**Log esperado:**
```
TEW Delete Case - Post no existe o tipo incorrecto: 7432
```

**Solución:** Verifica que el post existe y es de tipo `tew_audit`:
```sql
SELECT ID, post_type, post_status FROM wp_posts WHERE ID = 7432;
```

---

### ❌ **Causa 4: Error en wp_send_json_* (namespace)**
**Log esperado:** 
```
PHP Fatal error: Call to undefined function TEW\Admin\wp_send_json_error()
```

**Solución:** Ya he añadido backslashes `\wp_send_json_error()`, pero si el problema persiste, necesitaremos usar `use function` en el archivo.

---

## 🔧 Si los Logs Muestran que TODO es OK

Si ves:
```
TEW AJAX - Verificación de nonce: OK
TEW Delete Case - Caso eliminado correctamente: 7432
```

Pero TODAVÍA ves error 400, entonces el problema es que **PHP muere antes de devolver el JSON**.

**Posibles causas:**
1. `wp_send_json_*` no está devolviendo JSON válido
2. Hay un warning/notice de PHP que corrompe la respuesta
3. El servidor está cortando la respuesta AJAX

**Solución:**
```php
// En class-admin-page.php, línea 749, cambiar:
\wp_send_json_success( [ 
    'message' => __( 'Caso de éxito eliminado correctamente', 'test-eco-website' ),
    'case_id' => $case_id,
] );

// Por:
header( 'Content-Type: application/json' );
echo json_encode( [ 
    'success' => true,
    'data' => [
        'message' => __( 'Caso de éxito eliminado correctamente', 'test-eco-website' ),
        'case_id' => $case_id,
    ]
] );
die();
```

---

## 📤 Siguiente Paso

1. **Sube los 2 archivos modificados**
2. **Intenta eliminar el caso**
3. **Copia y pégame los logs completos** que veas en `debug.log` o `error.log`

Con eso podré identificar exactamente qué está fallando y darte la solución definitiva.

---

## 🎯 Logs Esperados (Ejemplo Exitoso)

```
[20-Oct-2025 14:30:15 UTC] TEW AJAX - Inicio de ajax_delete_success_case
[20-Oct-2025 14:30:15 UTC] TEW AJAX - POST data: Array
(
    [action] => tew_delete_success_case
    [nonce] => 5f86f7b157
    [case_id] => 7432
)
[20-Oct-2025 14:30:15 UTC] TEW AJAX - Case ID recibido: 7432
[20-Oct-2025 14:30:15 UTC] TEW AJAX - Verificación de nonce: OK
[20-Oct-2025 14:30:15 UTC] TEW AJAX - Llamando a delete_success_case con ID: 7432
[20-Oct-2025 14:30:15 UTC] TEW Delete Case - Intentando eliminar caso: 7432
[20-Oct-2025 14:30:15 UTC] TEW Delete Case - Meta IS_SUCCESS_CASE para 7432: true
[20-Oct-2025 14:30:15 UTC] TEW Delete Case - Caso eliminado correctamente: 7432
[20-Oct-2025 14:30:15 UTC] TEW AJAX - Resultado de delete_success_case: TRUE
```

**Resultado:** JSON exitoso devuelto, caso eliminado, card desaparece del DOM.

---

**Sube los archivos y compárteme los logs para continuar.**
