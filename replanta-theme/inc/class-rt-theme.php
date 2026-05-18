<?php
/**
 * Main theme orchestrator.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Theme {

	private static ?RT_Theme $instance = null;

	public static function instance(): RT_Theme {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Singleton.
	}

	public function init(): void {
		( new RT_CPT_Page() )->register();
		( new RT_Admin() )->register();
		( new RT_REST() )->register();
		( new RT_Assets() )->register();
		( new RT_Component_Renderer() )->register();
		( new RT_Style_Packs() )->register();
		( new RT_RankMath_Bridge() )->register();
		( new RT_I18n_Driver() )->register();
		( new RT_Installer() )->register();
		( new RT_Layout() )->register();
		( new RT_Promote() )->register();
		( new RT_Promote_Admin() )->register();
		( new RT_Mirror_Renderer() )->register();
		( new RT_Mirror_Cleanup() )->register();
		( new RT_Mirror_Admin() )->register();
		( new RT_Schema() )->register();
		( new RT_Sitemap() )->register();

		// Register footer menu location for the layout footer.
		add_action( 'after_setup_theme', static function (): void {
			register_nav_menus( [
				'primary' => __( 'Menú principal', 'replanta-theme' ),
				'footer'  => __( 'Menú de pie', 'replanta-theme' ),
			] );
		}, 30 );
	}
}
