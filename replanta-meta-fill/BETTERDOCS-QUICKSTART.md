# BetterDocs SEO - Guía Rápida

**Meta descripciones SEO personalizadas para categorías de BetterDocs**

---

## 🎯 Problema Resuelto

### Antes
```
❌ term_description se usa para TODO (web + SEO)
❌ Si la descripción es larga, Google la trunca mal
❌ No hay control sobre qué muestra Google
```

### Ahora ✅
```
✅ term_description → Descripción PÚBLICA (para usuarios en tu web)
✅ %bdcat_description% → Meta SEO (solo para Google/buscadores)
✅ Control total sobre longitud y contenido SEO
✅ Generación automática con IA
```

---

## ⚡ Configuración en 3 Pasos

### 1️⃣ Configurar Rank Math

```
Rank Math > Títulos y Metas > Categorías de Documentación
Meta Descripción: %bdcat_description%
```

**Opciones:**

| Opción | Código | Uso |
|--------|--------|-----|
| Solo meta personalizada | `%bdcat_description%` | Recomendado |
| Con fallback | `%bdcat_description% %sep% %term_description%` | Si quieres backup |
| Con sitename | `%bdcat_description% %sep% %sitename%` | Añade marca |

### 2️⃣ Editar Categoría

```
Documentación > Categorías > Editar cualquier categoría
```

Verás el campo: **"Meta Description SEO"**

### 3️⃣ Generar Meta

**Opción A: Manual**
- Escribe la meta (120-155 caracteres)
- Guarda

**Opción B: Con IA** 🤖
- Clic en **"Generar con IA"**
- Espera 3-5 segundos
- ¡Listo! Se guarda automáticamente

---

## 🎨 Indicadores Visuales

### En Lista de Categorías

| Icono | Longitud | Estado | Acción |
|-------|----------|--------|--------|
| ✅ | 120-160 chars | Óptimo | Ninguna |
| ⚠️ | <120 chars | Muy corta | Ampliar |
| ⚠️ | >160 chars | Muy larga | Acortar |
| ❌ | 0 chars | Sin meta | Añadir |

### En Formulario de Edición

```
┌──────────────────────────────────────┐
│ Meta Description SEO                 │
├──────────────────────────────────────┤
│ [Textarea con contenido]             │
│                                      │
│ 145 caracteres  ✅ Longitud óptima   │
│                                      │
│ [🤖 Generar con IA] [🔄 Regenerar]  │
└──────────────────────────────────────┘
```

---

## 💡 Ejemplo Práctico

### Categoría: "WordPress Básico"

#### Descripción Pública (term_description)
**Se muestra en:** Tu web, página de archivo de la categoría

```
Esta categoría contiene todos los tutoriales básicos 
para empezar con WordPress. Aprenderás desde la instalación 
hasta la configuración inicial de tu sitio web. 

Incluye guías paso a paso para:
- Instalar WordPress
- Configurar permalinks
- Crear tus primeras páginas
- Gestionar usuarios

Perfecto para principiantes que nunca han usado WordPress.
```

#### Meta SEO (%bdcat_description%)
**Se muestra en:** Google, motores de búsqueda

```
Aprende WordPress desde cero con tutoriales paso a paso. 
Instalación, configuración y primeros pasos explicados 
de forma simple y práctica.
```

### Resultado en Google

```
┌─────────────────────────────────────────────────┐
│ WordPress Básico - Documentación Replanta       │
│ https://replanta.net › docs › wordpress-basico  │
│                                                 │
│ Aprende WordPress desde cero con tutoriales    │
│ paso a paso. Instalación, configuración y      │
│ primeros pasos explicados de forma simple...   │
└─────────────────────────────────────────────────┘
```

---

## 🤖 Generación con IA

### Cómo Funciona

1. **Detecta contexto automáticamente:**
   - Nombre de la categoría
   - Descripción existente
   - 5 documentos de ejemplo de esa categoría

2. **Genera prompt específico:**
   ```
   "Genera meta SEO (max 155 chars) para CATEGORÍA de docs.
   Nombre: WordPress Básico
   Documentos: 
   - Instalar WordPress en local
   - Configurar WordPress después de instalar
   - Primeros pasos con WordPress
   ..."
   ```

