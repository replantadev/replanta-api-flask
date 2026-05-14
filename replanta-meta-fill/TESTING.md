# 🧪 Testing Guide - Replanta Meta Fill

## 🎯 Plan de Pruebas

### Test 1: Instalación y Activación

**Objetivo**: Verificar que el plugin se activa correctamente

**Pasos**:
1. Subir plugin a `/wp-content/plugins/`
2. Ir a **Plugins** en WordPress admin
3. Buscar **Replanta Meta Fill**
4. Hacer clic en **Activar**

**Resultado esperado**:
- ✅ Plugin se activa sin errores
- ✅ Aparece menú "Meta Fill" en sidebar
- ✅ No hay errores en PHP error log

---

### Test 2: Configuración de API Key

**Objetivo**: Verificar que la configuración funciona

**Pasos**:
1. Ir a **Meta Fill > Configuración**
2. Introducir API key de OpenAI (válida)
3. Hacer clic en **Validar Key**
4. Guardar configuración

**Resultado esperado**:
- ✅ Mensaje "✅ API key válida"
- ✅ Configuración se guarda correctamente
- ✅ Estado del sistema muestra "✅ Configurado"

**Test negativo**:
- Probar con API key inválida
- ✅ Debe mostrar "❌ API key rechazada por OpenAI"

---

### Test 3: Columna en Admin

**Objetivo**: Verificar que la columna aparece en posts

**Pasos**:
1. Ir a **Entradas**
2. Verificar que hay una columna "Meta"

**Resultado esperado**:
- ✅ Columna "Meta" visible
- ✅ Posts sin meta muestran "❌ Sin meta" + botón "Generar"
- ✅ Posts con meta muestran estado (verde/amarillo) + preview

---

### Test 4: Generación Individual

**Objetivo**: Generar meta descripción para un post

**Preparación**:
1. Crear un post de prueba con contenido
2. **NO** añadir meta descripción manualmente
3. Publicar el post

**Pasos**:
1. Ir a **Entradas**
2. Buscar el post de prueba en la lista
3. En columna "Meta", hacer clic en **Generar**
4. Esperar respuesta (5-10 segundos)

**Resultado esperado**:
- ✅ Botón cambia a "Generando..." con spinner
- ✅ Notificación de éxito aparece
- ✅ Página se recarga automáticamente
- ✅ Columna ahora muestra "✅ XX chars (OK)" + preview
- ✅ Meta descripción se guardó en plugin SEO

**Verificar en**:
- Rank Math: Meta box del post, campo "Description"
- Yoast: Meta box del post, campo "Meta description"
- Base de datos: `SELECT * FROM wp_postmeta WHERE post_id = X AND meta_key LIKE '%description%'`

---

### Test 5: Regeneración

**Objetivo**: Regenerar meta descripción existente

**Pasos**:
1. Ir a **Entradas**
2. Buscar post que ya tiene meta (del Test 4)
3. Hacer clic en **Regenerar**
4. Esperar respuesta

**Resultado esperado**:
- ✅ Nueva meta descripción generada (diferente a la anterior)
- ✅ Se sobrescribe la meta existente
- ✅ Timestamp actualizado

---

### Test 6: Detección de Plugin SEO

**Objetivo**: Verificar auto-detección de plugin SEO

**Pasos**:
1. Activar Rank Math (si está disponible)
2. Ir a **Meta Fill > Configuración**
3. Ver "Estado del Sistema" > "Plugin SEO detectado"

**Resultado esperado**:
- ✅ Con Rank Math: muestra "Rank Math"
- ✅ Con Yoast: muestra "Yoast SEO"
- ✅ Sin plugin SEO: muestra "Ninguno (se usarán meta fields genéricos)"

**Cambiar plugin SEO**:
1. Desactivar Rank Math
2. Activar Yoast SEO
3. Refrescar página de configuración
4. ✅ Debe detectar "Yoast SEO"

---

### Test 7: Generación Masiva

**Objetivo**: Generar múltiples metas a la vez

**Preparación**:
1. Crear 5 posts de prueba sin meta descripción
2. Publicarlos todos

