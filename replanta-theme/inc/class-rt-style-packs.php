<?php
/**
 * Style Packs — apply preset bundles (palette + fonts + density + radius)
 * by writing user CSS variables override + saving choice to options.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Style_Packs {

	public const OPTION = 'rt_active_style_pack';

	public function register(): void {
		add_action( 'wp_head', [ $this, 'inline_overrides' ], 5 );
	}

	/** @return array<string, array<string,mixed>> */
	public static function packs(): array {
		return [
			'replanta-eco' => [
				'name'    => 'Replanta Eco',
				'palette' => [
					'bg' => '#FBFAF6', 'fg' => '#0E1A14', 'muted' => '#6B7568',
					'surface' => '#F1EFE7', 'border' => '#D9D6CA',
					'primary' => '#1F6F45', 'primary-fg' => '#FFFFFF',
					'accent'  => '#E8B84C', 'accent-fg' => '#0E1A14',
				],
				'fonts'   => [ 'serif' => 'DM Serif Display', 'sans' => 'DM Sans' ],
				'density' => 'normal',
				'radius'  => 'soft',
			],
			'tech-studio' => [
				'name'    => 'Tech Studio',
				'palette' => [
					'bg' => '#0A0A0B', 'fg' => '#F4F4F5', 'muted' => '#A1A1AA',
					'surface' => '#18181B', 'border' => '#27272A',
					'primary' => '#10B981', 'primary-fg' => '#0A0A0B',
					'accent'  => '#F59E0B', 'accent-fg' => '#0A0A0B',
				],
				'fonts'   => [ 'serif' => 'Geist', 'sans' => 'Geist' ],
				'density' => 'compact',
				'radius'  => 'sharp',
			],
			'editorial-mag' => [
				'name'    => 'Editorial Mag',
				'palette' => [
					'bg' => '#FFFFFF', 'fg' => '#0F0F0F', 'muted' => '#525252',
					'surface' => '#FAFAFA', 'border' => '#E5E5E5',
					'primary' => '#1A1A1A', 'primary-fg' => '#FFFFFF',
					'accent'  => '#DC2626', 'accent-fg' => '#FFFFFF',
				],
				'fonts'   => [ 'serif' => 'Playfair Display', 'sans' => 'Source Sans 3' ],
				'density' => 'airy',
				'radius'  => 'sharp',
			],
		];
	}

	public function apply( string $pack_id ): bool {
		if ( ! isset( self::packs()[ $pack_id ] ) ) return false;
		update_option( self::OPTION, $pack_id, false );
		return true;
	}

	public function active_pack(): array {
		$id = (string) get_option( self::OPTION, 'replanta-eco' );
		$packs = self::packs();
		return $packs[ $id ] ?? $packs['replanta-eco'];
	}

	public function inline_overrides(): void {
		$pack = $this->active_pack();
		$lines = [];
		foreach ( (array) $pack['palette'] as $slug => $hex ) {
			$lines[] = '--wp--preset--color--' . esc_attr( (string) $slug ) . ': ' . esc_attr( (string) $hex ) . ';';
		}
		$density_map = [ 'compact' => '1.25', 'normal' => '1.5', 'airy' => '1.85' ];
		$density = $density_map[ (string) ( $pack['density'] ?? 'normal' ) ] ?? '1.5';
		$lines[] = '--rt-spacing-scale: ' . $density . ';';

		$radius_map = [ 'sharp' => '0', 'soft' => '0.625rem' ];
		$lines[] = '--rt-radius: ' . ( $radius_map[ (string) ( $pack['radius'] ?? 'soft' ) ] ?? '0.625rem' ) . ';';

		echo "<style id='rt-style-pack'>:root{" . implode( '', $lines ) . "}</style>\n"; // phpcs:ignore
	}
}
