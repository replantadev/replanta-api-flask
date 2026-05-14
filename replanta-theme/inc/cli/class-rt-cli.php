<?php
/**
 * WP-CLI commands.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

final class RT_CLI {

	/**
	 * Sync /content files into rt_page CPT.
	 *
	 * ## EXAMPLES
	 *     wp replanta sync
	 */
	public function sync( $args, $assoc ): void { // phpcs:ignore
		$sync = new RT_Content_Sync();
		$r = $sync->sync_all();
		WP_CLI::log( sprintf( 'scanned=%d created=%d updated=%d skipped=%d errors=%d',
			$r['scanned'], $r['created'] ?? 0, $r['updated'] ?? 0, $r['skipped'] ?? 0, count( $r['errors'] )
		) );
		foreach ( $r['errors'] as $e ) {
			WP_CLI::warning( $e );
		}
	}

	/**
	 * Generate a page using AI.
	 *
	 * ## OPTIONS
	 * <prompt>
	 * : Description of the page.
	 *
	 * [--lang=<lang>]
	 * : Target language slug.
	 *
	 * [--slug=<slug>]
	 * : URL slug.
	 *
	 * ## EXAMPLES
	 *     wp replanta generate "Landing for carbon audit service" --lang=es --slug=auditoria
	 */
	public function generate( $args, $assoc ): void { // phpcs:ignore
		$prompt = (string) ( $args[0] ?? '' );
		$lang   = (string) ( $assoc['lang'] ?? 'es' );
		$slug   = (string) ( $assoc['slug'] ?? '' );
		if ( $prompt === '' ) {
			WP_CLI::error( 'Prompt required.' );
		}
		$gen = new RT_Page_Generator();
		$res = $gen->generate_page( $prompt, $lang, $slug ?: null );
		if ( ! $res['ok'] ) {
			WP_CLI::error( $res['error'] ?? 'Unknown error' );
		}
		WP_CLI::success( 'Created: ' . $res['path'] );
	}

	/**
	 * Import an Elementor post into MDX.
	 *
	 * ## OPTIONS
	 * --post=<id>
	 * : Post ID to import.
	 *
	 * [--lang=<lang>]
	 * : Language slug. Default: es.
	 *
	 * ## EXAMPLES
	 *     wp replanta import-elementor --post=123 --lang=es
	 */
	public function import_elementor( $args, $assoc ): void { // phpcs:ignore
		$id   = (int) ( $assoc['post'] ?? 0 );
		$lang = (string) ( $assoc['lang'] ?? 'es' );
		if ( $id <= 0 ) {
			WP_CLI::error( '--post=<id> required.' );
		}
		$importer = new RT_Elementor_Importer();
		$res = $importer->import( $id, $lang );
		if ( ! $res['ok'] ) {
			WP_CLI::error( $res['error'] ?? 'Unknown error' );
		}
		WP_CLI::success( 'Imported to: ' . $res['path'] . ' (review needed: ' . ( $res['review_needed'] ? 'yes' : 'no' ) . ')' );
	}
}

WP_CLI::add_command( 'replanta', RT_CLI::class );