**Pasos**:
1. Ir a **Meta Fill > Generación Masiva**
2. Verificar que los 5 posts aparecen en la lista
3. Seleccionar todos (checkbox superior)
4. Hacer clic en **Generar Meta Descripciones Seleccionadas**
5. Confirmar en el diálogo
6. Observar barra de progreso

**Resultado esperado**:
- ✅ Barra de progreso se muestra
- ✅ Posts se procesan en lotes de 5
- ✅ Progreso actualiza: "1 / 5", "2 / 5", etc.
- ✅ Estado individual por post: "✅ Generada" o "❌ Error"
- ✅ Al terminar: mensaje "✅ Generación completada: 5 posts procesados"
- ✅ Página se recarga automáticamente
- ✅ Todos los posts tienen meta descripción

---

### Test 8: Validación de Longitud

**Objetivo**: Verificar que respeta longitud máxima

**Pasos**:
1. Ir a **Meta Fill > Configuración**
2. Cambiar "Longitud máxima" a **120**
3. Guardar
4. Generar meta para un post nuevo

**Resultado esperado**:
- ✅ Meta generada tiene máximo 120 caracteres
- ✅ Si OpenAI excede, se corta en última palabra completa

**Probar con otros valores**:
- 100 chars: ✅ Meta muy corta pero funcional
- 155 chars: ✅ Longitud óptima SEO
- 200 chars: ✅ Meta más larga pero funcional

---

### Test 9: Personalización de Prompt

**Objetivo**: Verificar que el prompt personalizado funciona

**Pasos**:
1. Ir a **Meta Fill > Configuración**
2. Editar "Plantilla de Prompt":
```
Crea una meta descripción ÉPICA y EMOCIONANTE (máximo {max_length} caracteres) que use MAYÚSCULAS y emojis.

Título: {title}
Contenido: {content}

Meta descripción épica:
```
3. Guardar
4. Generar meta para un post nuevo

**Resultado esperado**:
- ✅ Meta generada refleja el estilo del prompt (más emocionante, con énfasis)
- ✅ Variables {title}, {content}, {max_length} se reemplazan correctamente

**Restaurar**:
- Volver a prompt por defecto después de la prueba

---

### Test 10: Auto-generación al Publicar

**Objetivo**: Verificar generación automática

**Pasos**:
1. Ir a **Meta Fill > Configuración**
2. Activar **Generación automática**
3. Guardar
4. Crear un post nuevo
5. **NO** añadir meta manualmente
6. Publicar el post
7. Esperar 5-10 segundos
8. Editar el post

**Resultado esperado**:
- ✅ Meta descripción generada automáticamente
- ✅ Aparece en plugin SEO (Rank Math/Yoast)
- ✅ Sin intervención manual

**Test negativo**:
- Publicar post que YA tiene meta manual
- ✅ NO debe sobrescribir la meta existente

---

### Test 11: Manejo de Errores

**Objetivo**: Verificar que los errores se manejan bien

**Test 11.1: API Key Inválida**
1. Cambiar API key a una inválida (`sk-FAKE123`)
2. Intentar generar meta

**Resultado esperado**:
- ✅ Error: "API key rechazada por OpenAI"
- ✅ No se genera meta
- ✅ No se produce fatal error en PHP

**Test 11.2: Sin Internet**
1. Desconectar servidor de internet (si es posible)
2. Intentar generar meta

**Resultado esperado**:
- ✅ Error: "Error de conexión: [mensaje]"

**Test 11.3: Post sin Contenido**
1. Crear post vacío (solo título)
2. Intentar generar meta

**Resultado esperado**:
- ✅ Genera meta basada solo en título
- ✅ O error: "Contenido insuficiente"

**Test 11.4: Rate Limiting**
1. Generar 50 metas seguidas muy rápido

**Resultado esperado**:
- ✅ Plugin añade delays automáticos
- ✅ No se produce error 429 de OpenAI
- ✅ Procesamiento por lotes funciona

---

### Test 12: Compatibilidad de Navegadores

**Objetivo**: Verificar que funciona en todos los navegadores

**Navegadores a probar**:
- ✅ Chrome
- ✅ Firefox
- ✅ Safari
- ✅ Edge

**Funcionalidades a verificar**:
- Columna "Meta" se ve correctamente
- Botones AJAX funcionan
- Notificaciones aparecen
- Barra de progreso se muestra
- CSS se aplica correctamente

