**GUÍA RÁPIDA: Usar %bdcat_description% en Rank Math**

## ✅ EL PLACEHOLDER ES:
```
%bdcat_description%
```

## 📍 DÓNDE USARLO:

### Opción 1: Configuración Global (Recomendado)
```
1. WordPress Admin → Rank Math → Títulos y Meta
2. Click en pestaña "Taxonomías"
3. Buscar la sección de tu taxonomía BetterDocs:
   - "Doc Category" o
   - "Knowledge Base Category" o
   - "Docs Category"
4. En el campo "Descripción" escribir: %bdcat_description%
5. Guardar cambios
```

### Opción 2: Por Categoría Individual
```
1. Docs → Categorías (o BetterDocs → Categories)
2. Editar una categoría
3. Scroll hacia abajo hasta la meta box "Rank Math SEO"
4. En el campo "Descripción" escribir: %bdcat_description%
5. Actualizar
```

## 🔧 SI NO FUNCIONA:

### 1. Verificar que las metas están generadas:
```sql
-- Ejecutar en phpMyAdmin:
SELECT 
    t.name as categoria,
    LEFT(tm.meta_value, 100) as meta_generada
FROM wp_termmeta tm
INNER JOIN wp_terms t ON tm.term_id = t.term_id
WHERE tm.meta_key = '_rmf_betterdocs_meta'
ORDER BY t.name;
```

### 2. Limpiar cache de Rank Math:
```
1. Rank Math → Status & Tools
2. Tab "Tools"
3. Click en "Clear Rank Math Cache"
4. Después: WordPress Admin Bar → Purge All Cache (si usas cache plugin)
```

### 3. Verificar integración:
```php
// Añadir temporalmente a functions.php para debug:
add_action('admin_notices', function() {
    $vars = apply_filters('rank_math/vars/register_extra_replacements', []);
    if (isset($vars['bdcat_description'])) {
        echo '<div class="notice notice-success"><p>✅ Variable bdcat_description registrada</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>❌ Variable NO registrada</p></div>';
    }
});
```

## 📝 NOMBRES POSIBLES DE TAXONOMÍA BETTERDOCS:

Rank Math puede mostrar la taxonomía como:
- ✅ Doc Category
- ✅ Knowledge Base Category  
- ✅ Docs Category
- ✅ Documentation Category

**Para encontrar el nombre exacto:**
```
1. BetterDocs → Settings → General
2. Ver "Category Slug" - ese es el nombre interno
```

## 🎯 EJEMPLO COMPLETO:

Si quieres combinar con otros placeholders de Rank Math:

```
%bdcat_description% | Tutoriales y guías de %sitename%
```

O solo:
```
%bdcat_description%
```

## ⚠️ IMPORTANTE:

1. **Primero genera las metas** desde Docs → Categorías → [Editar] → Click "Generar Meta"
2. **Después configura Rank Math** con el placeholder %bdcat_description%
3. **Luego visita** la categoría en el frontend para ver el resultado

## 🔍 VERIFICAR QUE FUNCIONA:

Visita una categoría BetterDocs y ve el código fuente (Ctrl+U):
```html
<!-- Debería aparecer: -->
<meta name="description" content="Tu meta generada aquí...">
```

## 📧 SI SIGUE SIN FUNCIONAR:

Posibles causas:
1. Cache activo (limpiar cache de WordPress + Rank Math)
2. Otro plugin SEO activo (desactivar Yoast, All in One SEO, etc.)
3. Tema sobrescribiendo metas (revisar functions.php del tema)

---

**Última actualización:** 2025-11-19
**Plugin:** Replanta Meta Fill v1.1.0
**Compatible:** Rank Math 1.0.200+, BetterDocs 2.5+