3. **Llama a OpenAI** con el modelo configurado (GPT-4o Mini por defecto)

4. **Guarda automáticamente** la meta generada

### Ventajas vs Manual

| Aspecto | Manual | Con IA |
|---------|--------|--------|
| Tiempo | 5-10 min | 3-5 seg |
| Keywords | Depende de ti | Optimizado |
| Longitud | Puede exceder | Siempre óptima |
| Persuasión | Variable | Optimizada |
| Contexto | Lo que recuerdes | Auto-detectado |

---

## 📊 Mejores Prácticas SEO

### Longitud Ideal

```
Móvil:    120-155 caracteres ✅ RECOMENDADO
Desktop:  Hasta 160 caracteres
Máximo:   320 caracteres (se trunca igual)
```

### Estructura Efectiva

```
[Acción] + [Beneficio] + [Keywords] + [CTA opcional]
```

**Ejemplos:**

❌ **Malo:**
```
"Esta es la categoría de plugins de WordPress"
(Muy genérica, sin beneficio, no persuade)
```

✅ **Bueno:**
```
"Descubre los mejores plugins WordPress para SEO, 
velocidad y seguridad. Guías de instalación y 
configuración paso a paso."
(Acción + beneficio + keywords + promesa)
```

### Palabras Clave

✅ **Hacer:**
- Incluir keywords principales de la categoría
- Usar variaciones naturales
- Mencionar qué tipo de contenido contiene

❌ **No hacer:**
- Keyword stuffing (repetir keywords)
- Usar todas mayúsculas
- Caracteres especiales innecesarios

---

## 🔧 Troubleshooting

### La variable no aparece en Rank Math

**Solución:**
```
1. Rank Math > Estado > Herramientas
2. Clic en "Limpiar Caché"
3. Refresca la página de configuración
4. La variable %bdcat_description% debe aparecer
```

### El botón "Generar con IA" no aparece

**Causa:** OpenAI no configurado

**Solución:**
```
1. Meta Fill > Configuración
2. Añade tu OpenAI API Key
3. Clic en "Validar Key"
4. Guarda configuración
5. Refresca la página de categorías
```

### La meta generada es muy corta/larga

**Solución:**
```
1. Meta Fill > Configuración
2. Ajusta "Longitud máxima" a 155
3. Guarda cambios
4. Regenera la meta de la categoría
```

### No detecta mi taxonomía

**Auto-detecta:**
- `doc_category` (BetterDocs v1.x)
- `betterdocs_category` (BetterDocs v2.x)
- `docs_category` (variante)
- `knowledge_base` (BetterDocs Pro)

**Si tu taxonomía es diferente:**
Contacta para añadir soporte.

---

## 📈 Checklist Pre-Lanzamiento

- [ ] Plugin Replanta Meta Fill activo
- [ ] OpenAI API Key configurada y validada
- [ ] Rank Math configurado con `%bdcat_description%`
- [ ] Todas las categorías principales tienen meta SEO
- [ ] Longitudes entre 120-160 caracteres
- [ ] Keywords principales incluidas
- [ ] Probado en Google Search Console
- [ ] Caché de Rank Math limpiada

---

## 🎯 Diferencia Clave

### term_description (Descripción Pública)

**Propósito:** Explicar al USUARIO qué encontrará en esta categoría

**Longitud:** La que necesites (2-3 párrafos está bien)

**Tono:** Natural, descriptivo, educativo

**Se muestra en:** 
- Tu sitio web
- Página de archivo de la categoría
- Widget de categorías (si lo usas)

### %bdcat_description% (Meta SEO)

**Propósito:** Convencer al BUSCADOR de hacer clic

**Longitud:** 120-155 caracteres (estricto)

**Tono:** Persuasivo, directo, orientado a acción

**Se muestra en:**
- Resultados de Google
- Resultados de Bing
- Otros buscadores
- Al compartir en redes (si no hay OG)

---

**¿Preguntas?** [replanta.net/soporte](https://replanta.net/soporte)