---

### Test 13: Responsive Design

**Objetivo**: Verificar que funciona en móvil

**Pasos**:
1. Acceder a WordPress admin desde móvil
2. Ir a **Entradas**
3. Verificar columna "Meta"

**Resultado esperado**:
- ✅ Columna se adapta a pantalla pequeña
- ✅ Botones son clicables en táctil
- ✅ Notificaciones se ven correctamente

---

### Test 14: Compatibilidad con Otros Plugins

**Plugins a probar**:
- ✅ Rank Math SEO
- ✅ Yoast SEO
- ✅ All in One SEO
- ✅ Classic Editor
- ✅ Gutenberg
- ✅ WooCommerce (si aplica)

**Resultado esperado**:
- ✅ Sin conflictos JavaScript
- ✅ Sin conflictos CSS
- ✅ Metas se guardan correctamente en cada plugin

---

### Test 15: Performance

**Objetivo**: Verificar que no ralentiza WordPress

**Métricas a verificar**:
- Tiempo de carga de página **Entradas**: < 2s adicionales
- Tiempo de generación de meta: 3-8 segundos (depende de OpenAI)
- Uso de memoria: < 10MB adicionales
- Queries de base de datos: < 5 queries adicionales

**Herramientas**:
- Query Monitor plugin
- Browser DevTools > Network
- PHP profiler (si disponible)

---

### Test 16: Seguridad

**Objetivo**: Verificar que es seguro

**Test 16.1: CSRF**
- Intentar llamar AJAX sin nonce
- ✅ Debe rechazar con error 403

**Test 16.2: Permisos**
- Login como Subscriber
- Intentar acceder a Meta Fill > Configuración
- ✅ Debe mostrar "No tienes permisos"

**Test 16.3: SQL Injection**
- Intentar inyectar SQL en campos de configuración
- ✅ Debe sanitizar y escapar correctamente

**Test 16.4: XSS**
- Intentar inyectar `<script>alert('XSS')</script>` en prompt
- ✅ Debe escapar y no ejecutar

---

## 📊 Checklist de Testing

### Funcionalidad Básica
- [ ] Instalación sin errores
- [ ] Activación correcta
- [ ] Configuración guarda opciones
- [ ] API key se valida
- [ ] Columna aparece en admin

### Generación
- [ ] Generación individual funciona
- [ ] Regeneración funciona
- [ ] Generación masiva funciona
- [ ] Auto-generación al publicar funciona
- [ ] Longitud se respeta
- [ ] Prompt personalizado funciona

### Compatibilidad
- [ ] Rank Math detectado
- [ ] Yoast SEO detectado
- [ ] AIOSEO detectado
- [ ] Metas se guardan correctamente
- [ ] Sin conflictos con otros plugins

### UI/UX
- [ ] Estados visuales claros
- [ ] Botones AJAX funcionan
- [ ] Notificaciones aparecen
- [ ] Barra de progreso funciona
- [ ] Responsive design OK

### Seguridad
- [ ] Nonces verificados
- [ ] Permisos verificados
- [ ] Inputs sanitizados
- [ ] Output escapado
- [ ] Sin vulnerabilidades CSRF/XSS

### Performance
- [ ] Carga rápida (< 2s)
- [ ] Generación rápida (< 10s)
- [ ] Bajo uso de memoria
- [ ] Pocas queries DB

### Errores
- [ ] API key inválida manejada
- [ ] Sin conexión manejada
- [ ] Post vacío manejado
- [ ] Rate limiting manejado
- [ ] Errores se loggean

---

## 🐛 Bugs Conocidos (si se encuentran)

_Lista aquí cualquier bug encontrado durante el testing_

1. **[Bug Description]**: 
   - Pasos para reproducir: 
   - Resultado esperado: 
   - Resultado actual: 
   - Severidad: Alta/Media/Baja

---

## ✅ Testing Completado

Una vez completado el testing, el plugin está listo para:
- 🚀 Deployment en producción
- 📦 Distribución pública
- 👥 Uso por usuarios finales

---

**Testeado por**: [Nombre]  
**Fecha**: [YYYY-MM-DD]  
**Versión**: 1.0.0  
**WordPress**: [versión]  
**PHP**: [versión]
