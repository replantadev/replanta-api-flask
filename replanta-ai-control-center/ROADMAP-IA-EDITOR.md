# Roadmap IA Editor OS (Replanta)

## Objetivo
Construir una experiencia editorial en WordPress donde:
- cada página se pueda editar con IA desde el editor
- cada página tenga historial de chat y revisiones
- exista un chat global para estructura del sitio y menús
- el frontend mantenga rendimiento máximo

## Estado actual (Sprint 1 iniciado)
- IA para creación de páginas por prompt
- Publicar/despublicar con controles y validaciones
- Header/Footer editable por Customizer (filas, columnas, módulos, texto, botón)
- IA para layout de Header/Footer (admin + REST)

## Sprint 1 (base estable)
1. Importador HTML desde carpeta front
   - Parsear HTML por archivo
   - Crear/actualizar páginas WP
   - Guardar metadatos de origen (archivo, hash, fecha)
2. Contrato JSON de edición
   - Definir esquema para operaciones de contenido
   - Validación estricta antes de aplicar cambios
3. Preview seguro
   - Modo dry-run para ver cambios sin aplicar

## Sprint 2 (chat por página)
1. Sidebar en editor de página
   - Prompt contextual
   - Propuesta de cambios
   - Aplicar parcial o total
2. Historial por página
   - Tabla de threads
   - Tabla de mensajes
   - Tabla de acciones aplicadas
3. Revisión y rollback
   - Crear revisión WP en cada operación IA
   - Restauración rápida

## Sprint 3 (chat global)
1. Vista de arquitectura del sitio
   - Árbol de páginas
   - Menús y enlaces
2. Prompt global
   - Editar estructura por lotes
   - Simulación de impacto
3. Flujo de aprobación
   - Previsualizar cambios globales
   - Confirmar aplicación

## Sprint 4 (performance y hardening)
1. Presupuestos de rendimiento por template
2. Gating de publicación (a11y + semántica + SEO)
3. Rate limit por operación y rol
4. Logging y auditoría completa

## Métricas objetivo 2026
- LCP <= 1.8s (móvil, caché caliente)
- CLS <= 0.05
- INP <= 150ms
- JS crítico <= 70KB comprimido
- CSS crítico <= 35KB comprimido

## Próxima entrega recomendada
1. Implementar importador de archivos front a páginas WP
2. Exponer endpoint de dry-run para edición IA por página
3. Crear primer panel de chat por página (MVP)
