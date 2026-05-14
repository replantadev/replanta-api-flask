<?php
/**
 * Page generator — orchestrates AI, validation, file write, sync.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Page_Generator {

	private RT_AI_Provider $provider;
	private RT_Content_Sync $sync;

	public function __construct( ?RT_AI_Provider $provider = null, ?RT_Content_Sync $sync = null ) {
		$settings = get_option( 'rt_theme_settings', [] );
		$id = (string) ( $settings['ai_provider'] ?? 'anthropic' );
		$this->provider = $provider ?? ( $id === 'openai' ? new RT_OpenAI() : new RT_Anthropic() );
		$this->sync     = $sync ?? new RT_Content_Sync();
	}

	/**
	 * Generate a complete page from prompt.
	 *
	 * @return array{ ok: bool, path?: string, mdx?: string, frontmatter?: array<string,mixed>, error?: string }
	 */
	public function generate_page( string $prompt, string $lang = 'es', ?string $slug_hint = null ): array {
		$context = $this->build_context( $lang );
		$slug_line = $slug_hint ? "Use slug: {$slug_hint}" : 'Pick a SEO-friendly slug.';

		$user_prompt = <<<P
Create a full page in {$lang}.

USER REQUEST:
{$prompt}

CONSTRAINTS:
- {$slug_line}
- Use 3 to 6 components.
- Open with <Hero>. Close with <CTA>.
- Keep copy concise, scannable.
- Suggest 2 internal links from sitemap if relevant.

OUTPUT: STRICT MDX only. No commentary.
P;

		$res = $this->provider->generate( $user_prompt, $context, [ 'max_tokens' => 4096 ] );
		if ( ! $res['ok'] ) {
			return [ 'ok' => false, 'error' => $res['error'] ?? 'AI error' ];
		}

		$mdx = $this->extract_mdx( (string) $res['content'] );
		$split = RT_MDX_Parser::split( $mdx );
		$front = $split['frontmatter'];

		if ( empty( $front['title'] ) || empty( $front['slug'] ) ) {
			return [ 'ok' => false, 'error' => 'AI output missing title/slug', 'mdx' => $mdx ];
		}

		$slug = sanitize_title( (string) $front['slug'] );
		$rel  = $lang . '/' . $slug . '.mdx';
		$front['slug'] = $slug;
		$front['lang'] = $lang;

		$abs = $this->sync->write_file( $rel, $front, $split['body'] );

		return [ 'ok' => true, 'path' => $rel, 'abs' => $abs, 'mdx' => $mdx, 'frontmatter' => $front ];
	}

	/** Generate or rewrite a single section. */
	public function generate_section( string $prompt, string $component_type = 'Hero', array $context_extra = [] ): array {
		$context = $this->build_context( (string) ( $context_extra['lang'] ?? 'es' ) );
		$context = array_merge( $context, $context_extra );

		$user = <<<P
Generate ONE component block of type <{$component_type}>.

USER REQUEST:
{$prompt}

OUTPUT: just the JSX component (with id="" and inner Markdown). No frontmatter, no other text.
P;
		$res = $this->provider->generate( $user, $context, [ 'max_tokens' => 1500 ] );
		if ( ! $res['ok'] ) {
			return [ 'ok' => false, 'error' => $res['error'] ?? 'AI error' ];
		}
		return [ 'ok' => true, 'block' => trim( (string) $res['content'] ) ];
	}

	/** Inline rewrite of a fragment. */
	public function rewrite( string $fragment, string $instruction, string $lang = 'es' ): array {
		$user = <<<P
Rewrite the following fragment in {$lang}. Instruction: {$instruction}

FRAGMENT:
{$fragment}

OUTPUT: only the rewritten text, no quotes, no commentary.
P;
		$res = $this->provider->generate( $user, [], [ 'max_tokens' => 800, 'temperature' => 0.6 ] );
		if ( ! $res['ok'] ) {
			return [ 'ok' => false, 'error' => $res['error'] ?? 'AI error' ];
		}
		return [ 'ok' => true, 'text' => trim( (string) $res['content'] ) ];
	}

	/**
	 * Rewrite a complete MDX file (frontmatter + body), preserving structure.
	 * Used by the HTML importer's "Improve with AI" feature.
	 */
	public function rewrite_full_mdx( string $mdx, string $instruction, string $lang = 'es' ): array {
		$user = <<<P
You are improving an MDX page in language "{$lang}".

INSTRUCTION:
{$instruction}

CURRENT MDX (preserve frontmatter keys, you may translate values; keep slug; keep all <Component id="..."> tags. Replace <Content> with more specific components like <Hero>/<Features>/<CTA>/<FAQ>/<Pricing>/<Testimonials> when the content fits the pattern):

{$mdx}

OUTPUT: strict MDX only, no commentary, no fences.
P;
		$res = $this->provider->generate( $user, $this->build_context( $lang ), [ 'max_tokens' => 4096, 'temperature' => 0.4 ] );
		if ( ! $res['ok'] ) {
			return [ 'ok' => false, 'error' => $res['error'] ?? 'AI error' ];
		}
		return [ 'ok' => true, 'mdx' => $this->extract_mdx( (string) $res['content'] ) ];
	}

	/**
	 * Rewrite a single MDX block (one component or one markdown chunk).
	 * Returns ONLY the rewritten block snippet, no surrounding context.
	 */
	public function rewrite_block_mdx( string $block_raw, string $instruction, string $lang = 'es' ): array {
		$instr = $instruction !== '' ? $instruction : 'Improve clarity and tone, keep meaning and structure.';
		$user  = <<<P
You are editing ONE block of an MDX page in language "{$lang}".

INSTRUCTION:
{$instr}

RULES:
- If the block is a JSX component (<Hero>, <Features>, <CTA>, <FAQ>, <Pricing>, <Testimonials>, <Stats>, <Content>), keep the SAME tag and the same id="" attribute. You MAY edit other attributes (title, subtitle, cta, href) and inner markdown.
- If the block is plain markdown, return improved markdown.
- If the block is a [shortcode], return it unchanged unless instruction explicitly asks otherwise.
- Do NOT add new components around it. Output ONE block only.
- Do NOT include frontmatter. No code fences. No commentary.

CURRENT BLOCK:
{$block_raw}

OUTPUT: the rewritten block only.
P;
		$res = $this->provider->generate( $user, $this->build_context( $lang ), [ 'max_tokens' => 1500, 'temperature' => 0.45 ] );
		if ( ! $res['ok'] ) {
			return [ 'ok' => false, 'error' => $res['error'] ?? 'AI error' ];
		}
		return [ 'ok' => true, 'mdx' => $this->extract_mdx( (string) $res['content'] ) ];
	}

	/**
	 * Generate a brand-new MDX block of the requested template type from a free-form prompt.
	 * $template can be: Hero, Features, Stats, CTA, FAQ, Pricing, Testimonials, Content, Markdown, Shortcode.
	 */
	public function generate_block_mdx( string $template, string $prompt, string $lang = 'es' ): array {
		$template = $template !== '' ? $template : 'Markdown';
		$user = <<<P
You are generating ONE MDX block in language "{$lang}".

DESIRED BLOCK TYPE: {$template}
USER PROMPT:
{$prompt}

RULES:
- If type is a component (Hero/Features/Stats/CTA/FAQ/Pricing/Testimonials/Content), output exactly: <{$template} id="..." [other attrs]>...inner markdown...</{$template}>
- Choose a stable, kebab-case id reflecting the content.
- For Features/Pricing/Stats use a markdown bullet list inside.
- For FAQ use **Question?**\\nAnswer pairs separated by blank lines.
- For Markdown type, output a single heading + paragraph + optional list. No JSX.
- For Shortcode type, output ONE [shortcode ...] line.
- No frontmatter. No fences. No commentary. ONE block only.

OUTPUT:
P;
		$res = $this->provider->generate( $user, $this->build_context( $lang ), [ 'max_tokens' => 1500, 'temperature' => 0.55 ] );
		if ( ! $res['ok'] ) {
			return [ 'ok' => false, 'error' => $res['error'] ?? 'AI error' ];
		}
		return [ 'ok' => true, 'mdx' => $this->extract_mdx( (string) $res['content'] ) ];
	}

	/** Translate full MDX into target lang preserving structure. */
	public function translate( string $mdx, string $target_lang ): array {
		$user = <<<P
Translate this MDX file to language "{$target_lang}".
- Preserve frontmatter keys; translate VALUES (title, meta_description) but NOT slug (regenerate slug for target lang).
- Update lang field.
- Keep all <Component id="..."> tags and ids unchanged.
- Translate all body text.

INPUT MDX:
{$mdx}

OUTPUT: strict MDX only.
P;
		$res = $this->provider->generate( $user, [], [ 'max_tokens' => 4096, 'temperature' => 0.3 ] );
		if ( ! $res['ok'] ) {
			return [ 'ok' => false, 'error' => $res['error'] ?? 'AI error' ];
		}
		return [ 'ok' => true, 'mdx' => $this->extract_mdx( (string) $res['content'] ) ];
	}

	/** @return array<string,mixed> */
	private function build_context( string $lang ): array {
		$theme_json = RT_THEME_DIR . 'theme.json';
		$tokens = is_readable( $theme_json )
			? ( json_decode( (string) file_get_contents( $theme_json ), true )['settings'] ?? [] )
			: [];

		$patterns = [
			'Hero', 'Stats', 'Features', 'CTA', 'FAQ', 'Pricing', 'Testimonials', 'Content',
		];

		$sitemap = array_map(
			static fn( $f ) => [ 'slug' => $f['slug'] ?? '', 'title' => $f['title'] ?? '', 'lang' => $f['lang'] ],
			$this->sync->list_files()
		);

		return [
			'lang'     => $lang,
			'tokens'   => $tokens,
			'patterns' => $patterns,
			'sitemap'  => $sitemap,
		];
	}

	private function extract_mdx( string $raw ): string {
		// Strip code fences if model wrapped them.
		if ( preg_match( '/```(?:mdx|markdown)?\s*([\s\S]+?)```/', $raw, $m ) ) {
			return trim( $m[1] );
		}
		return trim( $raw );
	}
}
