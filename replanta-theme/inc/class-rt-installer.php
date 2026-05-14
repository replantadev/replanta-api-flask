<?php
/**
 * Installer — first-run setup: creates content dirs, seeds sample pages, runs sync.
 * Designed to be invoked from admin notice button or REST endpoint.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Installer {

	public const OPTION_INSTALLED = 'rt_theme_installed';
	public const OPTION_VERSION   = 'rt_theme_installed_version';

	public function register(): void {
		add_action( 'after_switch_theme', [ $this, 'on_activation' ] );
		add_action( 'admin_notices', [ $this, 'maybe_notice' ] );
	}

	public function is_installed(): bool {
		return (bool) get_option( self::OPTION_INSTALLED, false );
	}

	public function on_activation(): void {
		// Just create dirs on activation; full install runs from button.
		$this->ensure_dirs();
	}

	public function maybe_notice(): void {
		if ( $this->is_installed() ) {
			return;
		}
		if ( ! current_user_can( RT_Admin::CAPABILITY ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && $screen->id === 'toplevel_page_' . RT_Admin::PAGE_SLUG ) {
			return; // The Composer renders its own install panel.
		}
		$url = admin_url( 'admin.php?page=' . RT_Admin::PAGE_SLUG );
		echo '<div class="notice notice-info" style="border-left-color:#1F6F45;">';
		echo '<p style="display:flex;align-items:center;gap:12px;margin:12px 0;">';
		echo '<strong style="font-size:14px;">' . esc_html__( 'Replanta AI', 'replanta-theme' ) . '</strong>';
		echo '<span>' . esc_html__( '¡Bienvenido! Configura tu tema en un clic.', 'replanta-theme' ) . '</span>';
		echo '<a href="' . esc_url( $url ) . '" class="button button-primary" style="background:#1F6F45;border-color:#1F6F45;">';
		echo esc_html__( 'Configurar Replanta AI', 'replanta-theme' );
		echo '</a>';
		echo '</p></div>';
	}

	/**
	 * Full install: create dirs, seed sample MDX files, sync.
	 *
	 * @return array{ ok: bool, dirs: array<int,string>, seeded: array<int,string>, sync: array<string,mixed> }
	 */
	public function install( string $lang = 'es', bool $seed_demo = true ): array {
		$dirs    = $this->ensure_dirs( $lang );
		$seeded  = $seed_demo ? $this->seed_demo( $lang ) : [];
		$sync    = ( new RT_Content_Sync() )->sync_all();

		update_option( self::OPTION_INSTALLED, true, false );
		update_option( self::OPTION_VERSION, RT_THEME_VERSION, false );

		return [ 'ok' => true, 'dirs' => $dirs, 'seeded' => $seeded, 'sync' => $sync ];
	}

	/** @return array<int,string> */
	private function ensure_dirs( string $primary_lang = 'es' ): array {
		$root = trailingslashit( RT_THEME_DIR ) . 'content';
		$created = [];
		foreach ( [ $root, $root . '/' . $primary_lang, $root . '/' . $primary_lang . '/imported' ] as $dir ) {
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
				$created[] = $dir;
			}
		}
		// Add .htaccess to deny direct file listing.
		$ht = $root . '/.htaccess';
		if ( ! file_exists( $ht ) ) {
			file_put_contents( $ht, "Options -Indexes\n" );
		}
		return $created;
	}

	/** @return array<int,string> Paths of seeded files. */
	private function seed_demo( string $lang ): array {
		$dir = trailingslashit( RT_THEME_DIR ) . 'content/' . $lang;
		$seeded = [];

		$samples = [
			'home.mdx' => $this->sample_home( $lang ),
			'sobre-nosotros.mdx' => $this->sample_about( $lang ),
		];

		foreach ( $samples as $filename => $content ) {
			$path = $dir . '/' . $filename;
			if ( file_exists( $path ) ) {
				continue; // Don't overwrite.
			}
			file_put_contents( $path, $content );
			$seeded[] = $path;
		}
		return $seeded;
	}

	private function sample_home( string $lang ): string {
		$title = $lang === 'en' ? 'Home' : 'Inicio';
		$tag   = $lang === 'en'
			? 'AI-native WordPress, fast and simple.'
			: 'WordPress nativo de IA, rápido y simple.';
		$cta   = $lang === 'en' ? 'Get started' : 'Empezar ahora';

		return <<<MDX
---
title: {$title}
slug: home
lang: {$lang}
template: page-wide
patterns: [Hero, Features, CTA]
seo:
  meta_description: "{$tag}"
---

<Hero id="home-hero">
# {$title}

{$tag}

[{$cta}](#contacto)
</Hero>

<Features id="home-features">
## ¿Por qué Replanta?

- **Velocidad**: tema FSE optimizado, CSS mínimo.
- **IA nativa**: genera páginas con prompts.
- **Multi-idioma**: Polylang/WPML out of the box.
</Features>

<CTA id="home-cta">
## ¿Listo para empezar?

Configura tu primera página con un prompt en el Composer.
</CTA>

MDX;
	}

	private function sample_about( string $lang ): string {
		$title = $lang === 'en' ? 'About' : 'Sobre nosotros';
		return <<<MDX
---
title: {$title}
slug: sobre-nosotros
lang: {$lang}
template: page-narrow
patterns: [Content]
---

<Content id="about-content">
## {$title}

Esta es una página de ejemplo creada por el instalador de Replanta AI.
Edítala desde el Composer o directamente en `content/{$lang}/sobre-nosotros.mdx`.
</Content>

MDX;
	}
}
