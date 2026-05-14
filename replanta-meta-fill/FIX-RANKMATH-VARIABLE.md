# 🔧 FIX: Variable %bdcat_description% no aparece en lista Rank Math

## ✅ CAMBIOS IMPLEMENTADOS

### Archivo modificado: `class-betterdocs-seo.php`

1. **Prioridad de hooks aumentada** (línea 29-30)
2. **Formato de registro corregido** (líneas 244-258)
3. **Soporte para admin mejorado** (líneas 264-309)

## 📦 DEPLOYMENT

### Paso 1: Subir archivo actualizado
```
Archivo local:
c:\Users\programacion2\Local Sites\repos\replanta-meta-fill\includes\class-betterdocs-seo.php

Subir a:
/wp-content/plugins/replanta-meta-fill/includes/class-betterdocs-seo.php
```

### Paso 2: Limpiar todos los caches
```
1. Rank Math → Status & Tools → Clear Rank Math Cache
2. Si usas cache plugin: Purge All Cache
3. Navegador: Ctrl + Shift + R (hard reload)
```

### Paso 3: Verificar registro (IMPORTANTE)

Sube el plugin de debug para verificar:

```
Archivo: debug-rankmath-vars.php
Subir a: /wp-content/plugins/debug-rankmath-vars.php

Activar: WordPress Admin → Plugins → Activar "Debug Rank Math Variables"
```

Verás un aviso grande en el admin que te dirá:
- ✅ Si la variable está registrada
- ❌ Si hay algún problema

### Paso 4: Usar el placeholder

Una vez verificado que aparece ✅ ENCONTRADA:

```
Rank Math → Títulos y Meta → Taxonomías → Doc Category
Campo "Descripción": %bdcat_description%
```

O en el editor de categorías:
```
1. Click en botón { } Variables
2. Buscar "BetterDocs Category Description"
3. Click para insertar
```

## 🐛 SI SIGUE SIN FUNCIONAR

### Verificación SQL directa:

```sql
-- Ver si las metas están guardadas
SELECT 
    t.term_id,
    t.name,
    tm.meta_value
FROM wp_terms t
INNER JOIN wp_termmeta tm ON t.term_id = tm.term_id
WHERE tm.meta_key = '_rmf_betterdocs_meta'
LIMIT 5;
```

Si retorna vacío, las metas no se generaron. Genera una desde:
```
Docs → Categorías → [Editar] → Click "Generar Meta"
```

### Verificación de hooks:

Añade temporalmente a `functions.php`:

```php
// Debug: Ver si el hook se ejecuta
add_action('init', function() {
    $vars = apply_filters('rank_math/vars/register_extra_replacements', []);
    error_log('Variables Rank Math: ' . print_r(array_keys($vars), true));
}, 999);
```

Revisa `debug.log` y busca si aparece `bdcat_description` en el array.

### Si Rank Math no detecta la variable:

Puede ser versión incompatible. Verifica:

```
Rank Math → About
Versión mínima requerida: 1.0.200+
```

Si tienes versión antigua, actualiza Rank Math.

## 📝 QUÉ SE ARREGLÓ

### Problema 1: Prioridad baja
**Antes:**
```php
add_filter('rank_math/vars/register_extra_replacements', [$this, 'register_rankmath_variable']);
```

**Después:**
```php
add_filter('rank_math/vars/register_extra_replacements', [$this, 'register_rankmath_variable'], 20);
```

Rank Math ejecuta sus propios hooks en prioridad 10. Necesitamos prioridad 20 para asegurar que se registre después.

### Problema 2: Formato incorrecto
**Antes:**
```php
'description' => __('Meta descripción SEO...'),
```

**Después:**
```php
'description' => esc_html__('Meta descripción generada...'),
```

Rank Math espera strings escaped con `esc_html__()`.

### Problema 3: No funcionaba en admin
**Antes:**
Solo checaba `is_tax()` (solo frontend)

**Después:**
```php
// Detecta contexto: frontend, admin edit, o admin preview
if (is_tax($this->taxonomies)) {
    // Frontend
} elseif (is_admin() && isset($_GET['tag_ID'])) {
    // Admin edit
} elseif (is_admin()) {
    // Admin preview - retornar ejemplo
}
```

## ✅ CHECKLIST POST-DEPLOYMENT

```
[ ] Archivo class-betterdocs-seo.php subido
[ ] Cache limpiado (Rank Math + WordPress)
[ ] Plugin debug-rankmath-vars activado
[ ] Verificado: ✅ Variable registrada
[ ] Plugin debug-rankmath-vars desactivado y eliminado
[ ] Configurado %bdcat_description% en Rank Math
[ ] Testeado en una categoría del frontend
[ ] Verificado meta tag en código fuente (Ctrl+U)
```

## 🎯 RESULTADO ESPERADO

Después del deployment:

1. El plugin debug mostrará: **✅ ENCONTRADA: bdcat_description**
2. En Rank Math → Títulos y Meta → Taxonomías verás el placeholder disponible
3. Al hacer click en **{ } Variables** aparecerá "BetterDocs Category Description"
4. En el frontend verás: `<meta name="description" content="Tu meta generada...">`

---

**Última actualización:** 2025-11-19  
**Versión:** 1.1.1 - Fix Rank Math variable registration
