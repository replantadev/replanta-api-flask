# Replanta Theme

Tema **AI-native** para WordPress. FSE puro, ultra rápido (Tailwind v4 + Interactivity API), pensado para que **escribas las páginas con prompts** desde el admin o como archivos `.mdx` en VS Code.

## Estado: Fase 1 (scaffolding)

Esto es la base. Funciona como tema activable, con admin "Replanta AI" y onboarding de 3 pasos. La generación con Claude llega en Fase 3.

## Roadmap

1. **Fase 1** — Scaffolding tema + admin shell ✅
2. Fase 2 — Sync `/content` ↔ CPT `rt_page`
3. Fase 3 — AI generación (Claude) por página y sección
4. Fase 4 — SEO + interlinking RankMath
5. Fase 5 — i18n AI batch (Polylang)
6. Fase 6 — Style Packs y editor visual
7. Fase 7 — Importador Elementor → Replanta MDX
8. Fase 8 — Marketplace de patterns

## Setup local

```powershell
cd replanta-theme
pnpm install
pnpm build
```

Activa el tema en WP (`Apariencia → Temas`). Verás un menú **"Replanta AI"** con el wizard.

Para desarrollo continuo:

```powershell
pnpm dev
```

## Stack

- **PHP 8.2+ / WP 6.6+**
- **Tailwind v4** (compilado a `assets/dist/`, no se sirve runtime)
- **TypeScript + React** (`@wordpress/element`) en el admin
- **Vite** como bundler
- **pnpm** como package manager
- **Anthropic Claude** como provider IA por defecto (OpenAI fallback)

## Estructura

```
replanta-theme/
├── style.css            # Header del tema
├── theme.json           # Design tokens
├── functions.php        # Bootstrap + autoloader
├── templates/           # FSE templates
├── parts/               # header / footer
├── patterns/            # Bloques sincronizados (hero, cta…)
├── inc/                 # Clases PHP (RT_*)
│   ├── class-rt-theme.php
│   ├── class-rt-admin.php
│   ├── class-rt-rest.php
│   ├── class-rt-cpt-page.php
│   ├── class-rt-assets.php
│   ├── class-rt-ai-provider.php
│   └── providers/       # OpenAI, Anthropic, Ollama (Fase 3)
├── assets/
│   ├── src/
│   │   ├── admin/       # React app del admin (TS)
│   │   └── theme/       # Interactivity front-end
│   └── dist/            # Build output (gitignored)
└── content/             # MDX sources (Fase 2)
```

## REST API

- `GET  /wp-json/replanta/v1/health`
- `GET  /wp-json/replanta/v1/settings`
- `POST /wp-json/replanta/v1/settings`
- `POST /wp-json/replanta/v1/onboarding`

## Convenciones

- Clases `RT_*`, snake_case en métodos (estándar WP, ignorar warnings de SonarQube/PSR si aparecen).
- Tabs para indentación PHP (estándar WP), 2 espacios para JS/TS/CSS.
- Nada de jQuery. Nada de Elementor. Nada de inline scripts en el front.

## Desarrollo en VS Code

Recomendado:
- Extensión **PHP Intelephense** + stubs `wordpress` activados.
- Extensión **Tailwind CSS IntelliSense**.
- Extensión **ESLint** + **Prettier**.

## Licencia

GPL-2.0-or-later
