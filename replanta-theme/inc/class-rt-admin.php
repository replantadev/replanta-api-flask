<?php
/**
 * Admin page "Replanta AI" — mounts the React app.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Admin {

	public const PAGE_SLUG = 'replanta-ai';
	public const CAPABILITY = 'edit_pages';

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function menu(): void {
		add_menu_page(
			__( 'Replanta', 'replanta-theme' ),
			__( 'Replanta', 'replanta-theme' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			[ $this, 'render' ],
			'dashicons-superhero',
			2
		);
		// Rename the auto-created first child so the menu doesn't show "Replanta" twice.
		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Panel', 'replanta-theme' ),
			__( 'Panel', 'replanta-theme' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);
	}

	public function render(): void {
		$has_build = is_readable( RT_THEME_DIR . 'assets/dist/admin.js' );

		echo '<div class="wrap" style="max-width:none;margin:0;padding:0;">';

		if ( ! $has_build ) {
			$this->render_fallback();
		} else {
			echo '<div id="replanta-ai-root" class="rt-admin-root"></div>';
		}

		echo '</div>';
	}

	private function render_fallback(): void {
		$installer   = new RT_Installer();
		$installed   = $installer->is_installed();
		$rest_url    = esc_url_raw( rest_url( 'replanta/v1/' ) );
		$nonce       = wp_create_nonce( 'wp_rest' );
		$settings    = (array) get_option( 'rt_theme_settings', [] );
		$has_key     = ! empty( $settings['ai_api_key'] );
		$pages_dir   = trailingslashit( RT_THEME_DIR ) . 'content';
		$import_dir  = ( new RT_HTML_Importer() )->import_dir();
		$import_count = count( ( new RT_HTML_Importer() )->list_sources() );
		$pages_count = 0;
		if ( is_dir( $pages_dir ) ) {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $pages_dir, RecursiveDirectoryIterator::SKIP_DOTS ) );
			foreach ( $it as $f ) {
				if ( $f->isFile() && preg_match( '/\.mdx?$/i', $f->getPathname() ) ) {
					$pages_count++;
				}
			}
		}
		$icon = static fn( string $n, int $s = 20 ) => RT_Icons::svg( $n, $s );
		?>
		<style>
			.rt-fallback{font-family:-apple-system,system-ui,sans-serif;max-width:880px;margin:32px auto;padding:0 20px;color:#0E1A14}
			.rt-fallback h1{font-size:30px;margin:0 0 6px;font-weight:600;letter-spacing:-.01em;display:flex;align-items:center;gap:12px}
			.rt-fallback .rt-tag{display:inline-flex;align-items:center;gap:6px;background:#1F6F45;color:#fff;font-size:11px;padding:4px 10px;border-radius:99px;letter-spacing:.08em;text-transform:uppercase;margin-bottom:14px;font-weight:500}
			.rt-fallback .rt-card{background:#fff;border:1px solid #E5E1D6;border-radius:14px;padding:24px;margin-bottom:14px;box-shadow:0 1px 2px rgba(14,26,20,.04)}
			.rt-fallback .rt-card h2{margin:0 0 6px;font-size:17px;font-weight:600;display:flex;align-items:center;gap:8px}
			.rt-fallback .rt-card p{margin:0 0 14px;color:#5C665A;font-size:14px;line-height:1.5}
			.rt-fallback button.rt-btn,.rt-fallback .rt-btn{display:inline-flex;align-items:center;gap:8px;background:#1F6F45;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;transition:background .15s}
			.rt-fallback button.rt-btn:hover{background:#185536}
			.rt-fallback button.rt-btn[disabled]{opacity:.5;cursor:wait}
			.rt-fallback button.rt-btn.rt-secondary{background:#fff;color:#1F6F45;border:1px solid #1F6F45}
			.rt-fallback button.rt-btn.rt-secondary:hover{background:#F4F1E9}
			.rt-fallback .rt-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px}
			.rt-fallback label{font-size:14px;color:#0E1A14}
			.rt-fallback input[type=text],.rt-fallback input[type=password],.rt-fallback select,.rt-fallback textarea{padding:9px 11px;border:1px solid #D9D6CA;border-radius:7px;font-size:14px;min-width:280px;font-family:inherit}
			.rt-fallback textarea{min-width:100%;min-height:120px;font-family:ui-monospace,Menlo,monospace;font-size:12px}
			.rt-fallback .rt-status{margin-top:10px;font-size:13px;color:#5C665A;min-height:18px;display:flex;align-items:center;gap:6px}
			.rt-fallback .rt-status.ok{color:#1F6F45}
			.rt-fallback .rt-status.err{color:#b91c1c}
			.rt-fallback .rt-step{display:flex;align-items:flex-start;gap:14px}
			.rt-fallback .rt-num{flex:0 0 32px;height:32px;border-radius:99px;background:#F1EFE7;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:13px;color:#5C665A}
			.rt-fallback .rt-num.done{background:#1F6F45;color:#fff}
			.rt-fallback code{background:#F1EFE7;padding:2px 6px;border-radius:4px;font-size:12px;font-family:ui-monospace,Menlo,monospace}
			.rt-fallback .rt-hint{font-size:12px;color:#8A9088;margin-top:6px}
			.rt-fallback .rt-icon{flex:0 0 auto;vertical-align:middle}
			.rt-fallback details{margin-top:10px}
			.rt-fallback details summary{cursor:pointer;font-size:13px;color:#1F6F45;font-weight:500}
			.rt-fallback .rt-source-list{font-size:12px;color:#5C665A;background:#F8F6EF;padding:10px;border-radius:6px;max-height:160px;overflow:auto;margin-top:8px;font-family:ui-monospace,Menlo,monospace}
			.rt-fallback .rt-empty{font-style:italic;color:#8A9088}
			/* Block editor */
			.rt-blocks{display:flex;flex-direction:column;gap:0;margin-top:14px;border:1px solid #E5E1D6;border-radius:10px;overflow:hidden;background:#FBF9F2}
			.rt-blocks .rt-empty-blocks{padding:32px;text-align:center;color:#8A9088;font-size:14px}
			.rt-block{position:relative;background:#fff;border-bottom:1px solid #EFECE2;padding:14px 16px;display:flex;gap:12px;align-items:flex-start;transition:background .12s}
			.rt-block:last-child{border-bottom:none}
			.rt-block:hover{background:#FCFBF6}
			.rt-block.rt-editing{background:#FFF8E7;box-shadow:inset 3px 0 0 #C49A2A}
			.rt-block .rt-block-handle{flex:0 0 26px;display:flex;flex-direction:column;align-items:center;gap:2px;color:#8A9088}
			.rt-block .rt-block-handle .rt-block-num{font-size:11px;font-weight:600;color:#5C665A;background:#F1EFE7;border-radius:99px;padding:2px 7px;min-width:22px;text-align:center}
			.rt-block .rt-block-body{flex:1;min-width:0}
			.rt-block .rt-block-meta{display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap}
			.rt-block .rt-badge{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;padding:3px 8px;border-radius:99px;background:#1F6F45;color:#fff}
			.rt-block .rt-badge.rt-md{background:#5C665A}
			.rt-block .rt-badge.rt-sc{background:#C49A2A}
			.rt-block .rt-block-id{font-size:11px;color:#8A9088;font-family:ui-monospace,Menlo,monospace}
			.rt-block .rt-block-preview{font-size:13px;color:#3A4540;line-height:1.45;word-break:break-word;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
			.rt-block .rt-block-actions{flex:0 0 auto;display:flex;gap:4px;align-items:center;opacity:.5;transition:opacity .12s}
			.rt-block:hover .rt-block-actions,.rt-block.rt-editing .rt-block-actions{opacity:1}
			.rt-block .rt-block-actions button{background:transparent;border:1px solid transparent;color:#5C665A;padding:6px;border-radius:6px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:all .12s}
			.rt-block .rt-block-actions button:hover{background:#F1EFE7;color:#1F6F45;border-color:#D9D6CA}
			.rt-block .rt-block-actions button.rt-danger:hover{color:#b91c1c;border-color:#fecaca;background:#fef2f2}
			.rt-block .rt-block-edit{margin-top:10px;display:none}
			.rt-block.rt-editing .rt-block-edit{display:block}
			.rt-block textarea.rt-block-raw{min-height:160px;font-family:ui-monospace,Menlo,monospace;font-size:12px;width:100%;min-width:0}
			.rt-block .rt-edit-row{display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;align-items:center}
			.rt-block .rt-block-preview-html{margin-top:10px;border:1px dashed #D9D6CA;border-radius:8px;background:#fff;padding:12px;font-size:13px;display:none}
			.rt-block.rt-show-preview .rt-block-preview-html{display:block}
			.rt-block-inserter{position:relative;height:0;overflow:visible;display:flex;justify-content:center;z-index:2}
			.rt-block-inserter button{position:absolute;top:-12px;width:24px;height:24px;border-radius:99px;border:1px solid #D9D6CA;background:#fff;color:#5C665A;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:14px;line-height:1;opacity:0;transition:all .12s}
			.rt-block-inserter:hover button,.rt-block-inserter.rt-open button{opacity:1;background:#1F6F45;color:#fff;border-color:#1F6F45;transform:scale(1.05)}
			.rt-inserter-panel{display:none;position:absolute;top:18px;left:50%;transform:translateX(-50%);background:#fff;border:1px solid #D9D6CA;border-radius:10px;padding:12px;box-shadow:0 6px 24px rgba(14,26,20,.12);min-width:320px;z-index:10}
			.rt-block-inserter.rt-open .rt-inserter-panel{display:block}
			.rt-inserter-panel h4{margin:0 0 8px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#5C665A}
			.rt-inserter-panel .rt-tpl-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-bottom:10px}
			.rt-inserter-panel .rt-tpl-grid button{background:#F8F6EF;border:1px solid #E5E1D6;color:#0E1A14;padding:8px 10px;border-radius:6px;font-size:12px;cursor:pointer;text-align:left;font-weight:500;transition:all .12s}
			.rt-inserter-panel .rt-tpl-grid button:hover{background:#1F6F45;color:#fff;border-color:#1F6F45}
			.rt-inserter-panel input,.rt-inserter-panel textarea{width:100%;min-width:0!important;margin-bottom:6px}
			.rt-inserter-panel .rt-tpl-actions{display:flex;gap:6px;justify-content:flex-end}
			.rt-inserter-tabs{display:flex;gap:4px;margin-bottom:10px;border-bottom:1px solid #E5E1D6}
			.rt-inserter-tabs button{flex:1;background:none;border:0;border-bottom:2px solid transparent;color:#5C665A;padding:6px 8px;font-size:12px;font-weight:600;cursor:pointer;text-transform:uppercase;letter-spacing:.04em}
			.rt-inserter-tabs button.rt-active{color:#1F6F45;border-bottom-color:#1F6F45}
			.rt-inserter-pane{display:none}
			.rt-inserter-pane.rt-active{display:block}
			.rt-lib-grid{display:flex;flex-direction:column;gap:6px;max-height:280px;overflow-y:auto}
			.rt-lib-grid .rt-lib-row{display:flex;gap:6px;align-items:center;padding:6px 8px;border:1px solid #E5E1D6;border-radius:6px;background:#F8F6EF}
			.rt-lib-grid .rt-lib-row strong{font-size:12px;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
			.rt-lib-grid .rt-lib-row .rt-lib-meta{font-size:11px;color:#5C665A;white-space:nowrap}
			.rt-lib-grid .rt-lib-row button{padding:4px 8px;font-size:11px;border-radius:5px;border:1px solid #D9D6CA;background:#fff;cursor:pointer;font-weight:600}
			.rt-lib-grid .rt-lib-row button.rt-copy{background:#1F6F45;color:#fff;border-color:#1F6F45}
			.rt-lib-grid .rt-lib-row button.rt-ref{background:#f97316;color:#fff;border-color:#f97316}
			.rt-lib-list{display:flex;flex-direction:column;gap:6px;margin-top:10px}
			.rt-lib-list .rt-lib-item{display:flex;gap:8px;align-items:center;padding:8px 10px;border:1px solid #E5E1D6;border-radius:6px;background:#F8F6EF}
			.rt-lib-list .rt-lib-item strong{flex:1;min-width:0;font-size:13px}
			.rt-lib-list .rt-lib-item .rt-lib-meta{font-size:12px;color:#5C665A}
			.rt-lib-list .rt-lib-item button{padding:4px 10px;font-size:12px;border-radius:5px;border:1px solid #D9D6CA;background:#fff;cursor:pointer}
			.rt-lib-list .rt-lib-item button.rt-danger{background:#b91c1c;color:#fff;border-color:#b91c1c}
			.rt-include-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;border-radius:99px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
			/* Sitemap discovery */
			.rt-sm-results{display:flex;flex-direction:column;gap:8px;margin-top:10px}
			.rt-sm-engine{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;background:linear-gradient(135deg,#1F6F45,#34a06b);color:#fff;border-radius:99px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;box-shadow:0 2px 8px rgba(31,111,69,.25)}
			.rt-sm-engine.rt-yoast{background:linear-gradient(135deg,#a4286a,#d63384)}
			.rt-sm-engine.rt-rankmath{background:linear-gradient(135deg,#724ec0,#9c6ade)}
			.rt-sm-engine.rt-aioseo{background:linear-gradient(135deg,#005ae0,#3b82f6)}
			.rt-sm-engine.rt-wp-core{background:linear-gradient(135deg,#21759b,#2271b1)}
			.rt-sm-card{display:grid;grid-template-columns:auto 1fr auto auto;gap:10px;align-items:center;padding:10px 12px;border:1px solid #E5E1D6;border-radius:8px;background:#F8F6EF;transition:all .15s}
			.rt-sm-card:hover{border-color:#1F6F45;background:#fff;box-shadow:0 2px 8px rgba(14,26,20,.05)}
			.rt-sm-card.rt-imported{background:#ecfdf5;border-color:#34a06b}
			.rt-sm-card .rt-sm-icon{width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;background:#fff;border-radius:8px;border:1px solid #E5E1D6;font-size:18px}
			.rt-sm-card .rt-sm-info{min-width:0}
			.rt-sm-card .rt-sm-title{font-weight:700;font-size:14px;color:#0E1A14;margin:0 0 2px}
			.rt-sm-card .rt-sm-meta{font-size:12px;color:#5C665A;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
			.rt-sm-card .rt-sm-meta a{color:#5C665A;text-decoration:none;border-bottom:1px dashed #B8B5A8;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11px}
			.rt-sm-card .rt-sm-count{font-variant-numeric:tabular-nums;font-weight:600;color:#1F6F45;background:#dcfce7;padding:2px 10px;border-radius:99px;font-size:12px;white-space:nowrap}
			.rt-sm-card .rt-sm-import{padding:6px 14px;font-size:12px;font-weight:600;border-radius:6px;border:1px solid #1F6F45;background:#1F6F45;color:#fff;cursor:pointer;transition:all .12s}
			.rt-sm-card .rt-sm-import:hover{background:#185735}
			.rt-sm-card .rt-sm-import:disabled{opacity:.5;cursor:wait}
			.rt-sm-card .rt-sm-import.rt-done{background:#34a06b;border-color:#34a06b}
			.rt-sm-bulk{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#0E1A14;color:#F8F6EF;border-radius:8px;margin-bottom:6px}
			.rt-sm-bulk button{padding:6px 14px;font-size:12px;font-weight:600;border-radius:6px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);color:#fff;cursor:pointer}
			.rt-sm-bulk button:hover{background:rgba(255,255,255,.15)}
			/* Editor toolbar (responsive switcher + audit) */
			.rt-be-toolbar{display:flex;justify-content:space-between;align-items:center;gap:10px;background:#0E1A14;color:#F8F6EF;padding:8px 12px;border-radius:10px;margin:10px 0 0}
			.rt-bp-switch{display:inline-flex;background:rgba(255,255,255,.08);border-radius:8px;padding:3px;gap:2px}
			.rt-bp-switch .rt-bp{background:transparent;border:none;color:#F8F6EF;padding:5px 12px;border-radius:6px;font-size:14px;cursor:pointer;transition:all .12s;line-height:1}
			.rt-bp-switch .rt-bp:hover{background:rgba(255,255,255,.08)}
			.rt-bp-switch .rt-bp.rt-active{background:#1F6F45;color:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2)}
			.rt-be-toolbar .rt-btn{padding:6px 12px;font-size:12px}
			.rt-blocks.rt-bp-tablet{max-width:820px;margin:14px auto;border-radius:18px;box-shadow:0 0 0 6px #0E1A14,0 0 0 8px #2a3a30,0 20px 40px rgba(0,0,0,.18);transition:max-width .25s ease}
			.rt-blocks.rt-bp-mobile{max-width:430px;margin:14px auto;border-radius:32px;box-shadow:0 0 0 8px #0E1A14,0 0 0 10px #2a3a30,0 20px 40px rgba(0,0,0,.22);transition:max-width .25s ease}
			.rt-blocks.rt-bp-tablet .rt-block,.rt-blocks.rt-bp-mobile .rt-block{font-size:13px}
			.rt-blocks.rt-bp-mobile .rt-block-actions{flex-wrap:wrap;justify-content:flex-end}
			/* Audit panel */
			.rt-audit-panel{margin-top:10px;padding:14px 16px;background:#fff;border:1px solid #E5E1D6;border-radius:12px;box-shadow:0 1px 2px rgba(14,26,20,.04)}
			.rt-audit-head{display:flex;align-items:center;gap:14px;margin-bottom:12px;flex-wrap:wrap}
			.rt-audit-score{flex:0 0 auto;width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:22px;color:#fff;background:#1F6F45}
			.rt-audit-score.rt-warn{background:#C49A2A}
			.rt-audit-score.rt-bad{background:#b91c1c}
			.rt-audit-stats{display:flex;gap:14px;flex-wrap:wrap;font-size:13px;color:#5C665A}
			.rt-audit-stats span strong{color:#0E1A14}
			.rt-audit-list{margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px}
			.rt-audit-list li{display:flex;gap:8px;align-items:flex-start;padding:8px 12px;background:#FBF9F2;border-left:3px solid #C49A2A;border-radius:6px;font-size:13px;color:#3A4540}
			.rt-audit-list li.rt-err{border-left-color:#b91c1c;background:#fef2f2;color:#7f1d1d}
			.rt-audit-list li.rt-ok{border-left-color:#1F6F45;background:#ecfdf5;color:#14532d}
			.rt-audit-list li .rt-cat{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;background:rgba(0,0,0,.08);padding:2px 6px;border-radius:99px;flex:0 0 auto;align-self:center}
			/* Inspector panel inside editing block */
			.rt-block.rt-editing .rt-inspector{display:block}
			.rt-inspector{display:none;margin-top:10px;padding:12px;background:#FBF9F2;border:1px solid #E5E1D6;border-radius:8px}
			.rt-inspector h5{margin:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#5C665A;font-weight:700}
			.rt-inspector-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px}
			.rt-insp-field{display:flex;flex-direction:column;gap:3px}
			.rt-insp-field label{font-size:11px;color:#5C665A;font-weight:600}
			.rt-insp-field input[type=text],.rt-insp-field select,.rt-insp-field input[type=color]{padding:6px 8px;border:1px solid #D9D6CA;border-radius:6px;background:#fff;font-size:13px;font-family:inherit;min-width:0;width:100%;box-sizing:border-box}
			.rt-insp-field input[type=color]{padding:2px;height:32px;cursor:pointer}
			.rt-insp-actions{margin-top:10px;display:flex;gap:8px;justify-content:flex-end}
			.rt-insp-actions .rt-btn{padding:6px 12px;font-size:12px}
			.rt-insp-tag{display:inline-block;background:#1F6F45;color:#fff;font-size:10px;font-weight:700;letter-spacing:.05em;padding:2px 7px;border-radius:99px;margin-bottom:6px}
			.rt-insp-na{color:#8A9088;font-size:12px;font-style:italic}
			.rt-toast{position:fixed;bottom:20px;right:20px;background:#0E1A14;color:#fff;padding:12px 18px;border-radius:8px;font-size:13px;box-shadow:0 8px 24px rgba(0,0,0,.2);z-index:9999;animation:rt-toast-in .25s ease}
			.rt-toast.ok{background:#1F6F45}.rt-toast.err{background:#b91c1c}
			@keyframes rt-toast-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
		</style>
		<div class="rt-fallback">
			<span class="rt-tag"><?php echo RT_Icons::brand( 14 ); ?>Replanta AI</span>
			<h1><?php esc_html_e( 'Configuración inicial', 'replanta-theme' ); ?></h1>
			<p style="color:#5C665A;margin-bottom:24px;font-size:15px">
				<?php esc_html_e( 'Instala el contenido base, conecta tu IA y migra tu sitio actual desde aquí.', 'replanta-theme' ); ?>
			</p>

			<div class="rt-card">
				<div class="rt-step">
					<div class="rt-num <?php echo $installed ? 'done' : ''; ?>"><?php echo $installed ? $icon( 'check', 16 ) : '1'; ?></div>
					<div style="flex:1">
						<h2><?php echo $icon( 'rocket' ); ?><?php esc_html_e( 'Instalar contenido', 'replanta-theme' ); ?></h2>
						<p><?php esc_html_e( 'Crea las carpetas content/{idioma}/ y, opcionalmente, dos páginas demo.', 'replanta-theme' ); ?></p>
						<?php if ( ! $installed ) : ?>
							<div class="rt-row">
								<label><?php esc_html_e( 'Idioma:', 'replanta-theme' ); ?>
									<select id="rt-install-lang">
										<option value="es">Español</option>
										<option value="en">English</option>
										<option value="ca">Català</option>
										<option value="fr">Français</option>
										<option value="de">Deutsch</option>
										<option value="it">Italiano</option>
										<option value="pt">Português</option>
									</select>
								</label>
								<label><input type="checkbox" id="rt-install-seed" checked> <?php esc_html_e( 'Crear contenido demo', 'replanta-theme' ); ?></label>
							</div>
							<button type="button" class="rt-btn" id="rt-install-btn"><?php echo $icon( 'rocket', 16 ); ?><?php esc_html_e( 'Instalar ahora', 'replanta-theme' ); ?></button>
						<?php else : ?>
							<p style="color:#1F6F45;display:flex;align-items:center;gap:6px"><?php echo $icon( 'check-circle', 18 ); ?><strong><?php esc_html_e( 'Instalado.', 'replanta-theme' ); ?></strong> <?php echo (int) $pages_count; ?> <?php esc_html_e( 'archivo(s) MDX.', 'replanta-theme' ); ?></p>
							<button type="button" class="rt-btn rt-secondary" id="rt-resync-btn"><?php esc_html_e( 'Re-sincronizar', 'replanta-theme' ); ?></button>
						<?php endif; ?>
						<div class="rt-status" id="rt-install-status"></div>
					</div>
				</div>
			</div>

			<div class="rt-card">
				<div class="rt-step">
					<div class="rt-num <?php echo $has_key ? 'done' : ''; ?>"><?php echo $has_key ? $icon( 'check', 16 ) : '2'; ?></div>
					<div style="flex:1">
						<h2><?php echo $icon( 'key' ); ?><?php esc_html_e( 'API key de IA', 'replanta-theme' ); ?></h2>
						<p><?php esc_html_e( 'Necesaria para generar y reescribir páginas con prompts.', 'replanta-theme' ); ?></p>
						<div class="rt-row">
							<label><?php esc_html_e( 'Proveedor:', 'replanta-theme' ); ?>
								<select id="rt-ai-provider">
									<option value="anthropic" <?php selected( $settings['ai_provider'] ?? 'anthropic', 'anthropic' ); ?>>Anthropic Claude</option>
									<option value="openai" <?php selected( $settings['ai_provider'] ?? '', 'openai' ); ?>>OpenAI</option>
								</select>
							</label>
						</div>
						<div class="rt-row">
							<input type="password" id="rt-ai-key" placeholder="<?php echo $has_key ? esc_attr__( 'ya configurada (sobrescribir)', 'replanta-theme' ) : 'sk-ant-... / sk-...'; ?>">
							<button type="button" class="rt-btn" id="rt-savekey-btn"><?php esc_html_e( 'Guardar', 'replanta-theme' ); ?></button>
						</div>
						<div class="rt-status" id="rt-savekey-status"></div>
						<hr style="margin:14px 0;border:0;border-top:1px solid #e5e7eb">
						<p style="margin:4px 0 6px"><strong><?php esc_html_e( 'PageSpeed Insights API key (opcional)', 'replanta-theme' ); ?></strong></p>
						<p class="rt-hint"><?php esc_html_e( 'Necesaria para el comparador de rendimiento. Sin clave: 5 consultas/día. Con clave gratuita de Google: 25.000/día.', 'replanta-theme' ); ?></p>
						<div class="rt-row">
							<input type="password" id="rt-psi-key" placeholder="<?php echo ! empty( $settings['psi_api_key'] ) ? esc_attr__( 'ya configurada (sobrescribir)', 'replanta-theme' ) : 'AIza...'; ?>">
							<button type="button" class="rt-btn" id="rt-savepsi-btn"><?php esc_html_e( 'Guardar', 'replanta-theme' ); ?></button>
						</div>
						<div class="rt-status" id="rt-savepsi-status"></div>
					</div>
				</div>
			</div>

			<div class="rt-card">
				<div class="rt-step">
					<div class="rt-num"><?php echo $icon( 'upload', 16 ); ?></div>
					<div style="flex:1">
						<h2><?php echo $icon( 'file-html' ); ?><?php esc_html_e( 'Migrar sitio existente (HTML + CSS de Elementor)', 'replanta-theme' ); ?></h2>
						<p><?php esc_html_e( 'Sube tus HTML clonados y CSS personalizado a la carpeta de importación. Replanta los convierte en MDX editables y conserva tu CSS.', 'replanta-theme' ); ?></p>
						<p class="rt-hint"><?php esc_html_e( 'Carpeta:', 'replanta-theme' ); ?> <code><?php echo esc_html( $import_dir ); ?></code></p>
						<?php if ( $import_count > 0 ) : ?>
							<div class="rt-source-list" id="rt-import-list"></div>
							<div class="rt-row" style="margin-top:12px">
								<label><?php esc_html_e( 'Idioma destino:', 'replanta-theme' ); ?>
									<select id="rt-import-lang">
										<option value="es">Español</option>
										<option value="en">English</option>
										<option value="ca">Català</option>
										<option value="fr">Français</option>
										<option value="de">Deutsch</option>
										<option value="it">Italiano</option>
										<option value="pt">Português</option>
									</select>
								</label>
								<label><input type="checkbox" id="rt-import-css" checked> <?php esc_html_e( 'Importar CSS personalizado', 'replanta-theme' ); ?></label>
							</div>
							<button type="button" class="rt-btn" id="rt-import-btn"><?php echo $icon( 'upload', 16 ); ?><?php esc_html_e( 'Importar todo', 'replanta-theme' ); ?></button>
						<?php else : ?>
							<p class="rt-empty"><?php esc_html_e( 'Aún no hay archivos. Sube tus .html y .css por FTP a la carpeta de arriba y recarga.', 'replanta-theme' ); ?></p>
						<?php endif; ?>
						<details>
							<summary><?php esc_html_e( 'Pegar HTML directamente', 'replanta-theme' ); ?></summary>
							<div style="margin-top:10px">
								<div class="rt-row">
									<input type="text" id="rt-raw-slug" placeholder="<?php esc_attr_e( 'slug (ej: home)', 'replanta-theme' ); ?>">
									<input type="text" id="rt-raw-title" placeholder="<?php esc_attr_e( 'Título de la página', 'replanta-theme' ); ?>">
								</div>
								<textarea id="rt-raw-html" placeholder="<?php esc_attr_e( 'Pega aquí el HTML completo de una página…', 'replanta-theme' ); ?>"></textarea>
								<textarea id="rt-raw-css" placeholder="<?php esc_attr_e( '(Opcional) CSS personalizado a inyectar globalmente…', 'replanta-theme' ); ?>" style="margin-top:8px"></textarea>
								<div class="rt-row" style="margin-top:8px">
									<button type="button" class="rt-btn" id="rt-raw-btn"><?php echo $icon( 'arrow-right', 16 ); ?><?php esc_html_e( 'Convertir a MDX', 'replanta-theme' ); ?></button>
								</div>
							</div>
						</details>
						<details>
							<summary><?php esc_html_e( 'Importar desde URL', 'replanta-theme' ); ?></summary>
							<div style="margin-top:10px">
								<div class="rt-row">
									<input type="text" id="rt-url-input" placeholder="https://tu-sitio.com/pagina" style="min-width:380px">
									<input type="text" id="rt-url-slug" placeholder="<?php esc_attr_e( 'slug (auto si vacío)', 'replanta-theme' ); ?>">
									<label><input type="checkbox" id="rt-url-images" checked> <?php esc_html_e( 'Descargar imágenes', 'replanta-theme' ); ?></label>
									<button type="button" class="rt-btn" id="rt-url-btn"><?php esc_html_e( 'Importar URL', 'replanta-theme' ); ?></button>
								</div>
							</div>
						</details>
						<details open>
							<summary><strong><?php esc_html_e( 'Importar en modo Mirror (recomendado para Elementor / diseños complejos)', 'replanta-theme' ); ?></strong></summary>
							<div style="margin-top:10px">
								<p class="rt-hint"><?php esc_html_e( 'Clona el HTML del <body> y descarga sus CSS al servidor. La página queda visualmente idéntica al original. Editable como un único bloque HTML.', 'replanta-theme' ); ?></p>
								<div class="rt-row">
									<input type="text" id="rt-mirror-url" placeholder="https://tu-sitio.com/pagina" style="min-width:380px">
									<input type="text" id="rt-mirror-slug" placeholder="<?php esc_attr_e( 'slug (auto si vacío)', 'replanta-theme' ); ?>">
									<button type="button" class="rt-btn" id="rt-mirror-btn"><?php echo $icon( 'paint-brush', 16 ); ?><?php esc_html_e( 'Clonar Mirror', 'replanta-theme' ); ?></button>
								</div>
								<div class="rt-status" id="rt-mirror-status"></div>
							</div>
						</details>
						<details>
							<summary><?php esc_html_e( 'Crawler de sitemap.xml (multi-página)', 'replanta-theme' ); ?></summary>
							<div style="margin-top:10px">
								<div class="rt-row">
									<input type="text" id="rt-sm-input" placeholder="https://tu-sitio.com  o  /sitemap_index.xml" style="min-width:380px">
									<button type="button" class="rt-btn rt-secondary" id="rt-sm-discover"><?php esc_html_e( 'Detectar sitemaps', 'replanta-theme' ); ?></button>
								</div>
								<p class="rt-hint"><?php esc_html_e( 'Pega la URL del sitio (recomendado) o un sitemap. Detectamos automáticamente Yoast, Rank Math, AIO SEO, WordPress Core y BetterDocs.', 'replanta-theme' ); ?></p>
								<div id="rt-sm-results" class="rt-sm-results"></div>
								<div class="rt-row" id="rt-sm-options" style="display:none;flex-wrap:wrap;align-items:center;gap:10px;margin-top:8px">
									<label><?php esc_html_e( 'Límite por sitemap:', 'replanta-theme' ); ?> <input type="number" id="rt-sm-limit" value="20" min="1" max="500" style="min-width:80px"></label>
									<label><input type="checkbox" id="rt-sm-images" checked> <?php esc_html_e( 'Descargar imágenes a la Media Library', 'replanta-theme' ); ?></label>
									<span class="rt-hint" style="margin:0"><?php esc_html_e( 'Compatible con BetterDocs: las URLs /docs/ se importan como páginas Replanta sin tocar el CPT de BetterDocs.', 'replanta-theme' ); ?></span>
								</div>
							</div>
						</details>
						<div class="rt-status" id="rt-import-status"></div>
					</div>
				</div>
			</div>

			<div class="rt-card">
				<div class="rt-step">
					<div class="rt-num"><?php echo $icon( 'sparkle', 16 ); ?></div>
					<div style="flex:1">
						<h2><?php echo $icon( 'sparkle' ); ?><?php esc_html_e( 'Mejorar páginas con IA', 'replanta-theme' ); ?></h2>
						<p><?php esc_html_e( 'Selecciona una página importada y reescríbela con IA: clarifica el copy, transforma <Content> en <Hero>/<Features>/<CTA>/<FAQ> cuando aplique.', 'replanta-theme' ); ?></p>
						<div class="rt-row">
							<select id="rt-ai-page" style="min-width:380px"><option value=""><?php esc_html_e( '— elige una página importada —', 'replanta-theme' ); ?></option></select>
							<button type="button" class="rt-btn rt-secondary" id="rt-ai-refresh-btn"><?php esc_html_e( 'Refrescar', 'replanta-theme' ); ?></button>
						</div>
						<div class="rt-row">
							<input type="text" id="rt-ai-instruction" placeholder="<?php esc_attr_e( '(Opcional) Instrucción extra: ej. tono más profesional, añadir FAQ…', 'replanta-theme' ); ?>" style="min-width:480px">
						</div>
						<div class="rt-row">
							<button type="button" class="rt-btn" id="rt-ai-btn"><?php echo $icon( 'sparkle', 16 ); ?><?php esc_html_e( 'Mejorar con IA', 'replanta-theme' ); ?></button>
							<button type="button" class="rt-btn rt-secondary" id="rt-diff-btn"><?php esc_html_e( 'Ver diff con original', 'replanta-theme' ); ?></button>
						</div>
						<div class="rt-status" id="rt-ai-status"></div>
						<div id="rt-diff-out" style="display:none;margin-top:14px"></div>
					</div>
				</div>
			</div>

			<div class="rt-card">
				<div class="rt-step">
					<div class="rt-num"><?php echo $icon( 'rocket', 16 ); ?></div>
					<div style="flex:1">
						<h2><?php echo $icon( 'rocket' ); ?><?php esc_html_e( 'Redirecciones (301)', 'replanta-theme' ); ?></h2>
						<p><?php esc_html_e( 'Las acciones de promoción ("Adoptar URL", "Reemplazar en menús", "Front page") están integradas en cada página: ve a Replanta → Páginas y úsalas desde la fila o desde la barra superior al editar/ver una página. Aquí gestionas las redirecciones 301 globales que se generan al adoptar URLs.', 'replanta-theme' ); ?></p>
						<div class="rt-row">
							<a class="rt-btn" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . RT_CPT_Page::POST_TYPE ) ); ?>"><?php esc_html_e( 'Ir a Páginas', 'replanta-theme' ); ?></a>
							<button type="button" class="rt-btn rt-secondary" id="rt-promote-redirects-toggle"><?php esc_html_e( 'Ver / añadir redirecciones', 'replanta-theme' ); ?></button>
						</div>
						<div id="rt-promote-redirects" style="display:none;margin-top:12px"></div>
						<div class="rt-status" id="rt-promote-status"></div>
					</div>
				</div>
			</div>

			<div class="rt-card">
				<div class="rt-step">
					<div class="rt-num"><?php echo $icon( 'gear', 16 ); ?></div>
					<div style="flex:1">
						<h2><?php echo $icon( 'gear' ); ?><?php esc_html_e( 'Editor de bloques', 'replanta-theme' ); ?></h2>
						<p><?php esc_html_e( 'Construye tus páginas por bloques: añade Hero, Features, FAQ, CTA, markdown libre o shortcodes WordPress entre filas. Reordena, duplica, edita o reescribe con IA cualquier bloque sin tocar los demás.', 'replanta-theme' ); ?></p>
						<div class="rt-row">
							<select id="rt-be-page" style="min-width:380px"><option value=""><?php esc_html_e( '— elige una página —', 'replanta-theme' ); ?></option></select>
							<button type="button" class="rt-btn rt-secondary" id="rt-be-load"><?php esc_html_e( 'Cargar bloques', 'replanta-theme' ); ?></button>
							<button type="button" class="rt-btn rt-secondary" id="rt-be-refresh"><?php esc_html_e( 'Refrescar lista', 'replanta-theme' ); ?></button>
						</div>
						<div class="rt-be-toolbar" id="rt-be-toolbar" style="display:none">
							<div class="rt-bp-switch" role="tablist" aria-label="<?php esc_attr_e( 'Vista por dispositivo', 'replanta-theme' ); ?>">
								<button type="button" class="rt-bp rt-active" data-bp="desktop" title="<?php esc_attr_e( 'Escritorio (1440px)', 'replanta-theme' ); ?>">🖥</button>
								<button type="button" class="rt-bp" data-bp="tablet" title="<?php esc_attr_e( 'Tablet (768px)', 'replanta-theme' ); ?>">📱</button>
								<button type="button" class="rt-bp" data-bp="mobile" title="<?php esc_attr_e( 'Móvil (390px)', 'replanta-theme' ); ?>">📲</button>
							</div>
							<button type="button" class="rt-btn rt-secondary" id="rt-be-audit"><?php esc_html_e( 'Auditar página', 'replanta-theme' ); ?></button>
						</div>
						<div id="rt-audit-panel" class="rt-audit-panel" style="display:none"></div>
						<div class="rt-status" id="rt-be-status"></div>
						<div id="rt-be-blocks" class="rt-blocks" style="display:none"></div>
					</div>
				</div>
			</div>

			<div class="rt-card">
				<div class="rt-step">
					<div class="rt-num"><?php echo $icon( 'gear', 16 ); ?></div>
					<div style="flex:1">
						<h2><?php echo $icon( 'gear' ); ?><?php esc_html_e( 'Cabecera y pie', 'replanta-theme' ); ?></h2>
						<p><?php esc_html_e( 'Controles globales tipo Astra. Sobreescribe por página añadiendo `header_transparent`, `hide_header` o `hide_footer` en el frontmatter.', 'replanta-theme' ); ?></p>
						<div id="rt-layout-form" style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
							<label style="display:flex;align-items:center;gap:8px;font-size:13px"><input type="checkbox" data-lk="header_sticky"> <?php esc_html_e( 'Cabecera pegajosa al hacer scroll', 'replanta-theme' ); ?></label>
							<label style="display:flex;align-items:center;gap:8px;font-size:13px"><input type="checkbox" data-lk="header_transparent"> <?php esc_html_e( 'Cabecera transparente sobre el primer bloque', 'replanta-theme' ); ?></label>
							<label style="display:flex;align-items:center;gap:8px;font-size:13px"><input type="checkbox" data-lk="footer_compact"> <?php esc_html_e( 'Pie compacto (1 columna)', 'replanta-theme' ); ?></label>
							<label style="display:flex;flex-direction:column;gap:4px;font-size:13px"><span><?php esc_html_e( 'Altura del logo (px)', 'replanta-theme' ); ?></span><input type="number" data-lk="logo_height" min="16" max="96" style="min-width:0;width:100%"></label>
							<label style="display:flex;flex-direction:column;gap:4px;font-size:13px"><span><?php esc_html_e( 'ID del logo (Media Library)', 'replanta-theme' ); ?></span><input type="number" data-lk="logo_id" min="0" style="min-width:0;width:100%" placeholder="0 = SVG por defecto"></label>
							<label style="display:flex;flex-direction:column;gap:4px;font-size:13px"><span><?php esc_html_e( 'Tagline (opcional)', 'replanta-theme' ); ?></span><input type="text" data-lk="tagline" style="min-width:0;width:100%" placeholder="<?php esc_attr_e( 'AI · Eco · Web', 'replanta-theme' ); ?>"></label>
							<label style="display:flex;flex-direction:column;gap:4px;font-size:13px"><span><?php esc_html_e( 'CTA — texto', 'replanta-theme' ); ?></span><input type="text" data-lk="cta_label" style="min-width:0;width:100%" placeholder="Empezar gratis"></label>
							<label style="display:flex;flex-direction:column;gap:4px;font-size:13px"><span><?php esc_html_e( 'CTA — URL', 'replanta-theme' ); ?></span><input type="text" data-lk="cta_href" style="min-width:0;width:100%" placeholder="/contacto"></label>
							<label style="display:flex;flex-direction:column;gap:4px;font-size:13px"><span><?php esc_html_e( 'Color de fondo (#hex, opcional)', 'replanta-theme' ); ?></span><input type="text" data-lk="header_bg" style="min-width:0;width:100%" placeholder="#ffffff"></label>
							<label style="display:flex;flex-direction:column;gap:4px;font-size:13px"><span><?php esc_html_e( 'Color de texto (#hex, opcional)', 'replanta-theme' ); ?></span><input type="text" data-lk="header_color" style="min-width:0;width:100%" placeholder="#0E1A14"></label>
							<label style="display:flex;flex-direction:column;gap:4px;font-size:13px"><span><?php esc_html_e( 'Twitter/X (URL)', 'replanta-theme' ); ?></span><input type="text" data-lk="social_twitter" style="min-width:0;width:100%"></label>
							<label style="display:flex;flex-direction:column;gap:4px;font-size:13px"><span><?php esc_html_e( 'LinkedIn (URL)', 'replanta-theme' ); ?></span><input type="text" data-lk="social_linkedin" style="min-width:0;width:100%"></label>
							<label style="display:flex;flex-direction:column;gap:4px;font-size:13px"><span><?php esc_html_e( 'GitHub (URL)', 'replanta-theme' ); ?></span><input type="text" data-lk="social_github" style="min-width:0;width:100%"></label>
						</div>
						<div class="rt-row" style="margin-top:12px">
							<button type="button" class="rt-btn" id="rt-layout-save"><?php esc_html_e( 'Guardar cabecera/pie', 'replanta-theme' ); ?></button>
							<a class="rt-btn rt-secondary" href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>" target="_blank"><?php esc_html_e( 'Editar menús', 'replanta-theme' ); ?></a>
							<a class="rt-btn rt-secondary" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank"><?php esc_html_e( 'Vista previa del sitio', 'replanta-theme' ); ?></a>
						</div>
						<div class="rt-status" id="rt-layout-status"></div>
						<p class="rt-hint"><?php esc_html_e( 'Asigna el menú "Menú principal" y "Menú de pie" en Apariencia → Menús.', 'replanta-theme' ); ?></p>
					</div>
				</div>
			</div>

			<div class="rt-card">
				<div class="rt-step">
					<div class="rt-num"><?php echo $icon( 'folder', 16 ); ?></div>
					<div style="flex:1">
						<h2><?php echo $icon( 'folder' ); ?><?php esc_html_e( 'Biblioteca de bloques', 'replanta-theme' ); ?></h2>
						<p><?php esc_html_e( 'Guarda bloques que reutilizarás en muchas páginas. Usa "Copia" para insertar contenido independiente o "Ref" para crear una referencia sincronizada (al editarla aquí cambia en todas las páginas que la usen).', 'replanta-theme' ); ?></p>
						<div class="rt-row">
							<button type="button" class="rt-btn rt-secondary" id="rt-lib-refresh"><?php esc_html_e( 'Refrescar lista', 'replanta-theme' ); ?></button>
							<span class="rt-hint"><?php esc_html_e( 'Para guardar un bloque a la biblioteca, usa el botón 📚 dentro del editor de bloques.', 'replanta-theme' ); ?></span>
						</div>
						<div id="rt-lib-list" class="rt-lib-list"></div>
						<div class="rt-status" id="rt-lib-status"></div>
					</div>
				</div>
			</div>

			<div class="rt-card">
				<div class="rt-step">
					<div class="rt-num"><?php echo $icon( 'paint-brush', 16 ); ?></div>
					<div style="flex:1">
						<h2><?php echo $icon( 'paint-brush' ); ?><?php esc_html_e( 'CSS personalizado', 'replanta-theme' ); ?></h2>
						<p><?php esc_html_e( 'Edita el CSS global del tema (heredado de Elementor o el que tú quieras añadir).', 'replanta-theme' ); ?></p>
						<textarea id="rt-css" placeholder="/* Tu CSS personalizado */"></textarea>
						<div class="rt-row" style="margin-top:8px">
							<button type="button" class="rt-btn" id="rt-css-btn"><?php esc_html_e( 'Guardar CSS', 'replanta-theme' ); ?></button>
							<button type="button" class="rt-btn rt-secondary" id="rt-css-load-btn"><?php esc_html_e( 'Cargar actual', 'replanta-theme' ); ?></button>
						</div>
						<div class="rt-status" id="rt-css-status"></div>
					</div>
				</div>
			</div>

			<div class="rt-card">
				<div class="rt-step">
					<div class="rt-num">5</div>
					<div style="flex:1">
						<h2><?php echo $icon( 'sparkle' ); ?><?php esc_html_e( 'Editor visual (opcional)', 'replanta-theme' ); ?></h2>
						<p><?php esc_html_e( 'Para el Composer con UI completa, ejecuta en el servidor:', 'replanta-theme' ); ?></p>
						<p><code>cd wp-content/themes/replanta-theme &amp;&amp; pnpm install &amp;&amp; pnpm build</code></p>
						<p class="rt-hint"><?php esc_html_e( 'Mientras tanto puedes generar páginas con WP-CLI:', 'replanta-theme' ); ?> <code>wp replanta generate "tu prompt" --lang=es</code></p>
					</div>
				</div>
			</div>
		</div>

		<script>
		(function(){
			var rest=<?php echo wp_json_encode( $rest_url ); ?>;
			var nonce=<?php echo wp_json_encode( $nonce ); ?>;
			function call(path,method,data,cb){
				var m=(method||'GET').toUpperCase();
				var opts={method:m,headers:{'X-WP-Nonce':nonce,'Content-Type':'application/json'}};
				if(data&&m!=='GET'&&m!=='HEAD')opts.body=JSON.stringify(data);
				fetch(rest+path,opts).then(function(r){return r.json().then(function(j){return{ok:r.ok,data:j}})}).then(cb).catch(function(e){cb({ok:false,data:{error:e.message}})});
			}
			function setStatus(id,msg,cls){var el=document.getElementById(id);if(!el)return;el.textContent=msg;el.className='rt-status '+(cls||'')}

			var ib=document.getElementById('rt-install-btn');
			if(ib){ib.addEventListener('click',function(){
				ib.disabled=true;setStatus('rt-install-status','Instalando…');
				call('install','POST',{lang:document.getElementById('rt-install-lang').value,seed_demo:document.getElementById('rt-install-seed').checked},function(r){
					if(r.ok&&r.data.ok){setStatus('rt-install-status','Instalado. Recargando…','ok');setTimeout(function(){location.reload()},800)}
					else{ib.disabled=false;setStatus('rt-install-status','Error: '+(r.data.error||'desconocido'),'err')}
				});
			})}

			var rb=document.getElementById('rt-resync-btn');
			if(rb){rb.addEventListener('click',function(){
				rb.disabled=true;setStatus('rt-install-status','Sincronizando…');
				call('sync','POST',{},function(r){rb.disabled=false;setStatus('rt-install-status',r.ok?('Sync OK ('+(r.data.scanned||0)+' archivos)'):'Error',r.ok?'ok':'err')});
			})}

			var sk=document.getElementById('rt-savekey-btn');
			if(sk){sk.addEventListener('click',function(){
				var k=document.getElementById('rt-ai-key').value.trim();
				if(!k){setStatus('rt-savekey-status','Introduce una API key','err');return}
				sk.disabled=true;setStatus('rt-savekey-status','Guardando…');
				call('settings','POST',{ai_api_key:k,ai_provider:document.getElementById('rt-ai-provider').value},function(r){
					sk.disabled=false;
					if(r.ok){setStatus('rt-savekey-status','Guardada','ok');document.getElementById('rt-ai-key').value=''}
					else setStatus('rt-savekey-status','Error: '+(r.data.error||''),'err');
				});
			})}

			var spsi=document.getElementById('rt-savepsi-btn');
			if(spsi){spsi.addEventListener('click',function(){
				var k=document.getElementById('rt-psi-key').value.trim();
				if(!k){setStatus('rt-savepsi-status','Introduce una API key','err');return}
				spsi.disabled=true;setStatus('rt-savepsi-status','Guardando…');
				call('settings','POST',{psi_api_key:k},function(r){
					spsi.disabled=false;
					if(r.ok){setStatus('rt-savepsi-status','Guardada','ok');document.getElementById('rt-psi-key').value=''}
					else setStatus('rt-savepsi-status','Error: '+(r.data.error||''),'err');
				});
			})}

			// Importer list
			var listEl=document.getElementById('rt-import-list');
			if(listEl){
				call('import/html','GET',null,function(r){
					if(!r.ok){listEl.textContent='Error cargando lista';return}
					var srcs=r.data.sources||[];
					if(!srcs.length){listEl.innerHTML='<em>(vacío)</em>';return}
					listEl.innerHTML=srcs.map(function(s){return '<div>['+s.ext+'] '+s.path+' <span style="color:#8A9088">('+Math.round(s.size/1024)+' KB)</span></div>'}).join('');
				});
			}

			var imp=document.getElementById('rt-import-btn');
			if(imp){imp.addEventListener('click',function(){
				imp.disabled=true;setStatus('rt-import-status','Importando…');
				call('import/html','POST',{lang:document.getElementById('rt-import-lang').value,merge_css:document.getElementById('rt-import-css').checked},function(r){
					imp.disabled=false;
					if(r.ok&&r.data.ok){
						var msg=r.data.imported.length+' páginas importadas'+(r.data.css_saved?' + CSS guardado':'');
						if(r.data.errors&&r.data.errors.length)msg+=' ('+r.data.errors.length+' errores)';
						setStatus('rt-import-status',msg,'ok');
					}else setStatus('rt-import-status','Error: '+(r.data.error||'desconocido'),'err');
				});
			})}

			var raw=document.getElementById('rt-raw-btn');
			if(raw){raw.addEventListener('click',function(){
				var html=document.getElementById('rt-raw-html').value.trim();
				if(!html){setStatus('rt-import-status','Pega HTML primero','err');return}
				raw.disabled=true;setStatus('rt-import-status','Convirtiendo…');
				call('import/html/raw','POST',{
					html:html,
					custom_css:document.getElementById('rt-raw-css').value,
					slug:document.getElementById('rt-raw-slug').value,
					title:document.getElementById('rt-raw-title').value,
					lang:document.getElementById('rt-import-lang')?document.getElementById('rt-import-lang').value:'es'
				},function(r){
					raw.disabled=false;
					if(r.ok&&r.data.ok)setStatus('rt-import-status','Convertido: '+r.data.file,'ok');
					else setStatus('rt-import-status','Error: '+(r.data.error||''),'err');
				});
			})}

			// URL import
			var urlBtn=document.getElementById('rt-url-btn');
			if(urlBtn){urlBtn.addEventListener('click',function(){
				var u=document.getElementById('rt-url-input').value.trim();
				if(!u){setStatus('rt-import-status','Introduce URL','err');return}
				urlBtn.disabled=true;setStatus('rt-import-status','Descargando '+u+'…');
				call('import/url','POST',{
					url:u,
					slug:document.getElementById('rt-url-slug').value,
					download_images:document.getElementById('rt-url-images').checked,
					lang:document.getElementById('rt-import-lang')?document.getElementById('rt-import-lang').value:'es'
				},function(r){
					urlBtn.disabled=false;
					if(r.ok&&r.data.ok){setStatus('rt-import-status','Importado: '+r.data.file,'ok');refreshAiPages();}
					else setStatus('rt-import-status','Error: '+(r.data.error||'desconocido'),'err');
				});
			})}

			// Mirror import
			var mirBtn=document.getElementById('rt-mirror-btn');
			if(mirBtn){mirBtn.addEventListener('click',function(){
				var u=document.getElementById('rt-mirror-url').value.trim();
				if(!u){setStatus('rt-mirror-status','Introduce URL','err');return}
				mirBtn.disabled=true;setStatus('rt-mirror-status','Clonando '+u+' (descargando HTML y CSS, puede tardar)…');
				call('import/mirror','POST',{
					url:u,
					slug:document.getElementById('rt-mirror-slug').value,
					lang:document.getElementById('rt-import-lang')?document.getElementById('rt-import-lang').value:'es'
				},function(r){
					mirBtn.disabled=false;
					if(r.ok&&r.data.ok){
						setStatus('rt-mirror-status','Clonado ('+r.data.css_count+' hojas CSS). <a href="'+r.data.edit_url+'">Editar</a> · <a href="'+r.data.url+'" target="_blank" rel="noopener">Ver</a>','ok');
						if(typeof refreshAiPages==='function')refreshAiPages();
					}else{
						setStatus('rt-mirror-status','Error: '+(r.data.error||'desconocido'),'err');
					}
				});
			})}

			// Sitemap discovery + per-leaf import
			var smInput=document.getElementById('rt-sm-input');
			var smDiscover=document.getElementById('rt-sm-discover');
			var smResults=document.getElementById('rt-sm-results');
			var smOptions=document.getElementById('rt-sm-options');
			var SM_KIND_ICONS={page:'📄',post:'📝',doc:'📚',product:'🛒',category:'🗂️',tag:'🏷️',author:'👤',media:'🖼️',other:'🧩'};
			function smEngineBadge(eng){
				var labels={yoast:'Yoast',rankmath:'Rank Math',aioseo:'AIO SEO','wp-core':'WP Core',generic:'Sitemap',unknown:'?'};
				return '<span class="rt-sm-engine rt-'+escHtml(eng)+'">'+escHtml(labels[eng]||eng)+'</span>';
			}
			function smRender(data){
				if(!data||!data.sitemaps||!data.sitemaps.length){
					smResults.innerHTML='<em style="color:#8A9088">No se encontraron sitemaps. Prueba a pegar la URL exacta del sitemap.xml.</em>';
					smOptions.style.display='none';return;
				}
				smOptions.style.display='flex';
				var bulk='<div class="rt-sm-bulk">'+
					'<div>'+smEngineBadge(data.engine)+' <span style="opacity:.7;margin-left:8px;font-size:12px">'+escHtml(data.index_url||'')+'</span></div>'+
					'<button type="button" id="rt-sm-import-all">Importar todos</button>'+
				'</div>';
				var rows=data.sitemaps.map(function(s,idx){
					var ic=SM_KIND_ICONS[s.kind]||'🧩';
					var cnt=s.count>=0?(s.count+' URLs'):'?';
					return '<div class="rt-sm-card" data-idx="'+idx+'">'+
						'<div class="rt-sm-icon" title="'+escHtml(s.kind)+'">'+ic+'</div>'+
						'<div class="rt-sm-info">'+
							'<p class="rt-sm-title">'+escHtml(s.label)+'</p>'+
							'<div class="rt-sm-meta"><a href="'+escHtml(s.url)+'" target="_blank" rel="noopener">'+escHtml(s.url)+'</a></div>'+
						'</div>'+
						'<span class="rt-sm-count">'+escHtml(cnt)+'</span>'+
						'<button type="button" class="rt-sm-import" data-url="'+escHtml(s.url)+'">Importar</button>'+
					'</div>';
				}).join('');
				smResults.innerHTML=bulk+rows;
				smResults.querySelectorAll('.rt-sm-import').forEach(function(b){
					b.addEventListener('click',function(){smImportOne(b)});
				});
				var allBtn=document.getElementById('rt-sm-import-all');
				if(allBtn)allBtn.addEventListener('click',function(){
					var btns=smResults.querySelectorAll('.rt-sm-import:not(.rt-done)');
					if(!btns.length){toast('Nada que importar','err');return}
					if(!confirm('¿Importar '+btns.length+' sitemap(s)? Cada uno respeta el límite por sitemap configurado.'))return;
					(function step(i){
						if(i>=btns.length){toast('Todos importados','ok');refreshAiPages();return}
						smImportOne(btns[i],function(){step(i+1)});
					})(0);
				});
			}
			function smImportOne(btn,cb){
				var url=btn.getAttribute('data-url');
				var card=btn.closest('.rt-sm-card');
				btn.disabled=true;btn.textContent='Importando…';
				setStatus('rt-import-status','Crawleando '+url+'…');
				call('import/sitemap','POST',{
					url:url,
					limit:parseInt(document.getElementById('rt-sm-limit').value,10)||20,
					download_images:document.getElementById('rt-sm-images').checked,
					lang:document.getElementById('rt-import-lang')?document.getElementById('rt-import-lang').value:'es'
				},function(r){
					btn.disabled=false;
					if(r.ok&&r.data.ok){
						var n=(r.data.imported||[]).length;
						btn.textContent='✓ '+n+' importadas';btn.classList.add('rt-done');
						card.classList.add('rt-imported');
						setStatus('rt-import-status',n+' páginas importadas desde '+url,'ok');
						refreshAiPages();
					}else{
						btn.textContent='Reintentar';
						setStatus('rt-import-status','Error: '+(r.data.error||(r.data.errors&&r.data.errors[0])||''),'err');
						toast('Error al importar','err');
					}
					if(cb)cb();
				});
			}
			if(smDiscover)smDiscover.addEventListener('click',function(){
				var u=smInput.value.trim();
				if(!u){setStatus('rt-import-status','Pega la URL del sitio o sitemap','err');return}
				smDiscover.disabled=true;
				smResults.innerHTML='<em style="color:#8A9088">🔎 Detectando sitemaps en '+escHtml(u)+'…</em>';
				call('import/sitemap/discover','POST',{url:u},function(r){
					smDiscover.disabled=false;
					if(r.ok&&r.data.ok)smRender(r.data);
					else{
						var errs=(r.data&&r.data.errors)?r.data.errors.join(' · '):'no encontrado';
						smResults.innerHTML='<em style="color:#b91c1c">No se pudo detectar: '+escHtml(errs)+'</em>';
					}
				});
			});
			if(smInput)smInput.addEventListener('keydown',function(e){if(e.key==='Enter'&&smDiscover){e.preventDefault();smDiscover.click()}});

			// AI rewrite + diff
			function refreshAiPages(){
				var sel=document.getElementById('rt-ai-page');
				if(!sel)return;
				call('pages','GET',null,function(r){
					if(!r.ok)return;
					var imp=(r.data||[]).filter(function(p){return p.path&&p.path.indexOf('/imported/')!==-1});
					sel.innerHTML='<option value="">— elige una página importada —</option>'+imp.map(function(p){return '<option value="'+p.path+'">['+(p.lang||'?')+'] '+(p.title||p.slug||p.path)+'</option>'}).join('');
				});
			}
			refreshAiPages();
			var aiRef=document.getElementById('rt-ai-refresh-btn');
			if(aiRef)aiRef.addEventListener('click',refreshAiPages);

			var aiBtn=document.getElementById('rt-ai-btn');
			if(aiBtn){aiBtn.addEventListener('click',function(){
				var p=document.getElementById('rt-ai-page').value;
				if(!p){setStatus('rt-ai-status','Elige una página','err');return}
				aiBtn.disabled=true;setStatus('rt-ai-status','Reescribiendo con IA… (puede tardar 10-30s)');
				call('import/ai-rewrite','POST',{path:p,instruction:document.getElementById('rt-ai-instruction').value},function(r){
					aiBtn.disabled=false;
					if(r.ok&&r.data.ok)setStatus('rt-ai-status','Mejorada: '+r.data.path,'ok');
					else setStatus('rt-ai-status','Error: '+(r.data.error||''),'err');
				});
			})}

			var diffBtn=document.getElementById('rt-diff-btn');
			if(diffBtn){diffBtn.addEventListener('click',function(){
				var p=document.getElementById('rt-ai-page').value;
				if(!p){setStatus('rt-ai-status','Elige una página','err');return}
				diffBtn.disabled=true;setStatus('rt-ai-status','Cargando diff…');
				call('import/diff','POST',{path:p},function(r){
					diffBtn.disabled=false;
					var out=document.getElementById('rt-diff-out');
					if(r.ok&&r.data.ok){
						setStatus('rt-ai-status','Diff cargado','ok');
						out.style.display='grid';
						out.style.gridTemplateColumns='1fr 1fr';
						out.style.gap='12px';
						var srcOrig=encodeURIComponent(r.data.original_html||'<p>(sin source_url o no descargable)</p>');
						var srcRepl=encodeURIComponent('<style>body{font-family:system-ui;padding:20px}</style>'+(r.data.replanta_html||''));
						out.innerHTML='<div><h4 style="margin:0 0 6px">Original</h4><iframe sandbox style="width:100%;height:520px;border:1px solid #D9D6CA;border-radius:6px" src="data:text/html;charset=utf-8,'+srcOrig+'"></iframe></div><div><h4 style="margin:0 0 6px">Replanta</h4><iframe sandbox style="width:100%;height:520px;border:1px solid #D9D6CA;border-radius:6px" src="data:text/html;charset=utf-8,'+srcRepl+'"></iframe></div>';
					}else setStatus('rt-ai-status','Error diff: '+(r.data.error||''),'err');
				});
			})}

			// Custom CSS editor
			var cssLoad=document.getElementById('rt-css-load-btn');
			if(cssLoad){cssLoad.addEventListener('click',function(){
				cssLoad.disabled=true;
				call('custom-css','GET',null,function(r){
					cssLoad.disabled=false;
					if(r.ok){document.getElementById('rt-css').value=r.data.css||'';setStatus('rt-css-status','Cargado ('+(r.data.css||'').length+' bytes)','ok')}
					else setStatus('rt-css-status','Error','err');
				});
			})}

			var cssBtn=document.getElementById('rt-css-btn');
			if(cssBtn){cssBtn.addEventListener('click',function(){
				cssBtn.disabled=true;setStatus('rt-css-status','Guardando…');
				call('custom-css','POST',{css:document.getElementById('rt-css').value},function(r){
					cssBtn.disabled=false;
					setStatus('rt-css-status',r.ok?('Guardado ('+(r.data.bytes||0)+' bytes)'):'Error',r.ok?'ok':'err');
				});
			})}

			/* ============================ Block Editor ============================ */
			var beSel=document.getElementById('rt-be-page');
			var beWrap=document.getElementById('rt-be-blocks');
			var beStatus='rt-be-status';
			var bePath='';
			var beBlocks=[];
			var beTemplates=['Hero','Features','Stats','CTA','FAQ','Pricing','Testimonials','Content','Markdown','Shortcode'];

			function toast(msg,kind){
				var t=document.createElement('div');
				t.className='rt-toast '+(kind||'');
				t.textContent=msg;
				document.body.appendChild(t);
				setTimeout(function(){t.style.opacity='0';setTimeout(function(){t.remove()},250)},2400);
			}
			function escHtml(s){return String(s||'').replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
			function badgeFor(b){
				if(b.type==='component')return '<span class="rt-badge">'+escHtml(b.tag||'')+'</span>';
				if(b.type==='shortcode')return '<span class="rt-badge rt-sc">Shortcode</span>';
				return '<span class="rt-badge rt-md">Markdown</span>';
			}
			/* ---------- Inspector (container options) ---------- */
			var INSP_PAD=['','none','xs','sm','md','lg','xl'];
			var INSP_GAP=['','none','xs','sm','md','lg'];
			var INSP_MAX=['','full','wide','boxed','narrow'];
			var INSP_ALIGN=['','left','center','right'];
			function selOptions(arr,sel){
				return arr.map(function(v){
					var label=v===''?'— por defecto —':v;
					return '<option value="'+escHtml(v)+'"'+(String(sel||'')===v?' selected':'')+'>'+escHtml(label)+'</option>';
				}).join('');
			}
			function renderInspector(b,i){
				if(b.type!=='component'){
					return '<div class="rt-inspector"><span class="rt-insp-na">Las opciones de contenedor solo están disponibles en bloques tipo componente (Hero, Features, CTA, FAQ…).</span></div>';
				}
				var a=b.attrs||{};
				var bg=a.bg||'';
				var bgIsHex=/^#[0-9a-fA-F]{3,8}$/.test(bg);
				var bgPicker=bgIsHex?bg:'#ffffff';
				return '<div class="rt-inspector" data-i="'+i+'">'+
					'<span class="rt-insp-tag">'+escHtml(b.tag||'Section')+'</span>'+
					'<h5>Opciones del contenedor</h5>'+
					'<div class="rt-inspector-grid">'+
						'<div class="rt-insp-field"><label>id (anchor)</label><input type="text" data-k="id" value="'+escHtml(a.id||'')+'" placeholder="seccion-1"></div>'+
						'<div class="rt-insp-field"><label>className</label><input type="text" data-k="className" value="'+escHtml(a.className||a['class']||'')+'" placeholder="mi-clase"></div>'+
						'<div class="rt-insp-field"><label>title</label><input type="text" data-k="title" value="'+escHtml(a.title||'')+'"></div>'+
						'<div class="rt-insp-field"><label>max-width</label><select data-k="maxWidth">'+selOptions(INSP_MAX,a.maxWidth||a.width||'')+'</select></div>'+
						'<div class="rt-insp-field"><label>padding (desktop)</label><select data-k="pad">'+selOptions(INSP_PAD,a.pad||'')+'</select></div>'+
						'<div class="rt-insp-field"><label>padding (móvil)</label><select data-k="padMobile">'+selOptions(INSP_PAD,a.padMobile||'')+'</select></div>'+
						'<div class="rt-insp-field"><label>gap</label><select data-k="gap">'+selOptions(INSP_GAP,a.gap||'')+'</select></div>'+
						'<div class="rt-insp-field"><label>alineación</label><select data-k="align">'+selOptions(INSP_ALIGN,a.align||'')+'</select></div>'+
						'<div class="rt-insp-field"><label>color de fondo</label><input type="color" data-k="bg" data-initial="'+escHtml(bgPicker)+'" value="'+escHtml(bgPicker)+'"></div>'+
						'<div class="rt-insp-field"><label>fondo (libre, sobreescribe)</label><input type="text" data-k="bgRaw" value="'+escHtml(bgIsHex?'':bg)+'" placeholder="ej: #F8F6EF, var(--brand), url(...)"></div>'+
					'</div>'+
					'<div class="rt-insp-actions">'+
						'<button type="button" class="rt-btn rt-secondary rt-insp-reset">Limpiar opciones</button>'+
						'<button type="button" class="rt-btn rt-insp-save">Aplicar al contenedor</button>'+
					'</div>'+
				'</div>';
			}
			function refreshBePages(){
				if(!beSel)return;
				call('pages','GET',null,function(r){
					if(!r.ok)return;
					var list=(r.data||[]);
					beSel.innerHTML='<option value="">— elige una página —</option>'+list.map(function(p){return '<option value="'+escHtml(p.path)+'">['+escHtml(p.lang||'?')+'] '+escHtml(p.title||p.slug||p.path)+'</option>'}).join('');
				});
			}
			refreshBePages();
			var beRef=document.getElementById('rt-be-refresh');
			if(beRef)beRef.addEventListener('click',refreshBePages);

			function renderBlocks(){
				if(!beWrap)return;
				beWrap.style.display='block';
				if(!beBlocks.length){
					beWrap.innerHTML=inserterHtml(0,true)+'<div class="rt-empty-blocks">Esta página está vacía. Inserta tu primer bloque arriba ↑</div>';
					wireInserters();return;
				}
				var html=inserterHtml(0,false);
				beBlocks.forEach(function(b,i){
					var attrId=(b.attrs&&b.attrs.id)?'<span class="rt-block-id">#'+escHtml(b.attrs.id)+'</span>':'';
					var isInc=(b.tag==='Include')||(b.attrs&&b.attrs.__include);
					var incSlug=b.attrs&&b.attrs.slug?String(b.attrs.slug):'';
					var incBadge=isInc?'<span class="rt-include-badge">📎 REF · '+escHtml(incSlug)+'</span>':'';
					html+=
					'<div class="rt-block" data-i="'+i+'"'+(isInc?' data-include="'+escHtml(incSlug)+'"':'')+'>'+
						'<div class="rt-block-handle"><span class="rt-block-num">'+(i+1)+'</span></div>'+
						'<div class="rt-block-body">'+
							'<div class="rt-block-meta">'+badgeFor(b)+attrId+incBadge+'</div>'+
							'<div class="rt-block-preview">'+escHtml(b.preview||'')+'</div>'+
							'<div class="rt-block-edit">'+
								renderInspector(b,i)+
								'<textarea class="rt-block-raw">'+escHtml(b.raw||'')+'</textarea>'+
								'<div class="rt-edit-row">'+
									'<input type="text" class="rt-block-instr" placeholder="(Opcional) Instrucción IA: tono más profesional, añade ejemplos…" style="flex:1;min-width:0">'+
									'<button type="button" class="rt-btn rt-act-save">Guardar</button>'+
									'<button type="button" class="rt-btn rt-secondary rt-act-airewrite">Reescribir IA</button>'+
									'<button type="button" class="rt-btn rt-secondary rt-act-preview">Vista previa</button>'+
									'<button type="button" class="rt-btn rt-secondary rt-act-cancel">Cancelar</button>'+
								'</div>'+
								'<div class="rt-block-preview-html"></div>'+
							'</div>'+
						'</div>'+
						'<div class="rt-block-actions">'+
							'<button type="button" class="rt-act-up" title="Subir">▲</button>'+
							'<button type="button" class="rt-act-down" title="Bajar">▼</button>'+
							(isInc?'':'<button type="button" class="rt-act-edit" title="Editar">✎</button>')+
							(isInc?'':'<button type="button" class="rt-act-savelib" title="Guardar en biblioteca">📚</button>')+
							'<button type="button" class="rt-act-dup" title="Duplicar">⎘</button>'+
							(isInc?'<button type="button" class="rt-act-break" title="Romper sincronización (convertir a copia)">⛓️</button>':'')+
							'<button type="button" class="rt-act-del rt-danger" title="Eliminar">✕</button>'+
						'</div>'+
					'</div>'+
					inserterHtml(i+1,false);
				});
				beWrap.innerHTML=html;
				wireInserters();wireBlockActions();
			}
			function inserterHtml(pos,empty){
				var tpls=beTemplates.map(function(t){return '<button type="button" data-tpl="'+t+'">'+t+'</button>'}).join('');
				return '<div class="rt-block-inserter" data-pos="'+pos+'"'+(empty?' style="height:24px"':'')+'>'+
					'<button type="button" class="rt-ins-toggle" title="Insertar bloque">+</button>'+
					'<div class="rt-inserter-panel">'+
						'<div class="rt-inserter-tabs">'+
							'<button type="button" class="rt-tab rt-active" data-pane="tpl">Plantillas</button>'+
							'<button type="button" class="rt-tab" data-pane="lib">Biblioteca</button>'+
						'</div>'+
						'<div class="rt-inserter-pane rt-active" data-pane="tpl">'+
							'<h4>Insertar bloque en posición '+(pos+1)+'</h4>'+
							'<div class="rt-tpl-grid">'+tpls+'</div>'+
							'<input type="text" class="rt-ins-prompt" placeholder="(Opcional) Prompt para que la IA lo escriba por ti…">'+
							'<div class="rt-tpl-actions">'+
								'<button type="button" class="rt-btn rt-secondary rt-ins-cancel">Cancelar</button>'+
								'<button type="button" class="rt-btn rt-ins-ai">Generar con IA</button>'+
							'</div>'+
						'</div>'+
						'<div class="rt-inserter-pane" data-pane="lib">'+
							'<h4>Bloques reutilizables</h4>'+
							'<div class="rt-lib-grid"><em style="color:#8A9088;font-size:12px">Cargando…</em></div>'+
							'<p class="rt-hint" style="margin:6px 0 0;font-size:11px;color:#5C665A">Copia: contenido independiente. Ref: sincroniza cambios con la biblioteca.</p>'+
						'</div>'+
					'</div>'+
				'</div>';
			}
			function wireInserters(){
				beWrap.querySelectorAll('.rt-block-inserter').forEach(function(ins){
					var pos=parseInt(ins.getAttribute('data-pos'),10);
					ins.querySelector('.rt-ins-toggle').addEventListener('click',function(e){
						e.stopPropagation();
						beWrap.querySelectorAll('.rt-block-inserter.rt-open').forEach(function(o){if(o!==ins)o.classList.remove('rt-open')});
						ins.classList.toggle('rt-open');
						if(ins.classList.contains('rt-open'))loadLibIntoInserter(ins);
					});
					ins.querySelectorAll('.rt-inserter-tabs .rt-tab').forEach(function(t){
						t.addEventListener('click',function(){
							ins.querySelectorAll('.rt-inserter-tabs .rt-tab').forEach(function(b){b.classList.remove('rt-active')});
							t.classList.add('rt-active');
							var pane=t.getAttribute('data-pane');
							ins.querySelectorAll('.rt-inserter-pane').forEach(function(p){p.classList.toggle('rt-active',p.getAttribute('data-pane')===pane)});
							if(pane==='lib')loadLibIntoInserter(ins);
						});
					});
					ins.querySelectorAll('.rt-tpl-grid button').forEach(function(btn){
						btn.addEventListener('click',function(){
							var tpl=btn.getAttribute('data-tpl');
							setStatus(beStatus,'Insertando '+tpl+'…');
							call('blocks/insert','POST',{path:bePath,position:pos,template:tpl},function(r){
								if(r.ok&&r.data.ok){beBlocks=r.data.blocks;renderBlocks();toast('Bloque '+tpl+' insertado','ok');setStatus(beStatus,'')}
								else{toast('Error al insertar','err');setStatus(beStatus,r.data.error||'error','err')}
							});
						});
					});
					ins.querySelector('.rt-ins-cancel').addEventListener('click',function(){ins.classList.remove('rt-open')});
					ins.querySelector('.rt-ins-ai').addEventListener('click',function(){
						var prompt=ins.querySelector('.rt-ins-prompt').value.trim();
						var firstTpl=ins.querySelector('.rt-tpl-grid button[data-tpl]').getAttribute('data-tpl');
						// Use the template the user clicked last, fallback to first; we read data-selected if set
						var sel=ins.querySelector('.rt-tpl-grid button.rt-sel');
						var tpl=sel?sel.getAttribute('data-tpl'):firstTpl;
						if(!prompt){toast('Escribe un prompt o pulsa una plantilla','err');return}
						setStatus(beStatus,'IA generando '+tpl+'…');
						call('blocks/insert-ai','POST',{path:bePath,position:pos,template:tpl,prompt:prompt},function(r){
							if(r.ok&&r.data.ok){beBlocks=r.data.blocks;renderBlocks();toast('Bloque generado con IA','ok');setStatus(beStatus,'')}
							else{toast('Error IA','err');setStatus(beStatus,r.data.error||'error','err')}
						});
					});
					// Mark selected template (visual only) when click in tpl button while panel open and prompt focused
					ins.querySelectorAll('.rt-tpl-grid button').forEach(function(btn){
						btn.addEventListener('mousedown',function(e){
							if(ins.querySelector('.rt-ins-prompt').value.trim()!==''){e.preventDefault();ins.querySelectorAll('.rt-tpl-grid button').forEach(function(b){b.classList.remove('rt-sel')});btn.classList.add('rt-sel')}
						});
					});
				});
				document.addEventListener('click',function closer(){beWrap.querySelectorAll('.rt-block-inserter.rt-open').forEach(function(o){o.classList.remove('rt-open')})},{once:true});
			}
			function wireBlockActions(){
				beWrap.querySelectorAll('.rt-block').forEach(function(row){
					var i=parseInt(row.getAttribute('data-i'),10);
					row.querySelector('.rt-act-up').addEventListener('click',function(){doMove(i,-1)});
					row.querySelector('.rt-act-down').addEventListener('click',function(){doMove(i,1)});
					row.querySelector('.rt-act-dup').addEventListener('click',function(){doDup(i)});
					row.querySelector('.rt-act-del').addEventListener('click',function(){if(confirm('¿Eliminar este bloque?'))doDel(i)});
					var actEdit=row.querySelector('.rt-act-edit');
					if(actEdit)actEdit.addEventListener('click',function(){
						beWrap.querySelectorAll('.rt-block.rt-editing').forEach(function(o){if(o!==row)o.classList.remove('rt-editing')});
						row.classList.toggle('rt-editing');
					});
					row.querySelector('.rt-act-cancel').addEventListener('click',function(){row.classList.remove('rt-editing','rt-show-preview')});
					row.querySelector('.rt-act-save').addEventListener('click',function(){
						var raw=row.querySelector('.rt-block-raw').value;
						setStatus(beStatus,'Guardando bloque…');
						call('blocks/update','POST',{path:bePath,index:i,raw:raw},function(r){
							if(r.ok&&r.data.ok){beBlocks=r.data.blocks;renderBlocks();toast('Bloque guardado','ok');setStatus(beStatus,'')}
							else{toast('Error al guardar','err');setStatus(beStatus,r.data.error||'error','err')}
						});
					});
					row.querySelector('.rt-act-airewrite').addEventListener('click',function(){
						var instr=row.querySelector('.rt-block-instr').value;
						setStatus(beStatus,'IA reescribiendo bloque #'+(i+1)+'…');
						call('blocks/rewrite-ai','POST',{path:bePath,index:i,instruction:instr},function(r){
							if(r.ok&&r.data.ok){beBlocks=r.data.blocks;renderBlocks();toast('Bloque reescrito con IA','ok');setStatus(beStatus,'')}
							else{toast('Error IA','err');setStatus(beStatus,r.data.error||'error','err')}
						});
					});
					row.querySelector('.rt-act-preview').addEventListener('click',function(){
						var raw=row.querySelector('.rt-block-raw').value;
						var box=row.querySelector('.rt-block-preview-html');
						box.innerHTML='<em style="color:#8A9088">Renderizando…</em>';
						row.classList.add('rt-show-preview');
						call('blocks/preview','POST',{raw:raw},function(r){
							if(r.ok&&r.data.ok)box.innerHTML=r.data.html||'<em>(vacío)</em>';
							else box.innerHTML='<span style="color:#b91c1c">Error: '+(r.data.error||'')+'</span>';
						});
					});
					var savelib=row.querySelector('.rt-act-savelib');
					if(savelib)savelib.addEventListener('click',function(){
						var defSlug=prompt('Slug del bloque (a-z0-9-):','bloque-'+(i+1));
						if(!defSlug)return;
						var title=prompt('Título descriptivo:','Bloque #'+(i+1));
						if(title===null)return;
						var asRef=confirm('¿Reemplazar este bloque por una referencia sincronizada?\n\nOK = Ref (cambios futuros se propagan)\nCancelar = Copia (deja este bloque tal cual)');
						setStatus(beStatus,'Guardando en biblioteca…');
						call('blocks/save-to-library','POST',{path:bePath,index:i,slug:defSlug,title:title,replace_with_include:asRef},function(r){
							if(r.ok&&r.data.ok){
								if(r.data.blocks){beBlocks=r.data.blocks;renderBlocks()}
								toast('Guardado en biblioteca','ok');setStatus(beStatus,'');
								refreshLibList();
							}else{toast('Error: '+(r.data.error||''),'err');setStatus(beStatus,r.data.error||'error','err')}
						});
					});
					var brk=row.querySelector('.rt-act-break');
					if(brk)brk.addEventListener('click',function(){
						if(!confirm('¿Romper sincronización? Se convertirá en una copia independiente del bloque biblioteca.'))return;
						call('blocks/break-include','POST',{path:bePath,index:i},function(r){
							if(r.ok&&r.data.ok){beBlocks=r.data.blocks;renderBlocks();toast('Sincronización rota','ok')}
							else toast('Error: '+(r.data.error||''),'err');
						});
					});
					/* Inspector wiring */
					var inspSave=row.querySelector('.rt-insp-save');
					if(inspSave)inspSave.addEventListener('click',function(){
						var attrs={};
						row.querySelectorAll('.rt-inspector input[data-k],.rt-inspector select[data-k]').forEach(function(el){
							var k=el.getAttribute('data-k');
							if(el.type==='color'){
								// Only emit color if user changed it from initial.
								if(el.getAttribute('data-initial')!==el.value)attrs[k]=el.value;
								return;
							}
							attrs[k]=el.value;
						});
						// bgRaw overrides bg color picker if filled.
						if(attrs.bgRaw&&attrs.bgRaw.trim()!==''){attrs.bg=attrs.bgRaw}
						delete attrs.bgRaw;
						setStatus(beStatus,'Aplicando opciones…');
						call('blocks/update-attrs','POST',{path:bePath,index:i,attrs:attrs},function(r){
							if(r.ok&&r.data.ok){beBlocks=r.data.blocks;renderBlocks();toast('Contenedor actualizado','ok');setStatus(beStatus,'')}
							else{toast('Error al aplicar opciones','err');setStatus(beStatus,r.data.error||'error','err')}
						});
					});
					var inspReset=row.querySelector('.rt-insp-reset');
					if(inspReset)inspReset.addEventListener('click',function(){
						if(!confirm('¿Limpiar todas las opciones del contenedor (mantiene id y title)?'))return;
						var keep={id:beBlocks[i].attrs&&beBlocks[i].attrs.id||'',title:beBlocks[i].attrs&&beBlocks[i].attrs.title||''};
						call('blocks/update-attrs','POST',{path:bePath,index:i,attrs:keep},function(r){
							if(r.ok&&r.data.ok){beBlocks=r.data.blocks;renderBlocks();toast('Opciones limpiadas','ok')}
						});
					});
				});
			}
			/* Library inside inserter */
			var libCache=null,libCacheAt=0;
			function loadLibIntoInserter(ins){
				var pane=ins.querySelector('.rt-inserter-pane[data-pane="lib"] .rt-lib-grid');
				if(!pane)return;
				var pos=parseInt(ins.getAttribute('data-pos'),10);
				var now=Date.now();
				var render=function(items){
					if(!items.length){pane.innerHTML='<em style="color:#8A9088;font-size:12px">No hay bloques en la biblioteca todavía. Usa 📚 en cualquier bloque para añadir uno.</em>';return}
					pane.innerHTML=items.map(function(it){
						return '<div class="rt-lib-row"><strong title="'+escHtml(it.slug)+'">'+escHtml(it.title||it.slug)+'</strong>'+
							'<span class="rt-lib-meta">'+(it.usage_count||0)+'×</span>'+
							'<button type="button" class="rt-copy" data-slug="'+escHtml(it.slug)+'" data-mode="copy" data-pos="'+pos+'">Copia</button>'+
							'<button type="button" class="rt-ref" data-slug="'+escHtml(it.slug)+'" data-mode="ref" data-pos="'+pos+'">Ref</button>'+
						'</div>';
					}).join('');
					pane.querySelectorAll('button[data-mode]').forEach(function(btn){
						btn.addEventListener('click',function(){
							var slug=btn.getAttribute('data-slug');
							var mode=btn.getAttribute('data-mode');
							var endpoint=mode==='ref'?'blocks/insert-include':'blocks/insert-library-copy';
							setStatus(beStatus,'Insertando '+(mode==='ref'?'referencia':'copia')+' '+slug+'…');
							call(endpoint,'POST',{path:bePath,position:pos,slug:slug},function(r){
								if(r.ok&&r.data.ok){beBlocks=r.data.blocks;renderBlocks();toast(mode==='ref'?'Referencia insertada':'Copia insertada','ok');setStatus(beStatus,'')}
								else{toast('Error: '+(r.data.error||''),'err');setStatus(beStatus,r.data.error||'error','err')}
							});
						});
					});
				};
				if(libCache&&(now-libCacheAt)<10000){render(libCache);return}
				pane.innerHTML='<em style="color:#8A9088;font-size:12px">Cargando…</em>';
				call('library','GET',null,function(r){
					if(r.ok&&r.data.ok){libCache=r.data.items||[];libCacheAt=Date.now();render(libCache)}
					else pane.innerHTML='<em style="color:#b91c1c;font-size:12px">Error al cargar biblioteca</em>';
				});
			}
			function doMove(i,dir){
				call('blocks/move','POST',{path:bePath,index:i,direction:dir},function(r){
					if(r.ok&&r.data.ok){beBlocks=r.data.blocks;renderBlocks()}
					else toast('No se pudo mover','err');
				});
			}
			function doDup(i){
				call('blocks/duplicate','POST',{path:bePath,index:i},function(r){
					if(r.ok&&r.data.ok){beBlocks=r.data.blocks;renderBlocks();toast('Bloque duplicado','ok')}
				});
			}
			function doDel(i){
				call('blocks/delete','POST',{path:bePath,index:i},function(r){
					if(r.ok&&r.data.ok){beBlocks=r.data.blocks;renderBlocks();toast('Bloque eliminado','ok')}
				});
			}
			var beLoad=document.getElementById('rt-be-load');
			if(beLoad){beLoad.addEventListener('click',function(){
				var p=beSel.value;
				if(!p){toast('Elige una página','err');return}
				bePath=p;
				setStatus(beStatus,'Cargando bloques…');
				call('blocks?path='+encodeURIComponent(p),'GET',null,function(r){
					if(r.ok&&r.data.ok){
						beBlocks=r.data.blocks;renderBlocks();
						setStatus(beStatus,'Cargados '+beBlocks.length+' bloques','ok');
						var tb=document.getElementById('rt-be-toolbar');if(tb)tb.style.display='flex';
					}
					else{setStatus(beStatus,'Error: '+(r.data.error||''),'err');toast('Error al cargar','err')}
				});
			})}
			/* Responsive switcher */
			document.querySelectorAll('#rt-be-toolbar .rt-bp').forEach(function(btn){
				btn.addEventListener('click',function(){
					document.querySelectorAll('#rt-be-toolbar .rt-bp').forEach(function(b){b.classList.remove('rt-active')});
					btn.classList.add('rt-active');
					var bp=btn.getAttribute('data-bp');
					if(!beWrap)return;
					beWrap.classList.remove('rt-bp-tablet','rt-bp-mobile');
					if(bp==='tablet')beWrap.classList.add('rt-bp-tablet');
					else if(bp==='mobile')beWrap.classList.add('rt-bp-mobile');
				});
			});
			/* Audit page */
			var auditBtn=document.getElementById('rt-be-audit');
			var auditPanel=document.getElementById('rt-audit-panel');
			function renderAudit(d){
				if(!auditPanel)return;
				var scoreClass=d.score>=80?'':(d.score>=60?'rt-warn':'rt-bad');
				var stats='<div class="rt-audit-stats">'+
					'<span><strong>'+d.words+'</strong> palabras</span>'+
					'<span><strong>'+d.reading_min+' min</strong> de lectura</span>'+
					'<span><strong>'+d.blocks+'</strong> bloques</span>'+
					'<span><strong>'+d.h1+'</strong> H1 · <strong>'+d.h2+'</strong> H2</span>'+
					'<span><strong>'+d.images+'</strong> imágenes ('+d.images_no_alt+' sin alt)</span>'+
				'</div>';
				var issues=(d.issues||[]).map(function(it){
					var cls=it.level==='err'?'rt-err':'';
					return '<li class="'+cls+'"><span class="rt-cat">'+escHtml(it.cat)+'</span><span>'+escHtml(it.msg)+'</span></li>';
				}).join('');
				var wins=(d.wins||[]).map(function(w){return '<li class="rt-ok"><span class="rt-cat">OK</span><span>'+escHtml(w)+'</span></li>'}).join('');
				auditPanel.innerHTML='<div class="rt-audit-head">'+
					'<div class="rt-audit-score '+scoreClass+'">'+d.score+'</div>'+
					'<div style="flex:1"><strong style="font-size:15px">Auditoría rápida</strong>'+stats+'</div>'+
					'<button type="button" class="rt-btn rt-secondary" id="rt-audit-close">Cerrar</button>'+
				'</div>'+
				'<ul class="rt-audit-list">'+issues+wins+'</ul>';
				auditPanel.style.display='block';
				var close=document.getElementById('rt-audit-close');
				if(close)close.addEventListener('click',function(){auditPanel.style.display='none'});
			}
			if(auditBtn)auditBtn.addEventListener('click',function(){
				if(!bePath){toast('Carga una página primero','err');return}
				auditBtn.disabled=true;setStatus(beStatus,'Auditando página…');
				call('audit/page','POST',{path:bePath},function(r){
					auditBtn.disabled=false;
					if(r.ok&&r.data.ok){renderAudit(r.data);setStatus(beStatus,'Auditoría completada · score '+r.data.score+'/100','ok')}
					else{setStatus(beStatus,'Error: '+(r.data.error||''),'err');toast('Error al auditar','err')}
				});
			});

			/* ============================ Layout (header/footer) ============================ */
			var layoutForm=document.getElementById('rt-layout-form');
			var layoutStatus=document.getElementById('rt-layout-status');
			var layoutSaveBtn=document.getElementById('rt-layout-save');
			function loadLayout(){
				if(!layoutForm)return;
				call('layout','GET',null,function(r){
					if(!r.ok||!r.data.ok){setStatus(layoutStatus,'No se pudo cargar layout','err');return}
					var s=r.data.layout||{};
					layoutForm.querySelectorAll('[data-lk]').forEach(function(el){
						var k=el.getAttribute('data-lk');
						if(el.type==='checkbox')el.checked=!!s[k];
						else el.value=s[k]==null?'':s[k];
					});
				});
			}
			if(layoutSaveBtn)layoutSaveBtn.addEventListener('click',function(){
				var payload={};
				layoutForm.querySelectorAll('[data-lk]').forEach(function(el){
					var k=el.getAttribute('data-lk');
					if(el.type==='checkbox')payload[k]=el.checked;
					else if(el.type==='number')payload[k]=el.value===''?0:parseInt(el.value,10);
					else payload[k]=el.value;
				});
				layoutSaveBtn.disabled=true;setStatus(layoutStatus,'Guardando…');
				call('layout','POST',payload,function(r){
					layoutSaveBtn.disabled=false;
					if(r.ok&&r.data.ok){setStatus(layoutStatus,'Guardado','ok');toast('Cabecera/pie actualizados','ok')}
					else{setStatus(layoutStatus,'Error','err');toast('Error al guardar layout','err')}
				});
			});
			loadLayout();

			/* ============================ Library (admin list) ============================ */
			var libList=document.getElementById('rt-lib-list');
			var libStatus=document.getElementById('rt-lib-status');
			var libRefresh=document.getElementById('rt-lib-refresh');
			function refreshLibList(){
				if(!libList)return;
				libCache=null;
				libList.innerHTML='<em style="color:#8A9088">Cargando…</em>';
				call('library','GET',null,function(r){
					if(!r.ok||!r.data.ok){libList.innerHTML='<em style="color:#b91c1c">Error al cargar</em>';return}
					var items=r.data.items||[];
					if(!items.length){libList.innerHTML='<em style="color:#8A9088">Vacía. Pulsa 📚 en cualquier bloque del editor para añadirlo aquí.</em>';return}
					libList.innerHTML=items.map(function(it){
						return '<div class="rt-lib-item" data-slug="'+escHtml(it.slug)+'">'+
							'<strong>'+escHtml(it.title||it.slug)+'</strong>'+
							'<span class="rt-lib-meta">/'+escHtml(it.slug)+' · '+(it.usage_count||0)+' uso(s)</span>'+
							'<button type="button" class="rt-lib-del rt-danger">Eliminar</button>'+
						'</div>';
					}).join('');
					libList.querySelectorAll('.rt-lib-del').forEach(function(b){
						b.addEventListener('click',function(){
							var slug=b.closest('.rt-lib-item').getAttribute('data-slug');
							if(!confirm('¿Eliminar bloque "'+slug+'" de la biblioteca?\n\nNota: las páginas que lo referencian mostrarán un aviso de error hasta que se rompa la sincronización o se vuelva a crear.'))return;
							call('library/delete','POST',{slug:slug},function(r2){
								if(r2.ok&&r2.data.ok){toast('Eliminado','ok');refreshLibList()}
								else toast('Error al eliminar','err');
							});
						});
					});
				});
			}
			if(libRefresh)libRefresh.addEventListener('click',refreshLibList);
			refreshLibList();

			// ===== Promote (gestión de redirecciones globales) =====
			var promRedToggle=document.getElementById('rt-promote-redirects-toggle');
			var promRedBox=document.getElementById('rt-promote-redirects');
			function escHtml(s){return String(s||'').replace(/[&<>\"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]})}
			function redLoad(){
				if(!promRedBox)return;
				promRedBox.innerHTML='<p style="color:var(--rt-muted)">Cargando redirecciones…</p>';
				call('promote/redirects','GET',null,function(r){
					var items=(r.ok&&r.data.ok)?(r.data.items||[]):[];
					var rows=items.length?items.map(function(it){
						return '<div class="rt-source-item" style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px;border:1px solid var(--rt-line);border-radius:8px;margin-bottom:6px">'+
							'<code>'+escHtml(it.from)+'</code>'+
							'<span style="color:var(--rt-muted)">→</span>'+
							'<code style="flex:1">'+escHtml(it.to)+'</code>'+
							'<button type="button" class="rt-btn rt-secondary rt-red-del" data-from="'+escHtml(it.from)+'">Eliminar</button>'+
						'</div>';
					}).join(''):'<p style="color:var(--rt-muted)">Sin redirecciones todavía.</p>';
					promRedBox.innerHTML=rows+
						'<div class="rt-row" style="margin-top:8px">'+
							'<input type="text" id="rt-red-from" placeholder="/from-path" style="flex:1">'+
							'<input type="text" id="rt-red-to" placeholder="/to-path" style="flex:1">'+
							'<button type="button" class="rt-btn" id="rt-red-add">Añadir 301</button>'+
						'</div>';
					var addBtn=document.getElementById('rt-red-add');
					if(addBtn)addBtn.addEventListener('click',function(){
						var from=(document.getElementById('rt-red-from').value||'').trim();
						var to=(document.getElementById('rt-red-to').value||'').trim();
						if(!from||!to){setStatus('rt-promote-status','from + to requeridos','err');return}
						call('promote/redirects','POST',{from:from,to:to},function(r2){
							if(r2.ok&&r2.data.ok){setStatus('rt-promote-status','Redirección añadida','ok');redLoad()}
							else setStatus('rt-promote-status','Error','err');
						});
					});
					promRedBox.querySelectorAll('.rt-red-del').forEach(function(b){
						b.addEventListener('click',function(){
							var from=b.getAttribute('data-from');
							call('promote/redirects','DELETE',{from:from},function(r2){
								if(r2.ok&&r2.data.ok){setStatus('rt-promote-status','Eliminada','ok');redLoad()}
								else setStatus('rt-promote-status','Error','err');
							});
						});
					});
				});
			}
			if(promRedToggle)promRedToggle.addEventListener('click',function(e){
				e.preventDefault();
				if(promRedBox.style.display==='none'){promRedBox.style.display='block';redLoad()}
				else{promRedBox.style.display='none'}
			});
		})();
		</script>
		<?php
	}

	public function enqueue( string $hook ): void {
		if ( $hook !== 'toplevel_page_' . self::PAGE_SLUG ) {
			return;
		}

		// If build doesn't exist, fallback render handles itself with inline JS.
		if ( ! is_readable( RT_THEME_DIR . 'assets/dist/admin.js' ) ) {
			return;
		}

		$asset_file = RT_THEME_DIR . 'assets/dist/admin.asset.php';
		$asset      = is_readable( $asset_file ) ? require $asset_file : [ 'dependencies' => [], 'version' => RT_THEME_VERSION ];

		$deps = array_merge(
			[ 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ],
			$asset['dependencies'] ?? []
		);

		wp_enqueue_script(
			'replanta-admin',
			RT_THEME_URL . 'assets/dist/admin.js',
			$deps,
			$asset['version'] ?? RT_THEME_VERSION,
			true
		);

		wp_enqueue_style(
			'replanta-admin',
			RT_THEME_URL . 'assets/dist/admin.css',
			[ 'wp-components' ],
			$asset['version'] ?? RT_THEME_VERSION
		);

		wp_localize_script(
			'replanta-admin',
			'RT_ADMIN',
			[
				'restUrl'   => esc_url_raw( rest_url( 'replanta/v1/' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'themeUrl'  => RT_THEME_URL,
				'languages' => $this->detect_languages(),
				'siteUrl'   => home_url( '/' ),
				'version'   => RT_THEME_VERSION,
			]
		);
	}

	private function detect_languages(): array {
		if ( function_exists( 'pll_languages_list' ) ) {
			return pll_languages_list( [ 'fields' => '' ] ) ?: [ [ 'slug' => 'es', 'name' => 'Español' ] ];
		}
		return [ [ 'slug' => 'es', 'name' => 'Español' ] ];
	}
}
