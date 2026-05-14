<?php
/**
 * OpenAI provider (fallback).
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_OpenAI implements RT_AI_Provider {

	private const API_URL = 'https://api.openai.com/v1/chat/completions';

	public function id(): string {
		return 'openai';
	}

	public function display_name(): string {
		return 'OpenAI';
	}

	public function generate( string $prompt, array $context = [], array $options = [] ): array {
		$api_key = $this->get_api_key();
		if ( $api_key === '' ) {
			return [ 'ok' => false, 'error' => 'OpenAI API key missing' ];
		}

		$model       = (string) ( $options['model'] ?? 'gpt-4o' );
		$max_tokens  = (int)    ( $options['max_tokens'] ?? 4096 );
		$temperature = (float)  ( $options['temperature'] ?? 0.7 );
		$system      = (string) ( $options['system'] ?? 'You output strict MDX as instructed.' );

		$body = [
			'model'       => $model,
			'max_tokens'  => $max_tokens,
			'temperature' => $temperature,
			'messages'    => [
				[ 'role' => 'system', 'content' => $system ],
				[ 'role' => 'user',   'content' => $prompt ],
			],
		];

		$response = wp_remote_post(
			self::API_URL,
			[
				'timeout' => 90,
				'headers' => [
					'authorization' => 'Bearer ' . $api_key,
					'content-type'  => 'application/json',
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
			return [ 'ok' => false, 'error' => $json['error']['message'] ?? "HTTP {$code}" ];
		}

		$content = (string) ( $json['choices'][0]['message']['content'] ?? '' );
		return [ 'ok' => true, 'content' => $content, 'raw' => $json ];
	}

	private function get_api_key(): string {
		$settings = get_option( 'rt_theme_settings', [] );
		$prov = (string) ( $settings['ai_provider'] ?? 'anthropic' );
		if ( $prov === 'openai' ) {
			return (string) ( $settings['ai_api_key'] ?? '' );
		}
		return defined( 'RT_OPENAI_API_KEY' ) ? (string) RT_OPENAI_API_KEY : '';
	}
}
