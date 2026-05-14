# Replanta Meta Fill

**Generación automática de meta descripciones usando OpenAI**

Plugin de WordPress que utiliza la API de OpenAI para generar meta descripciones SEO optimizadas automáticamente. Compatible con RankMath, Yoast SEO y All in One SEO.

**✨ NUEVO:** Soporte para categorías de BetterDocs con variable personalizada `%bdcat_description%` para Rank Math.

---

## 🚀 Características

### ✨ Generación Inteligente
- **Crawling de contenido**: Analiza el contenido del post antes de generar
- **Contexto completo**: Incluye título, contenido, categorías y tags
- **Optimización de longitud**: Respeta límites SEO (120-155 caracteres recomendados)
- **Prompts personalizables**: Template de prompt totalmente editable
- **🆕 BetterDocs**: Generación específica para categorías de documentación

### 🎯 Interfaz Intuitiva
- **Columna en admin**: Estado visual de meta descripciones en lista de posts/páginas
- **🆕 Columna BetterDocs**: Estado visual en categorías de documentación
- **Indicadores de estado**: 
  - ✅ Verde: Meta OK (longitud óptima)
  - ⚠️ Amarillo: Meta muy corta o muy larga
  - ❌ Rojo: Sin meta descripción
- **Botones AJAX**: Genera/regenera sin recargar página
- **Preview inline**: Vista previa de meta descripción existente

### 🔄 Generación Masiva
- **Bulk generation**: Genera múltiples posts a la vez
- **Filtrado inteligente**: Detecta automáticamente posts sin meta
- **Procesamiento por lotes**: Evita timeouts y rate limiting
- **Barra de progreso**: Seguimiento en tiempo real
- **Control total**: Selección individual o masiva

### 🔌 Compatibilidad SEO
- ✅ **Rank Math**: Integración nativa + variable `%bdcat_description%` para BetterDocs
- ✅ **Yoast SEO**: Integración nativa
- ✅ **All in One SEO**: Integración nativa
- ✅ **Sin plugin SEO**: Usa meta fields genéricos
- ✅ **BetterDocs**: Meta SEO separada para categorías de documentación

### ⚙️ Configuración Avanzada
- **Modelos OpenAI**: GPT-4o, GPT-4o Mini, GPT-3.5 Turbo
- **Temperature ajustable**: Control de creatividad (0-1)
- **Validación de API key**: Verifica conexión con OpenAI
- **Auto-generación**: Opcional al publicar posts nuevos
- **Sistema de logging**: Logs detallados de operaciones

---

## 📋 Requisitos

