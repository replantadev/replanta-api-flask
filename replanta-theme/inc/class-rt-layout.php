<?php
/**
 * RT_Layout — site chrome (header / footer) controller.
 *
 * Astra-style: a small set of toggles that you can change site-wide and
 * override per page via frontmatter. Settings live inside the
 * `rt_theme_settings` option under the `layout` sub-key.
 *
 * Per-page overrides honoured (frontmatter keys):
 *   - header_transparent : bool
 *   - hide_header        : bool
 *   - hide_footer        : bool
 *
 * The class only emits body classes + a tiny CSS shim and exposes a couple of
 * helpers used by `parts/header.html` and `parts/footer.html`. The actual
 * markup lives in the template parts (FSE block theme).
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Layout {

	public const OPTION = 'rt_theme_settings';
	public const SUBKEY = 'layout';

	public const DEFAULTS = [
		'header_sticky'       => true,
		'header_transparent'  => false,
		'header_bg'           => '',
		'header_color'        => '',
		'footer_compact'      => false,
		'logo_id'             => 0,
		'logo_height'         => 36,
		'cta_label'           => '',
		'cta_href'            => '',
		'tagline'             => '',
		'social_twitter'      => '',
		'social_linkedin'     => '',
		'social_github'       => '',
	];

	public function register(): void {
		add_filter( 'body_class', [ $this, 'body_class' ] );
		add_action( 'wp_head', [ $this, 'inline_css' ], 99 );
		add_action( 'init', [ $this, 'register_blocks' ] );
	}

	public function register_blocks(): void {
		if ( ! function_exists( 'register_block_type' ) ) return;
		register_block_type( 'replanta/site-header', [
			'render_callback' => static function (): string {
				$brand = self::render_brand();
				$cta   = self::render_header_cta();
				$nav   = '';
				if ( function_exists( 'has_nav_menu' ) && has_nav_menu( 'primary' ) ) {
					$nav = (string) wp_nav_menu( [
						'theme_location' => 'primary',
						'container'      => false,
						'depth'          => 2,
						'menu_class'     => 'rt-primary-menu',
						'fallback_cb'    => '__return_false',
						'echo'           => false,
					] );
				}
				$nav_block = $nav !== '' ? '<nav class="rt-primary-nav" aria-label="' . esc_attr__( 'Navegación principal', 'replanta-theme' ) . '">' . $nav . '</nav>' : '';
				$style = '<style>'
					. '.rt-site-header .rt-header-inner{max-width:var(--wp--style--global--wide-size,1200px);margin:0 auto;padding:0 var(--wp--preset--spacing--40,1rem);display:flex;align-items:center;justify-content:space-between;gap:1rem}'
					. '.rt-primary-nav .rt-primary-menu{display:flex;gap:1.4rem;list-style:none;margin:0;padding:0}'
					. '.rt-primary-nav .rt-primary-menu a{text-decoration:none;color:inherit;font-size:.95rem;font-weight:500;opacity:.85;transition:opacity .12s}'
					. '.rt-primary-nav .rt-primary-menu a:hover{opacity:1}'
					. '@media(max-width:782px){.rt-primary-nav{display:none}}'
					. '</style>';
				return $style . '<div class="rt-header-inner">' . $brand . $nav_block . '<div class="rt-header-actions">' . $cta . '</div></div>';
			},
		] );
		register_block_type( 'replanta/site-footer', [
			'render_callback' => static function (): string {
				return self::render_footer_cols();
			},
		] );
	}

	/** @return array<string,mixed> */
	public static function settings(): array {
		$all = (array) get_option( self::OPTION, [] );
		$lay = (array) ( $all[ self::SUBKEY ] ?? [] );
		return array_merge( self::DEFAULTS, $lay );
	}

	/** @param array<string,mixed> $incoming @return array<string,mixed> */
	public static function save( array $incoming ): array {
		$all = (array) get_option( self::OPTION, [] );
		$cur = (array) ( $all[ self::SUBKEY ] ?? [] );
		$next = array_merge( self::DEFAULTS, $cur, self::sanitize( $incoming ) );
		$all[ self::SUBKEY ] = $next;
		update_option( self::OPTION, $all, false );
		return $next;
	}

	/** @param array<string,mixed> $in @return array<string,mixed> */
	private static function sanitize( array $in ): array {
		$out = [];
		foreach ( self::DEFAULTS as $k => $default ) {
			if ( ! array_key_exists( $k, $in ) ) continue;
			$v = $in[ $k ];
			if ( is_bool( $default ) ) {
				$out[ $k ] = (bool) $v;
			} elseif ( is_int( $default ) ) {
				$out[ $k ] = max( 0, (int) $v );
			} else {
				$out[ $k ] = is_string( $v ) ? sanitize_text_field( $v ) : '';
			}
		}
		// Special: cta_href accepts URLs.
		if ( isset( $in['cta_href'] ) ) {
			$out['cta_href'] = esc_url_raw( (string) $in['cta_href'] );
		}
		// Hex colors.
		foreach ( [ 'header_bg', 'header_color' ] as $col ) {
			if ( isset( $in[ $col ] ) ) {
				$v = trim( (string) $in[ $col ] );
				$out[ $col ] = preg_match( '/^#?[0-9a-fA-F]{3,8}$/', $v ) ? ( $v[0] === '#' ? $v : '#' . $v ) : '';
			}
		}
		return $out;
	}

	/**
	 * Per-page overrides come from CPT post-meta `_rt_frontmatter` (JSON).
	 *
	 * @return array<string,mixed>
	 */
	public static function page_overrides(): array {
		if ( ! is_singular() ) return [];
		$post = get_post();
		if ( ! $post ) return [];
		$json = (string) get_post_meta( $post->ID, RT_Content_Sync::META_FRONTMATTER, true );
		if ( $json === '' ) return [];
		$front = json_decode( $json, true );
		return is_array( $front ) ? $front : [];
	}

	/** @param string[] $classes @return string[] */
	public function body_class( array $classes ): array {
		$s    = self::settings();
		$over = self::page_overrides();

		$transparent = ! empty( $over['header_transparent'] ) ? true : (bool) $s['header_transparent'];
		$hide_header = ! empty( $over['hide_header'] );
		$hide_footer = ! empty( $over['hide_footer'] );

		if ( ! empty( $s['header_sticky'] ) )      $classes[] = 'rt-header-sticky';
		if ( $transparent )                        $classes[] = 'rt-header-transparent';
		if ( ! empty( $s['footer_compact'] ) )     $classes[] = 'rt-footer-compact';
		if ( $hide_header )                        $classes[] = 'rt-hide-header';
		if ( $hide_footer )                        $classes[] = 'rt-hide-footer';
		return $classes;
	}

	public function inline_css(): void {
		$s = self::settings();
		$logo_h = (int) $s['logo_height'];
		$bg     = (string) $s['header_bg'];
		$color  = (string) $s['header_color'];

		$rules = [];
		$rules[] = ':root{--rt-logo-h:' . max( 16, min( 96, $logo_h ) ) . 'px}';
		// Sticky header.
		$rules[] = '.rt-header-sticky .rt-site-header{position:sticky;top:0;z-index:50;backdrop-filter:saturate(180%) blur(10px);-webkit-backdrop-filter:saturate(180%) blur(10px);background:rgba(255,255,255,.85);border-bottom:1px solid rgba(0,0,0,.06);transition:background .2s,box-shadow .2s}';
		$rules[] = '.rt-header-sticky .rt-site-header.rt-scrolled{box-shadow:0 4px 18px rgba(14,26,20,.06);background:rgba(255,255,255,.96)}';
		// Transparent header (overlays first section).
		$rules[] = '.rt-header-transparent .rt-site-header{background:transparent;border-bottom:0;position:absolute;top:0;left:0;right:0;z-index:50}';
		$rules[] = '.rt-header-transparent .rt-site-header.rt-scrolled{background:rgba(255,255,255,.96);position:sticky;border-bottom:1px solid rgba(0,0,0,.06)}';
		$rules[] = '.rt-header-transparent .rt-site-header a,.rt-header-transparent .rt-site-header .rt-site-title{color:#fff}';
		$rules[] = '.rt-header-transparent .rt-site-header.rt-scrolled a,.rt-header-transparent .rt-site-header.rt-scrolled .rt-site-title{color:inherit}';
		// Hide chrome.
		$rules[] = '.rt-hide-header .rt-site-header,.rt-hide-footer .rt-site-footer{display:none!important}';
		// Compact footer.
		$rules[] = '.rt-footer-compact .rt-site-footer{padding-top:1.25rem!important;padding-bottom:1.25rem!important}';
		$rules[] = '.rt-footer-compact .rt-site-footer .rt-footer-cols{grid-template-columns:1fr!important;text-align:center}';
		// Brand block.
		$rules[] = '.rt-site-header{padding:.85rem 0}';
		$rules[] = '.rt-brand{display:inline-flex;align-items:center;gap:.55rem;text-decoration:none;color:inherit;font-weight:600;letter-spacing:-.01em}';
		$rules[] = '.rt-brand img,.rt-brand svg{height:var(--rt-logo-h);width:auto;display:block}';
		$rules[] = '.rt-brand .rt-tagline{font-size:.7rem;font-weight:500;color:var(--wp--preset--color--muted,#5C665A);letter-spacing:.06em;text-transform:uppercase}';
		// Header CTA.
		$rules[] = '.rt-header-cta{display:inline-flex;align-items:center;gap:.4rem;background:var(--wp--preset--color--primary,#1F6F45);color:#fff!important;padding:.5rem 1rem;border-radius:.5rem;font-size:.875rem;font-weight:500;text-decoration:none;transition:transform .12s,box-shadow .12s}';
		$rules[] = '.rt-header-cta:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(31,111,69,.25)}';
		// Footer cols.
		$rules[] = '.rt-site-footer .rt-footer-cols{display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:2rem;margin-bottom:2rem}';
		$rules[] = '.rt-site-footer .rt-footer-cols h4{font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;color:var(--wp--preset--color--muted,#5C665A);margin:0 0 .8rem;font-weight:600}';
		$rules[] = '.rt-site-footer .rt-footer-social{display:flex;gap:.6rem;margin-top:.8rem}';
		$rules[] = '.rt-site-footer .rt-footer-social a{color:inherit;opacity:.7;transition:opacity .12s}';
		$rules[] = '.rt-site-footer .rt-footer-social a:hover{opacity:1}';
		$rules[] = '@media (max-width:782px){.rt-site-footer .rt-footer-cols{grid-template-columns:1fr;gap:1.5rem;text-align:left}}';
		// Customs.
		if ( $bg !== '' )    $rules[] = '.rt-site-header{background:' . esc_attr( $bg ) . '!important}';
		if ( $color !== '' ) $rules[] = '.rt-site-header,.rt-site-header a{color:' . esc_attr( $color ) . '!important}';
		// Tiny scroll listener.
		$js = '<script>(function(){var h=document.querySelector(".rt-site-header");if(!h)return;var f=function(){h.classList.toggle("rt-scrolled",window.scrollY>8)};f();window.addEventListener("scroll",f,{passive:true})})();</script>';
		echo "<style id=\"rt-layout-css\">\n" . implode( "\n", $rules ) . "\n</style>\n" . $js;
	}

	/**
	 * Render the brand mark (logo image if uploaded, otherwise inline brand SVG)
	 * for use inside the header template part.
	 */
	public static function render_brand(): string {
		$s = self::settings();
		$logo_id = (int) $s['logo_id'];
		$site    = get_bloginfo( 'name' );
		$home    = esc_url( home_url( '/' ) );
		$tag     = (string) $s['tagline'];

		if ( $logo_id > 0 && function_exists( 'wp_get_attachment_image' ) ) {
			$img = wp_get_attachment_image( $logo_id, 'full', false, [
				'class'    => 'rt-brand-logo',
				'alt'      => esc_attr( $site ),
				'loading'  => 'eager',
				'decoding' => 'async',
			] );
			if ( $img ) {
				$inner = $img;
				if ( $tag !== '' ) $inner .= '<span class="rt-tagline">' . esc_html( $tag ) . '</span>';
				return '<a class="rt-brand" href="' . $home . '" rel="home">' . $inner . '</a>';
			}
		}
		// Fallback to inline SVG file from theme root.
		$svg_file = RT_THEME_DIR . 'replantav3-ico.svg';
		$svg = '';
		if ( is_readable( $svg_file ) ) {
			$svg = (string) file_get_contents( $svg_file );
			$svg = preg_replace( '/<\?xml[^>]+\?>/i', '', $svg );
			$svg = preg_replace( '/<!--.*?-->/s', '', (string) $svg );
		}
		if ( $svg === '' ) {
			$svg = RT_Icons::brand( 32 );
		}
		$out = '<a class="rt-brand" href="' . $home . '" rel="home">' . $svg;
		if ( $tag !== '' ) $out .= '<span class="rt-tagline">' . esc_html( $tag ) . '</span>';
		$out .= '</a>';
		return $out;
	}

	public static function render_header_cta(): string {
		$s = self::settings();
		$label = trim( (string) $s['cta_label'] );
		$href  = trim( (string) $s['cta_href'] );
		if ( $label === '' || $href === '' ) return '';
		return '<a class="rt-header-cta" href="' . esc_url( $href ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Render footer columns. Column 1 = brand + tagline + social, 2 = nav menu
	 * (uses the WP "Footer" location if registered, otherwise a placeholder),
	 * 3 = legal text.
	 */
	public static function render_footer_cols(): string {
		$s = self::settings();
		$brand_html = self::render_brand();

		$social = [];
		if ( $s['social_twitter']  ) $social[] = '<a href="' . esc_url( (string) $s['social_twitter']  ) . '" rel="noopener noreferrer" target="_blank" aria-label="Twitter">𝕏</a>';
		if ( $s['social_linkedin'] ) $social[] = '<a href="' . esc_url( (string) $s['social_linkedin'] ) . '" rel="noopener noreferrer" target="_blank" aria-label="LinkedIn">in</a>';
		if ( $s['social_github']   ) $social[] = '<a href="' . esc_url( (string) $s['social_github']   ) . '" rel="noopener noreferrer" target="_blank" aria-label="GitHub">GH</a>';
		$social_html = $social ? '<div class="rt-footer-social">' . implode( '', $social ) . '</div>' : '';

		$year = date( 'Y' );
		$site = esc_html( get_bloginfo( 'name' ) );

		ob_start();
		?>
		<div class="rt-footer-cols">
			<div class="rt-footer-col rt-footer-brand">
				<?php echo $brand_html; ?>
				<?php if ( $s['tagline'] === '' ) : ?>
					<p style="font-size:.875rem;color:var(--wp--preset--color--muted,#5C665A);margin:.6rem 0 0;max-width:32ch"><?php bloginfo( 'description' ); ?></p>
				<?php endif; ?>
				<?php echo $social_html; ?>
			</div>
			<div class="rt-footer-col">
				<h4><?php esc_html_e( 'Navegación', 'replanta-theme' ); ?></h4>
				<?php
				if ( has_nav_menu( 'footer' ) ) {
					wp_nav_menu( [ 'theme_location' => 'footer', 'container' => false, 'depth' => 1, 'menu_class' => 'rt-footer-menu' ] );
				} else {
					echo '<p style="font-size:.85rem;color:var(--wp--preset--color--muted,#5C665A);margin:0">' . esc_html__( 'Asigna un menú de navegación en Apariencia → Menús.', 'replanta-theme' ) . '</p>';
				}
				?>
			</div>
			<div class="rt-footer-col">
				<h4><?php esc_html_e( 'Legal', 'replanta-theme' ); ?></h4>
				<p style="font-size:.85rem;color:var(--wp--preset--color--muted,#5C665A);margin:0">© <?php echo (int) $year; ?> <?php echo $site; ?></p>
				<p style="font-size:.75rem;color:var(--wp--preset--color--muted,#5C665A);margin:.4rem 0 0;opacity:.7"><?php esc_html_e( 'Hecho con Replanta Theme', 'replanta-theme' ); ?></p>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
