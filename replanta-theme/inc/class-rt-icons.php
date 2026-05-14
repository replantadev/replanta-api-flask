<?php
/**
 * Phosphor icons (regular weight) — inline SVG helper.
 * Source: https://phosphoricons.com (MIT License).
 *
 * For the Replanta brand mark, use RT_Icons::brand() — never use any leaf glyph
 * to represent the brand.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Icons {

	private const BRAND_MARK_PATH = 'M70.8,3.3c-.1-1.7-1.5-3.1-3.2-3.2-15-.9-27.1,3.8-32.3,12.4-3.4,5.6-3.5,12.4-.4,18.9-1.5,1.9-2.7,4.1-3.5,6.5l-3.6-3.6c2-4.7,1.8-9.6-.7-13.7-3.9-6.5-12.9-10-24-9.3-1.7.1-3.1,1.5-3.2,3.2-.6,11.1,2.8,20,9.3,24,2.2,1.3,4.7,2.1,7.3,2,2.2,0,4.4-.5,6.4-1.4l7.3,7.3v6.5c0,1.9,1.5,3.4,3.4,3.4s3.4-1.5,3.4-3.4v-8.3c0-3,.9-6,2.7-8.4,2.8,1.3,5.9,2,9,2.1,3.4,0,6.7-.9,9.5-2.7,8.7-5.2,13.3-17.3,12.4-32.3ZM12.9,32.7c-3.7-2.3-6-7.7-6.1-14.7,6.9.2,12.4,2.4,14.7,6.1.9,1.4,1.2,3.1,1,4.8l-3.2-3.2c-1.3-1.3-3.5-1.3-4.8,0-1.3,1.3-1.3,3.5,0,4.8l3.2,3.2c-1.7.2-3.3-.2-4.8-1ZM54.9,29.8c-3,1.8-6.5,2.2-10.1,1.1l10.5-10.5c1.3-1.3,1.3-3.5,0-4.8s-3.5-1.3-4.8,0l-10.5,10.5c-1.1-3.6-.7-7.1,1.1-10.1,3.6-5.9,12-9.3,22.9-9.3h.2c0,11-3.3,19.5-9.3,23.1Z';

	private const ICONS = [
		'check'        => '<polyline points="216 72 104 184 48 128" fill="none" stroke="currentColor" stroke-width="20" stroke-linecap="round" stroke-linejoin="round"/>',
		'check-circle' => '<circle cx="128" cy="128" r="96" fill="none" stroke="currentColor" stroke-width="16"/><polyline points="172 100 113 156 84 128" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/>',
		'rocket'       => '<path d="M208,144V216a8,8,0,0,1-8,8H56a8,8,0,0,1-8-8V144" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/><path d="M48,144,128,40l80,104" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/><circle cx="128" cy="128" r="16" fill="currentColor"/>',
		'sparkle'      => '<path d="M197.6,90.13l-39.39-13.13L145.07,37.6a8,8,0,0,0-15.16,0L116.79,77,77.4,90.13a8,8,0,0,0,0,15.16L116.79,118.42l13.12,39.39a8,8,0,0,0,15.16,0l13.13-39.39L197.6,105.29A8,8,0,0,0,197.6,90.13Z" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/><polyline points="184 24 184 56 216 56 184 56 184 88" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/><polyline points="56 152 56 176 80 176 56 176 56 200" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/>',
		'gear'         => '<circle cx="128" cy="128" r="40" fill="none" stroke="currentColor" stroke-width="16"/><path d="M130.05,206.11c-1.34,0-2.69,0-4,0L94,224a104.59,104.59,0,0,1-34.83-20.1L59,168c-.84-1.06-1.64-2.13-2.41-3.23L20.7,157.86a103.6,103.6,0,0,1,0-59.73L56.55,91.27c.77-1.1,1.57-2.17,2.41-3.23L59.13,52.1A104.59,104.59,0,0,1,94,32l32,17.89c1.34,0,2.69,0,4,0L162,32a104.59,104.59,0,0,1,34.84,20.1L196.87,88c.84,1.06,1.64,2.13,2.41,3.23l35.85,6.86a103.6,103.6,0,0,1,0,59.73l-35.85,6.86c-.77,1.1-1.57,2.17-2.41,3.23l.05,35.86A104.59,104.59,0,0,1,162,224Z" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/>',
		'key'          => '<circle cx="160" cy="96" r="40" fill="none" stroke="currentColor" stroke-width="16"/><path d="M131.7,124.29,40,216v24H64V216H88V192h24L131.7,172.3" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/>',
		'upload'       => '<polyline points="86 82 128 40 170 82" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/><line x1="128" y1="152" x2="128" y2="40" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/><path d="M216,152v56a8,8,0,0,1-8,8H48a8,8,0,0,1-8-8V152" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/>',
		'file-html'    => '<path d="M48,112V40a8,8,0,0,1,8-8H152l56,56v24" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/><polyline points="152 32 152 88 208 88" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/><path d="M28,224V160H40a20,20,0,0,1,0,40H28" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/><path d="M124,160v64m24-32H100" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/><path d="M180,160v64h32" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/>',
		'paint-brush'  => '<path d="M120.42,75.67c11.13-21,30.49-44.42,67.58-43.66,40,.81,52.81,21.66,52,52-.74,29.13-22.75,49.82-44,55.94" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/><path d="M84,160c-12,0-24,8-24,32,0,16-12,24-24,24,16,16,40,24,56,24,32,0,56-24,56-56A56,56,0,0,0,84,160Z" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/><path d="M152,72S133,98,99,121s-58.16,29.41-58.16,29.41" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/>',
		'arrow-right'  => '<line x1="40" y1="128" x2="216" y2="128" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/><polyline points="144 56 216 128 144 200" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/>',
		'warning'      => '<line x1="128" y1="104" x2="128" y2="144" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/><path d="M114.16,40.84,19.36,204A16,16,0,0,0,33.2,228H222.8a16,16,0,0,0,13.84-24L141.84,40.84A16,16,0,0,0,114.16,40.84Z" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/><circle cx="128" cy="180" r="12" fill="currentColor"/>',
		'folder'       => '<path d="M32,200V64a8,8,0,0,1,8-8H93.34a8,8,0,0,1,4.8,1.6l27.72,20.8a8,8,0,0,0,4.8,1.6H216a8,8,0,0,1,8,8v112a8,8,0,0,1-8,8H40A8,8,0,0,1,32,200Z" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/>',
		'crown'        => '<path d="M48,176H208L235.55,89.4a8,8,0,0,0-11.85-9.18L176,108,131.59,46.78a8,8,0,0,0-13.18,0L74,108,26.3,80.22A8,8,0,0,0,14.45,89.4Z" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/><line x1="48" y1="208" x2="208" y2="208" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/>',
		'house'        => '<path d="M152,208V160a8,8,0,0,0-8-8H112a8,8,0,0,0-8,8v48a8,8,0,0,1-8,8H40a8,8,0,0,1-8-8V115.54a8,8,0,0,1,2.62-5.92l88-80.34a8,8,0,0,1,10.77,0l88,80.34a8,8,0,0,1,2.62,5.92V208a8,8,0,0,1-8,8H160A8,8,0,0,1,152,208Z" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/>',
		'tree-view'    => '<rect x="40" y="40" width="64" height="48" rx="8" fill="none" stroke="currentColor" stroke-width="16"/><rect x="152" y="88" width="64" height="48" rx="8" fill="none" stroke="currentColor" stroke-width="16"/><rect x="152" y="168" width="64" height="48" rx="8" fill="none" stroke="currentColor" stroke-width="16"/><path d="M104,64h24a8,8,0,0,1,8,8v120a8,8,0,0,0,8,8h8" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/><line x1="136" y1="112" x2="152" y2="112" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round"/>',
		'eye'          => '<path d="M128,56C48,56,16,128,16,128s32,72,112,72,112-72,112-72S208,56,128,56Z" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/><circle cx="128" cy="128" r="40" fill="none" stroke="currentColor" stroke-width="16"/>',
	];

	public static function svg( string $name, int $size = 20, string $extra_class = '' ): string {
		$body = self::ICONS[ $name ] ?? '';
		if ( $body === '' ) {
			return '';
		}
		$cls = trim( 'rt-icon ' . $extra_class );
		return sprintf(
			'<svg class="%s" xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 256 256" aria-hidden="true" focusable="false">%s</svg>',
			esc_attr( $cls ),
			$size,
			$size,
			$body
		);
	}

	/**
	 * Replanta brand mark (icon-only) inlined as SVG. Use this anywhere the
	 * brand identity should appear (admin headers, tags, badges) — never use
	 * a generic leaf icon to represent Replanta.
	 *
	 * @param int    $size_px     Square bounding box; aspect ratio is preserved.
	 * @param string $extra_class Extra CSS classes appended to the svg.
	 */
	public static function brand( int $size_px = 20, string $extra_class = '' ): string {
		$cls = trim( 'rt-brand-mark ' . $extra_class );
		// Native logo is 70.9 × 56.3. Scale width to size, compute height.
		$w = $size_px;
		$h = (int) round( $size_px * ( 56.3 / 70.9 ) );
		return sprintf(
			'<svg class="%s" xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 70.9 56.3" aria-hidden="true" focusable="false"><path fill="currentColor" d="%s"/></svg>',
			esc_attr( $cls ),
			$w,
			$h,
			esc_attr( self::BRAND_MARK_PATH )
		);
	}

	/** Path to the full brand SVG file (logo + wordmark) for use in template parts. */
	public static function brand_file_url(): string {
		return trailingslashit( get_template_directory_uri() ) . 'replantav3-ico.svg';
	}
}