- WordPress 5.8 o superior
- PHP 7.4 o superior
- API Key de OpenAI ([obtener aquí](https://platform.openai.com/api-keys))
- Plugin SEO (opcional pero recomendado):
  - Rank Math
  - Yoast SEO
  - All in One SEO

---

## 🔧 Instalación

1. **Subir plugin**:
   - Sube la carpeta `replanta-meta-fill` a `/wp-content/plugins/`
   - O instala desde el zip en WordPress admin

2. **Activar**:
   - Activa el plugin desde el menú "Plugins" en WordPress

3. **Configurar OpenAI**:
   - Ve a **Meta Fill > Configuración**
   - Introduce tu API Key de OpenAI
   - Haz clic en "Validar Key" para verificar
   - Guarda la configuración

4. **¡Listo!**:
   - Ve a **Entradas** o **Páginas**
   - Verás la nueva columna "Meta"
   - Haz clic en "Generar" para crear meta descripciones

---

## 💡 Uso

### Generación Individual

1. Ve a **Entradas** o **Páginas**
2. Busca la columna **Meta**
3. Para posts sin meta, haz clic en **Generar**
4. Para regenerar, haz clic en **Regenerar**
5. La meta se guardará automáticamente en tu plugin SEO

### Generación Masiva

1. Ve a **Meta Fill > Generación Masiva**
2. Se listarán todos los posts sin meta descripción
3. Selecciona los posts que quieras procesar
4. Haz clic en **Generar Meta Descripciones Seleccionadas**
5. Observa la barra de progreso
6. Los posts se procesarán en lotes para evitar timeouts

### Auto-generación al Publicar

1. Ve a **Meta Fill > Configuración**
2. Activa **Generación automática**
3. Cada vez que publiques un post sin meta, se generará automáticamente

### 🆕 BetterDocs - Categorías de Documentación

**Problema resuelto:** Separación de meta SEO de la descripción pública en categorías de documentación.

#### Configuración Inicial

1. **Configurar Rank Math**:
   - Ve a **Rank Math > Títulos y Metas > Categorías de Documentación**
   - En **Meta Descripción**, usa: `%bdcat_description%`
   - O combinada: `%bdcat_description% %sep% %sitename%`

2. **Editar Categorías**:
   - Ve a **Documentación > Categorías**
   - Edita cualquier categoría
   - Verás el campo **"Meta Description SEO"**

#### Generar Meta SEO

**Opción 1: Manual**
- Escribe la meta descripción en el campo (120-155 caracteres recomendados)
- Guarda la categoría

**Opción 2: Con IA** 🤖
- Haz clic en **"Generar con IA"**
- El plugin detecta automáticamente:
  - Nombre de la categoría
  - Descripción existente
  - 5 documentos de ejemplo
- Genera una meta específica para categorías de documentación
- Guarda automáticamente

**Opción 3: Regenerar**
- Si ya existe una meta, usa **"Regenerar"** para crear una nueva versión

#### Indicadores de Estado

En la columna **"Meta SEO"** verás:

| Estado | Significado | Acción |
|--------|-------------|--------|
| ✅ 145 chars | Longitud óptima (120-160) | Perfecto |
| ⚠️ 95 chars | Muy corta (<120) | Ampliar |
| ⚠️ 175 chars | Muy larga (>160) | Acortar |
| ❌ Sin meta | No configurada | Añadir |

#### Variable Rank Math: %bdcat_description%

**¿Para qué sirve?**
- Separa la **meta SEO** de la **descripción pública** del término
- `term_description` → Para usuarios en tu web
- `%bdcat_description%` → Solo para Google/SEO

**Ejemplo:**

```
Descripción pública (term_description):
"Esta categoría contiene todos los tutoriales básicos para empezar 
con WordPress. Aprenderás desde la instalación hasta la configuración 
inicial de tu sitio web. Perfecto para principiantes."

Meta SEO (%bdcat_description%):
"Aprende WordPress desde cero con tutoriales paso a paso. 
Instalación, configuración y primeros pasos explicados de forma simple."
```

**Resultado en Google:**
```
WordPress para Principiantes - Replanta
replanta.net › docs › wordpress-principiantes

Aprende WordPress desde cero con tutoriales paso a paso. 
Instalación, configuración y primeros pasos explicados de forma simple.
```

#### Taxonomías Soportadas

Auto-detecta:
- `doc_category` (BetterDocs v1.x)
- `betterdocs_category` (BetterDocs v2.x)  
- `docs_category` (variante)
- `knowledge_base` (BetterDocs Pro)

---

## ⚙️ Configuración

### OpenAI

- **API Key**: Tu clave de OpenAI (empieza con `sk-`)
- **Modelo**: 
  - `gpt-4o-mini` (recomendado): Mejor balance calidad/precio
  - `gpt-4o`: Máxima calidad
  - `gpt-3.5-turbo`: Más económico
- **Creatividad**: 0 (conservador) a 1 (creativo) - recomendado: 0.7

### Generación

- **Longitud máxima**: 120-155 caracteres (móvil) o hasta 320 (desktop)
- **Plantilla de prompt**: Personaliza el prompt enviado a OpenAI
  - Variables disponibles: `{title}`, `{content}`, `{max_length}`
- **Generación automática**: Auto-generar al publicar posts sin meta

### Compatibilidad SEO

- **Plugin SEO**: Auto-detectar o forzar uno específico
  - Auto-detectar (recomendado)
  - Rank Math
  - Yoast SEO
  - All in One SEO
  - Ninguno (meta fields genéricos)

---

## 🎨 Interfaz

### Columna en Lista de Posts

La columna **Meta** muestra:

✅ **Post con meta OK**:
```
✅ 145 chars (OK)
Descripción optimizada para SEO...
Generada hace 2 horas
[Regenerar]
```

⚠️ **Post con meta problemática**:
```
⚠️ 95 chars (Corta)
Meta muy corta para SEO...
[Regenerar]
```

❌ **Post sin meta**:
```
❌ Sin meta
[Generar]
```

---

## 🔐 Seguridad

- ✅ Nonces en todas las peticiones AJAX
- ✅ Verificación de permisos (`edit_posts`, `manage_options`)
- ✅ Sanitización de inputs
- ✅ API key almacenada de forma segura
- ✅ Rate limiting para evitar abusos

---

## 📊 Logging

El plugin mantiene un registro de operaciones:

- Generaciones exitosas
- Errores de API
- Validaciones de API key
- Activación/desactivación

Los logs se guardan en la base de datos (últimos 100) y en error_log de PHP.

---

## 🐛 Troubleshooting

### "Error de conexión con OpenAI"
- Verifica tu API key en Configuración
- Comprueba que tu servidor permite conexiones HTTPS salientes
- Revisa los logs de PHP

### "API key rechazada por OpenAI"
- Verifica que la key es correcta (empieza con `sk-`)
- Comprueba que tienes créditos disponibles en OpenAI
- Asegúrate de que la key no ha expirado

### "Timeout al generar múltiples posts"
- Reduce el número de posts seleccionados
- El plugin procesa en lotes de 5 con delays
- Aumenta el `max_execution_time` de PHP si es necesario

### "Meta descripción no se guarda"
- Verifica que tu plugin SEO está activo
- Comprueba que tienes permisos de edición
- Revisa el plugin SEO detectado en Estado del Sistema

---

## 🛠️ Desarrollo

### Estructura de Archivos

```
replanta-meta-fill/
├── replanta-meta-fill.php          # Archivo principal
├── includes/
│   ├── class-content-crawler.php   # Crawling y análisis de contenido
│   ├── class-openai-handler.php    # Gestión API OpenAI
│   ├── class-admin-columns.php     # Columnas en admin
│   ├── class-ajax-handler.php      # Endpoints AJAX
│   └── class-admin-settings.php    # Configuración
├── assets/
│   ├── css/
│   │   └── admin.css               # Estilos del admin
│   └── js/
│       └── admin.js                # JavaScript del admin
├── README.md
└── CHANGELOG.md
```

### Hooks Disponibles

```php
// Modificar el prompt antes de enviar a OpenAI
add_filter('rmf_prompt_template', function($prompt, $post_data) {
    // Personalizar prompt
    return $prompt;
}, 10, 2);

// Acción después de generar meta
add_action('rmf_meta_generated', function($post_id, $meta_description) {
    // Hacer algo después de generar
}, 10, 2);
```

---

## 📝 Changelog

Ver [CHANGELOG.md](CHANGELOG.md) para historial completo de versiones.

---

## 📄 Licencia

GPL v2 or later

---

## 👨‍💻 Autor

**Replanta**  
[https://replanta.net](https://replanta.net)

---

## 🙏 Soporte

Para reportar bugs o solicitar features:
- Abre un issue en GitHub
- Contacta en support@replanta.net

---

**¿Te gusta el plugin? ⭐ Dale una estrella en GitHub!**
