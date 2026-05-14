# Changelog

## [1.1.0] - 2025-11-18

### ✨ Added - Nueva Funcionalidad

**BetterDocs SEO - Meta Descripciones para Categorías**
- Campo personalizado de meta SEO para categorías de BetterDocs
- Variable `%bdcat_description%` para Rank Math
- Generación con IA específica para categorías de documentación
- Columna en admin con indicadores visuales (✅⚠️❌)
- Contador de caracteres en tiempo real
- Botones "Generar con IA" y "Regenerar"
- Auto-detección de taxonomías BetterDocs:
  - `doc_category` (BetterDocs v1.x)
  - `betterdocs_category` (BetterDocs v2.x)
  - `docs_category` (variante)
  - `knowledge_base` (BetterDocs Pro)
- Prompt especializado para categorías de documentación
- Separación clara: `term_description` (pública) vs `%bdcat_description%` (SEO)

### 📖 Documentation

- Sección completa de BetterDocs en README
- Ejemplos de configuración de Rank Math
- Guía de uso de la variable `%bdcat_description%`
- Tabla de indicadores de estado
- Ejemplos prácticos de meta vs descripción pública

---

## [1.0.0] - 2025-11-18

### 🎉 Lanzamiento Inicial

#### ✨ Características Principales

**Generación Inteligente con OpenAI**
- Integración completa con API de OpenAI (GPT-4o, GPT-4o Mini, GPT-3.5 Turbo)
- Crawling y análisis de contenido del post
- Generación contextual con título, contenido, categorías y tags
- Prompts personalizables con variables dinámicas
- Optimización automática de longitud (120-155 caracteres recomendados)
- Control de creatividad mediante temperature (0-1)

**Interfaz de Usuario**
- Columna "Meta" en lista de posts y páginas
- Indicadores visuales de estado:
  - ✅ Verde: Meta OK (longitud óptima)
  - ⚠️ Amarillo: Meta problemática (muy corta/larga)
  - ❌ Rojo: Sin meta descripción
- Botones AJAX para generar/regenerar sin recargar
- Preview inline de meta descripciones existentes
- Timestamp de generación
- Información de longitud en caracteres

**Generación Masiva**
- Página dedicada para generación en lote
- Detección automática de posts sin meta descripción
- Selección individual o masiva (select all)
- Procesamiento por lotes de 5 posts
- Barra de progreso en tiempo real
- Delays automáticos para evitar rate limiting
- Estado individual por cada post procesado

**Compatibilidad SEO Plugins**
- ✅ **Rank Math**: Integración nativa con `rank_math_description`
- ✅ **Yoast SEO**: Integración nativa con `_yoast_wpseo_metadesc`
- ✅ **All in One SEO**: Integración nativa con `_aioseo_description`
- ✅ **Sin plugin SEO**: Meta fields genéricos `_meta_description`
- Auto-detección de plugin activo
- Opción para forzar plugin específico

**Panel de Configuración**
- Settings page completa en admin
- Validación de API key con feedback visual
- Selección de modelo OpenAI
- Configuración de temperature
- Longitud máxima personalizable (100-320 caracteres)
- Editor de plantilla de prompt
- Toggle para auto-generación al publicar
- Estado del sistema con información detallada

**Sistema de Logging**
- Logs en base de datos (últimos 100 registros)
- Logs en error_log de PHP
- Niveles: info, success, error
- Timestamps con formato legible
- Información detallada de operaciones

**AJAX y Seguridad**
- 4 endpoints AJAX:
  - `rmf_generate_meta`: Generar meta individual
  - `rmf_check_status`: Verificar estado de meta
  - `rmf_bulk_generate`: Generación masiva
  - `rmf_validate_api_key`: Validar API key
- Nonces en todas las peticiones
- Verificación de permisos (`edit_posts`, `manage_options`)
- Sanitización de inputs
- Rate limiting incorporado

**Assets Frontend**
- CSS moderno con animaciones
- JavaScript modular y optimizado
- Notificaciones tipo toast
- Spinners y loading states
- Responsive design
- Iconos Dashicons integrados

