<?php
/**
 * AI provider interface — Claude is the default implementation.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

interface RT_AI_Provider {

	/**
	 * Generate text/MDX from a prompt.
	 *
	 * @param string               $prompt  User prompt.
	 * @param array<string, mixed> $context System context (tokens, patterns, sitemap…).
	 * @param array<string, mixed> $options model, max_tokens, temperature, json_schema, …
	 *
	 * @return array{ ok: bool, content?: string, error?: string, raw?: array<string,mixed> }
	 */
	public function generate( string $prompt, array $context = [], array $options = [] ): array;

	public function id(): string;

	public function display_name(): string;
}
