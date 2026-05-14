# Fix de Hreflang para BetterDocs

**Versión:** 1.2.0  
**Fecha:** 2026-04-28  
**Problema Resuelto:** Ahrefs reportaba conflictos de hreflang en páginas de BetterDocs

---

## ¿Cuál Era el Problema?

Ahrefs reportaba estos errores en las URLs de BetterDocs como `/doc/` y `/en/doc-2/`:

- ❌ "More than one page is linked for the same language"
- ❌ "Some pages don't include hreflang links to all the other pages of the group"

### Causa Raíz

BetterDocs crea posts independientes por idioma que **NO están vinculados a través de Polylang** como traducciones normales. El plugin original de hreflang solo buscaba traducciones en Polylang, por lo que:

1. No encontraba `/en/doc-2/` como traducción de `/doc/`
2. Generaba hreflang incorrecto o duplicado
3. Ahrefs detectaba conflictos de grupo

---

## ¿Qué Se Arregló?

Se agregó un **detector automático de BetterDocs** que busca páginas relacionadas cuando Polylang no encuentra traducciones.

### Estrategia de Detección

Cuando el plugin procesa una página de BetterDocs:

1. **Intenta Polylang primero** (`pll_get_post_translations`)
   - Si encuentra 2+ traducciones → las usa

2. **Si Polylang falla, busca por patrón de slug:**
   - Extrae el slug base (ej: `doc` de `doc-2`)
   - Busca posts con ese slug en cada idioma
   - Verifica que Polylang asigne el idioma correcto a cada post
   - Descarta posts marcados como `noindex`

3. **Genera hreflang únicos y correctos**
   - Un `hreflang` por idioma
   - Un `x-default` apuntando al idioma por defecto

---

## Cómo Verificar que Funciona

### 1️⃣ Acceso al Diagnóstico

Ve a tu sitio e ingresa esta URL en el navegador:

```
https://tu-sitio.com/wp-admin/admin-ajax.php?action=rsh_check
```

Debes estar logueado como admin.

### 2️⃣ Busca la Sección "BetterDocs Pages"

```
--- Test: BetterDocs Pages (si existen) ---

  Docs: Título de tu página (ID: 123)
    [es] https://replanta.net/doc/
    [en] https://replanta.net/en/doc-2/
```

✅ Si ves esto → **está funcionando correctamente**
❌ Si dice "No BetterDocs pages found" → BetterDocs no está activo o usa otro post_type

### 3️⃣ Verifica el Sitemap XML

1. Descarga: https://tu-sitio.com/sitemap_index.xml
2. Busca las URLs `/doc/` y `/en/doc-2/`
3. Verifica que cada una tiene hreflang así:

```xml
<url>
    <loc>https://replanta.net/doc/</loc>
    <xhtml:link rel="alternate" hreflang="es" href="https://replanta.net/doc/" />
    <xhtml:link rel="alternate" hreflang="en" href="https://replanta.net/en/doc-2/" />
    <xhtml:link rel="alternate" hreflang="x-default" href="https://replanta.net/doc/" />
</url>

<url>
    <loc>https://replanta.net/en/doc-2/</loc>
    <xhtml:link rel="alternate" hreflang="es" href="https://replanta.net/doc/" />
    <xhtml:link rel="alternate" hreflang="en" href="https://replanta.net/en/doc-2/" />
    <xhtml:link rel="alternate" hreflang="x-default" href="https://replanta.net/doc/" />
</url>
```

✅ **Correcto** - ambas URLs tienen hreflang a AMBAS versiones (es + en)
❌ **Incorrecto** - alguna URL falta hreflang a la otra versión

---

## Próximos Pasos en Ahrefs

1. **Vuelve a rastrear** tu sitio en Ahrefs
2. **Espera 48-72 horas** a que Google reindexe
3. Los errores de hreflang deberían desaparecer

### Si Ahrefs Aún Reporta Errores

Verifica:

- [ ] ¿Las páginas realmente están marcadas con el idioma correcto en Polylang?
  - Ve a **Docs** → edita cada página → revisa "Idioma" en la caja de la derecha
  
- [ ] ¿Los slugs de ambas versiones son consistentes?
  - ej: `/doc/` (ES) y `/en/doc-2/` (EN)
  - Si los slugs son muy diferentes, el detector no las vinculará
  
- [ ] ¿Hay más de 2 idiomas configurados?
  - El plugin generará hreflang para TODOS los idiomas activos
  - Asegúrate de que cada página tenga una versión por idioma

---

## Archivos Modificados

- `replanta-sitemap-hreflang.php` (v1.2.0)
  - ✨ Agregado método `get_betterdocs_translations()`
  - ✨ Mejorado `capture_translations()` con fallback a BetterDocs
  - ✨ Extendido diagnóstico AJAX para mostrar BetterDocs

---

## Requisitos

- ✅ Polylang activo
- ✅ Rank Math activo
- ✅ BetterDocs con posts en múltiples idiomas marcados en Polylang
- ✅ PHP 7.4+

---

## Soporte

Si los errores persisten después de 72 horas:

1. Ejecuta el diagnóstico: `/wp-admin/admin-ajax.php?action=rsh_check`
2. Copia la salida completa
3. Revisa que Polylang esté asignando idioma correcto a cada doc
4. Considera si los slugs son demasiado diferentes para ser detectados automáticamente
