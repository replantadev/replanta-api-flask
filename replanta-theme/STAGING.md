# Replanta Theme — Estado Fase 1-7 (staging)

## ✅ Implementado

### Backend PHP (`inc/`)
- `class-rt-theme.php` — Orquestador, registra todos los módulos
- `class-rt-cpt-page.php` — CPT `rt_page`
- `class-rt-admin.php` — Menú Replanta AI + assets
- `class-rt-rest.php` — REST `replanta/v1/*` con todos los endpoints
- `class-rt-assets.php` — Front enqueue
- `class-rt-content-sync.php` — MDX ↔ posts
- `class-rt-mdx-parser.php` — YAML lite + JSX parser
- `class-rt-component-renderer.php` — Renderiza `<!-- rt:component -->` a HTML
- `class-rt-page-generator.php` — generate_page / section / rewrite / translate
- `class-rt-rankmath-bridge.php` — SEO + interlinking
- `class-rt-i18n-driver.php` — Polylang/WPML adapter
- `class-rt-style-packs.php` — 3 presets (eco/tech/editorial)
- `class-rt-elementor-importer.php` — Importer Elementor → MDX
- `class-rt-ai-provider.php` — Interface
- `providers/class-rt-anthropic.php` — Claude Sonnet 4.5
- `providers/class-rt-openai.php` — GPT-4o
- `cli/class-rt-cli.php` — `wp replanta sync|generate|import-elementor`

### Frontend React (`assets/src/admin/`)
- `Composer.tsx` — UI completa: 3 tabs (Composer/Estilo/Importar), árbol de páginas por idioma, prompt+generación, editor frontmatter+body, traducción, eliminar, sync, style packs, importer Elementor

### REST endpoints `replanta/v1/`
| Método | Ruta | Función |
|---|---|---|
| GET | /health | Status |
| GET/POST | /settings | Config |
| POST | /onboarding | Wizard |
| GET | /pages | Listar |
| GET/PUT/DELETE | /pages/{path} | CRUD |
| POST | /sync | Re-sync MDX |
| POST | /generate/page | Generar página |
| POST | /generate/section | Generar sección |
| POST | /rewrite | Reescribir |
| POST | /translate | Traducir |
| GET | /interlink?slug&lang | Sugerencias enlaces |
| GET/POST | /style-packs | Listar/aplicar pack |
| GET | /import/elementor/list | Listar Elementor |
| POST | /import/elementor | Importar |

## 🚧 Pendiente / Fase 8 (no en staging)
- Marketplace de patterns
- Block patterns reales (hero/cta/features/etc) — actualmente sólo `hero-eco.php`
- WPML real (driver es stub)
- Streaming SSE en /generate

## 📦 Build & Deploy a staging

```powershell
cd replanta-theme
pnpm install
pnpm build
# Subir todo el directorio a /wp-content/themes/replanta-theme/
# Activar tema. Visitar wp-admin > Replanta AI para onboarding.
```

## 🧪 Testing rápido
1. Activar tema
2. Onboarding: pegar API key Anthropic, elegir style pack
3. Composer: prompt "Landing eco para auditoría carbono" → Generar
4. Página aparece en /content/es/ y como CPT rt_page
5. Probar traducción a EN
6. Probar import Elementor sobre cualquier página existente

## ⚠️ Notas
- Lints de SonarQube sobre tabs/snake_case/funciones WP undefined son **ruido** (estándar WP, sin stubs). Ignorar.
- El parser YAML es subset; si frontmatter es complejo, usar comillas y formatos planos.