#### 🎨 UI/UX

**Estilos**
- Diseño coherente con WordPress admin
- Colores semánticos (verde/amarillo/rojo)
- Animaciones suaves (spin, slide-in)
- Estados hover y disabled
- Feedback visual instantáneo

**JavaScript**
- jQuery-based
- Manejo de errores robusto
- Confirmaciones para acciones masivas
- Auto-recarga después de operaciones
- Notificaciones auto-dismissible (5s)

#### 🔧 Técnico

**Arquitectura**
- Patrón Singleton en todas las clases
- Separación de responsabilidades
- Autoload de clases
- Hooks de activación/desactivación
- Namespacing implícito con prefijos

**Clases Principales**
1. `Replanta_Meta_Fill`: Clase principal y orquestador
2. `Replanta_Meta_Fill_Content_Crawler`: Crawling y análisis
3. `Replanta_Meta_Fill_OpenAI_Handler`: API de OpenAI
4. `Replanta_Meta_Fill_Admin_Columns`: Columnas del admin
5. `Replanta_Meta_Fill_Ajax_Handler`: Endpoints AJAX
6. `Replanta_Meta_Fill_Admin_Settings`: Configuración

**Performance**
- Carga condicional de assets (solo en páginas relevantes)
- Procesamiento por lotes en generación masiva
- Delays para evitar rate limiting de OpenAI
- Cacheo de opciones
- Queries optimizadas

**Compatibilidad**
- WordPress 5.8+
- PHP 7.4+
- Todos los plugins SEO principales
- Gutenberg y Classic Editor
- Posts y Pages (extensible a CPTs)

#### 📦 Instalación

**Opciones por Defecto**
```php
[
    'openai_api_key' => '',
    'openai_model' => 'gpt-4o-mini',
    'max_length' => 155,
    'temperature' => 0.7,
    'auto_generate' => 0,
    'seo_plugin' => 'auto',
    'prompt_template' => '[template personalizable]'
]
```

**Activación**
- Crea opciones con valores por defecto
- Añade capacidad `manage_replanta_meta_fill` a admin
- Log de activación
- Merge de opciones existentes (no sobrescribe)

**Desactivación**
- Limpia trabajos programados
- Mantiene opciones (no borra datos)
- Log de desactivación

#### 🐛 Fixes Incluidos

- Manejo robusto de errores de API
- Validación de longitud de meta descripción
- Sanitización de output de OpenAI (eliminar comillas)
- Corte en última palabra completa si excede longitud
- Timeout handling en bulk generation
- Prevención de duplicados en logs

#### 📚 Documentación

- README.md completo con ejemplos
- CHANGELOG.md con historial detallado
- Inline comments en código
- PHPDoc en métodos públicos
- Descripciones de settings fields

---

## [Unreleased]

### 🚀 Próximas Características

- [ ] Soporte para Custom Post Types
- [ ] Traducción a múltiples idiomas
- [ ] Regeneración automática si meta es muy antigua
- [ ] A/B testing de meta descripciones
- [ ] Analytics de CTR por meta generada
- [ ] Integración con Google Search Console
- [ ] Preview de SERP (cómo se verá en Google)
- [ ] Sugerencias de mejora para metas existentes
- [ ] Export/Import de configuración
- [ ] API REST para integración externa

---

## Notas de Versión

### Convenciones de Versionado

Seguimos [Semantic Versioning](https://semver.org/):
- **MAJOR**: Cambios incompatibles en API
- **MINOR**: Nuevas funcionalidades compatibles
- **PATCH**: Bug fixes compatibles

### Tipos de Cambios

- ✨ **Added**: Nuevas características
- 🔧 **Changed**: Cambios en funcionalidad existente
- ⚠️ **Deprecated**: Características obsoletas
- ❌ **Removed**: Características eliminadas
- 🐛 **Fixed**: Bug fixes
- 🔒 **Security**: Parches de seguridad
