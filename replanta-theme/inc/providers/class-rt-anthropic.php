<?php
/**
 * Anthropic Claude provider.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Anthropic implements RT_AI_Provider {

	private const API_URL = 'https://api.anthropic.com/v1/messages';
	private const VERSION = '2023-06-01';

	public function id(): string {
		return 'anthropic';
	}

	public function display_name(): string {
		return 'Anthropic Claude';
	}

	public function generate( string $prompt, array $context = [], array $options = [] ): array {
		$api_key = $this->get_api_key();
		if ( $api_key === '' ) {
			return [ 'ok' => false, 'error' => 'Anthropic API key missing' ];
		}

		$model       = (string) ( $options['model'] ?? 'claude-sonnet-4-5' );
		$max_tokens  = (int)    ( $options['max_tokens'] ?? 4096 );
		$temperature = (float)  ( $options['temperature'] ?? 0.7 );
		$system      = (string) ( $options['system'] ?? $this->default_system( $context ) );

		$body = [
			'model'       => $model,
			'max_tokens'  => $max_tokens,
			'temperature' => $temperature,
			'system'      => $system,
			'messages'    => [
				[ 'role' => 'user', 'content' => $prompt ],
			],
		];

		$response = wp_remote_post(
			self::API_URL,
			[
				'timeout' => 90,
				'headers' => [
					'x-api-key'         => $api_key,
					'anthropic-version' => self::VERSION,
					'content-type'      => 'application/json',
				],
				'body'    => wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return [ 'ok' => false, 'error' => $response->get_error_message() ];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 || ! is_array( $json ) ) {
			$err = $json['error']['message'] ?? "HTTP {$code}";
			return [ 'ok' => false, 'error' => (string) $err, 'raw' => is_array( $json ) ? $json : [] ];
		}

		$content = '';
		foreach ( (array) ( $json['content'] ?? [] ) as $block ) {
			if ( ( $block['type'] ?? '' ) === 'text' ) {
				$content .= (string) ( $block['text'] ?? '' );
			}
		}

		return [ 'ok' => true, 'content' => $content, 'raw' => $json ];
	}

	private function get_api_key(): string {
		$settings = get_option( 'rt_theme_settings', [] );
		$key = (string) ( $settings['ai_api_key'] ?? '' );
		if ( $key === '' && defined( 'RT_ANTHROPIC_API_KEY' ) ) {
			return (string) RT_ANTHROPIC_API_KEY;
		}
		return $key;
	}

	/** @param array<string,mixed> $context */
	private function default_system( array $context ): string {
		$tokens   = wp_json_encode( $context['tokens'] ?? [] );
		$patterns = wp_json_encode( $context['patterns'] ?? [] );
		$sitemap  = wp_json_encode( $context['sitemap'] ?? [] );
		return <<<SYS
You are a senior web copywriter & front-end engineer for the Replanta theme.
You output STRICT MDX with YAML frontmatter — nothing else, no prose around it.

Rules:
- Frontmatter must include: title, slug, lang, template, patterns (array of strings), seo.meta_description, seo.focus_keyword.
- Body uses ONLY these JSX components mapped to registered patterns: <Hero>, <Stats>, <Features>, <CTA>, <FAQ>, <Pricing>, <Testimonials>, <Content>.
- Each component MUST have a stable id="" attribute (kebab-case).
- Inside components, use plain Markdown (## headings, lists, links, **bold**).
- Do NOT invent CSS, classes, inline styles, or HTML beyond the listed components.
- Match the brand voice: clear, sustainable, data-backed, no fluff.

Theme tokens (use semantic names, not hex): {$tokens}
Available patterns: {$patterns}
Existing sitemap (for internal links): {$sitemap}
SYS;
	}
}
