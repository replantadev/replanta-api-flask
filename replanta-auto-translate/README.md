# Replanta Auto Translate

Plugin de WordPress para traduccion automatica de sitios con Polylang y Elementor. Utiliza las APIs de OpenAI y Google Translate para generar traducciones de alta calidad.

## Requisitos

- WordPress 5.8+
- PHP 7.4+
- Polylang (version gratuita o Pro)
- Al menos una API key: OpenAI o Google Cloud Translation

## Instalacion

1. Subir la carpeta `replanta-auto-translate` a `/wp-content/plugins/`
2. Activar el plugin desde el panel de WordPress
3. Ir a **Auto Translate > Ajustes** y configurar las API keys
4. Configurar los idiomas en Polylang (origen y destino)

## Configuracion

### API Keys

#### OpenAI
1. Crear cuenta en [OpenAI](https://platform.openai.com/)
2. Generar API key en [API Keys](https://platform.openai.com/api-keys)
3. Copiar la key en Ajustes > OpenAI API Key

#### Google Cloud Translation
1. Crear proyecto en [Google Cloud Console](https://console.cloud.google.com/)
2. Habilitar Cloud Translation API
3. Crear credenciales (API Key)
4. Copiar la key en Ajustes > Google Translate API Key

### Opciones

| Opcion | Descripcion |
|--------|-------------|
| Motor de Traduccion | OpenAI (mejor calidad) o Google (mas rapido) |
| Modelo OpenAI | GPT-4o-mini (economico) o GPT-4o (mayor calidad) |
| Traducir URLs | Genera slugs traducidos automaticamente |
| Traducir Meta SEO | Traduce title y description de Yoast/RankMath |
| Paginas por Lote | Cuantas paginas procesar en cada peticion AJAX |
| Delay entre Peticiones | Milisegundos de espera para evitar rate limits |

## Uso

### Traduccion Individual

1. Ir a **Auto Translate > Traducir Sitio**
2. Localizar el post/pagina en la tabla
3. Click en boton "Traducir"
4. Esperar a que complete

### Traduccion Masiva

1. Ir a **Auto Translate > Traducir Sitio**
2. Click en "Traducir Todas las Paginas" o "Traducir Todos los Posts"
3. Confirmar la accion
4. Esperar a que complete (ver barra de progreso)

### Traduccion Selectiva

1. Marcar los posts/paginas deseados con los checkboxes
2. Click en "Traducir Seleccionadas"
3. Esperar a que complete

### Traduccion de Menus

1. Click en "Traducir Menus"
2. Se crearan menus traducidos y vinculados con Polylang

## Compatibilidad con Elementor

El plugin parsea completamente el JSON de `_elementor_data` y traduce:

- Headings
- Text Editor
- Buttons
- Image Box / Icon Box
- Tabs / Accordion / Toggle
- Testimonials
- Price Tables
- Forms (labels y placeholders)
- Y muchos mas widgets

Los shortcodes y HTML estructural se preservan sin modificar.

## Widgets Soportados

### Elementor Core
- heading
- text-editor
- button
- image-box
- icon-box
- icon-list
- counter
- progress
- testimonial
- tabs
- accordion
- toggle
- alert
- star-rating
- image-carousel

### Elementor Pro
- slides
- price-table
- price-list
- flip-box
- call-to-action
- animated-headline
- blockquote
- form

### Theme Builder
- page-title
- post-title
- post-excerpt
- search-form

## Filtros Disponibles

### Agregar campos traducibles personalizados

```php
add_filter('replanta_elementor_translatable_fields', function($fields) {
    // Agregar soporte para widget personalizado
    $fields['my-custom-widget'] = ['title', 'description', 'button_text'];
    return $fields;
});
```

### Agregar meta keys traducibles

```php
add_filter('replanta_translatable_meta_keys', function($keys) {
    $keys[] = 'my_custom_field';
    $keys[] = 'another_field';
    return $keys;
});
```

## API AJAX

El plugin expone varios endpoints AJAX para integraciones:

| Action | Descripcion |
|--------|-------------|
| replanta_translate_single | Traducir un post individual |
| replanta_translate_bulk_start | Iniciar proceso masivo |
| replanta_translate_bulk_process | Procesar siguiente lote |
| replanta_translate_bulk_cancel | Cancelar proceso |
| replanta_translate_bulk_status | Obtener estado |
| replanta_translate_menus | Traducir menus |
| replanta_test_connection | Probar conexion API |
| replanta_get_post_summary | Obtener resumen de post |
| replanta_get_stats | Obtener estadisticas |

## Logs

El plugin mantiene un historial de traducciones en la tabla `wp_replanta_translate_log` con:

- ID del post original
- ID del post traducido
- Idiomas origen/destino
- Motor usado
- Estado (completed/error)
- Mensaje de error (si aplica)
- Timestamps

Ver historial en **Auto Translate > Historial**

## Estimacion de Costos

### OpenAI
- GPT-4o-mini: ~$0.01 por 1000 tokens (~4000 caracteres)
- GPT-4o: ~$0.03 por 1000 tokens

### Google Translate
- ~$20 por millon de caracteres

Un sitio tipico con 20 paginas de Elementor puede costar entre $0.50 y $2.00.

## Troubleshooting

### "Post origen no tiene idioma asignado"
- Verificar que el post tenga idioma asignado en Polylang
- Ir a editar el post y asegurar que aparece la seleccion de idioma

### "Ya existe una traduccion para este idioma"
- El post ya tiene traduccion vinculada
- Verificar en Polylang el estado de traducciones

### "Error de conexion con OpenAI"
- Verificar API key
- Verificar credito disponible en cuenta OpenAI
- Probar con boton "Probar OpenAI" en ajustes

### "Rate limit exceeded"
- Aumentar delay entre peticiones en ajustes
- Reducir paginas por lote
- Esperar unos minutos y reintentar

### Elementor no muestra contenido traducido
- Abrir el post traducido con Elementor
- Si es necesario, regenerar CSS: Elementor > Tools > Regenerate CSS

## Changelog

### 1.0.0
- Version inicial
- Soporte completo para Elementor
- Integracion con Polylang
- Motores OpenAI y Google Translate
- Traduccion de menus
- Traduccion de meta SEO (Yoast/RankMath)
- Panel de administracion completo
- Sistema de logs

## Licencia

GPL v2 or later

## Autor

Replanta - https://replanta.net
