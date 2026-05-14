<?php
/**
 * Native admin integration for Promote actions: list table column,
 * row actions, admin bar, bulk button and pre-flight modal.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Promote_Admin {

	public function register(): void {
		$cpt = RT_CPT_Page::POST_TYPE;
		add_filter( "manage_{$cpt}_posts_columns", [ $this, 'columns' ] );
		add_action( "manage_{$cpt}_posts_custom_column", [ $this, 'render_column' ], 10, 2 );
		add_filter( 'post_row_actions', [ $this, 'row_actions' ], 10, 2 );
		add_action( 'admin_bar_menu', [ $this, 'admin_bar' ], 80 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'restrict_manage_posts', [ $this, 'bulk_button' ] );
		add_action( 'admin_footer', [ $this, 'render_modals' ] );
	}

	/* ------------------------------------------------------------- Columns */

	/**
	 * @param array<string,string> $cols
	 * @return array<string,string>
	 */
	public function columns( array $cols ): array {
		$out = [];
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( $k === 'title' ) {
				$out['rt_origin'] = __( 'Origen', 'replanta-theme' );
			}
		}
		if ( ! isset( $out['rt_origin'] ) ) {
			$out['rt_origin'] = __( 'Origen', 'replanta-theme' );
		}
		return $out;
	}

	public function render_column( string $col, int $post_id ): void {
		if ( $col !== 'rt_origin' ) {
			return;
		}
		$src = $this->source_url( $post_id );
		if ( $src === '' ) {
			echo '<span style="color:#999">—</span>';
			return;
		}
		$origin      = ( new RT_Promote() )->resolve_origin( $src );
		$is_promoted = (string) get_post_meta( $post_id, RT_Promote::META_AT, true ) !== '';

		echo '<div style="display:flex;flex-direction:column;gap:4px;font-size:12px">';
		echo '<a href="' . esc_url( $src ) . '" target="_blank" rel="noopener" style="text-decoration:none">' . esc_html( wp_parse_url( $src, PHP_URL_HOST ) . wp_parse_url( $src, PHP_URL_PATH ) ) . '</a>';
		if ( $is_promoted ) {
			echo '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 6px;background:#dcfce7;color:#166534;border-radius:4px;width:fit-content">' . RT_Icons::svg( 'check-circle', 12 ) . esc_html__( 'Adoptada', 'replanta-theme' ) . '</span>';
		} elseif ( $origin ) {
			$label = sprintf(
				/* translators: 1: post type, 2: status */
				__( '%1$s (%2$s)', 'replanta-theme' ),
				$origin['type'],
				$origin['status']
			);
			echo '<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 6px;background:#e0f2fe;color:#075985;border-radius:4px;width:fit-content">' . RT_Icons::svg( 'arrow-right', 12 ) . esc_html( $label ) . '</span>';
		} else {
			echo '<span style="display:inline-block;padding:2px 6px;background:#fef3c7;color:#92400e;border-radius:4px;width:fit-content">' . esc_html__( 'Sin original', 'replanta-theme' ) . '</span>';
		}
		echo '</div>';
	}

	/* --------------------------------------------------------- Row actions */

	/**
	 * @param array<string,string> $actions
	 * @return array<string,string>
	 */
	public function row_actions( array $actions, \WP_Post $post ): array {
		if ( $post->post_type !== RT_CPT_Page::POST_TYPE ) {
			return $actions;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}
		$src = $this->source_url( $post->ID );
		if ( $src === '' ) {
			return $actions;
		}
		$origin   = ( new RT_Promote() )->resolve_origin( $src );
		$promoted = (string) get_post_meta( $post->ID, RT_Promote::META_AT, true ) !== '';

		$btn = static function ( string $action, string $icon, string $label, int $id, string $color = '' ): string {
			$style = $color !== '' ? 'style="color:' . esc_attr( $color ) . ';font-weight:600"' : '';
			return sprintf(
				'<a href="#" class="rt-promote-action" data-rt-action="%s" data-rt-id="%d" %s><span style="display:inline-flex;align-items:center;gap:3px">%s%s</span></a>',
				esc_attr( $action ),
				$id,
				$style,
				RT_Icons::svg( $icon, 12 ),
				esc_html( $label )
			);
		};
		if ( $origin && ! $promoted ) {
			$actions['rt_promote_adopt'] = $btn( 'adopt', 'crown', __( 'Adoptar URL', 'replanta-theme' ), $post->ID, '#7c3aed' );
		}
		if ( $promoted ) {
			$actions['rt_promote_undo'] = $btn( 'undo', 'arrow-right', __( 'Revertir adopción', 'replanta-theme' ), $post->ID, '#dc2626' );
		}
		if ( $origin ) {
			$actions['rt_promote_menus'] = $btn( 'menus', 'tree-view', __( 'Reemplazar en menús', 'replanta-theme' ), $post->ID );
		}
		$actions['rt_promote_front'] = $btn( 'front', 'house', __( 'Front page', 'replanta-theme' ), $post->ID );
		return $actions;
	}

	/* ----------------------------------------------------------- Admin bar */

	public function admin_bar( \WP_Admin_Bar $bar ): void {
		$post = get_post();
		if ( ! $post || $post->post_type !== RT_CPT_Page::POST_TYPE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		$src = $this->source_url( $post->ID );
		if ( $src === '' ) {
			return;
		}
		$origin   = ( new RT_Promote() )->resolve_origin( $src );
		$promoted = (string) get_post_meta( $post->ID, RT_Promote::META_AT, true ) !== '';

		$bar->add_node( [
			'id'    => 'rt-promote',
			'title' => RT_Icons::svg( 'crown', 16 ) . '<span style="margin-left:4px">' . esc_html__( 'Promover', 'replanta-theme' ) . '</span>',
			'href'  => '#',
			'meta'  => [ 'class' => 'rt-promote-bar' ],
		] );
		if ( $origin && ! $promoted ) {
			$bar->add_node( [
				'parent' => 'rt-promote',
				'id'     => 'rt-promote-adopt',
				'title'  => sprintf(
					/* translators: %s: source URL path */
					__( 'Adoptar URL: %s', 'replanta-theme' ),
					(string) wp_parse_url( $src, PHP_URL_PATH )
				),
				'href'   => '#',
				'meta'   => [ 'class' => 'rt-promote-action', 'html' => 'data-rt-action="adopt" data-rt-id="' . (int) $post->ID . '"' ],
			] );
		}
		if ( $promoted ) {
			$bar->add_node( [
				'parent' => 'rt-promote',
				'id'     => 'rt-promote-undo',
				'title'  => __( 'Revertir adopción', 'replanta-theme' ),
				'href'   => '#',
				'meta'   => [ 'class' => 'rt-promote-action', 'html' => 'data-rt-action="undo" data-rt-id="' . (int) $post->ID . '"' ],
			] );
		}
		if ( $origin ) {
			$bar->add_node( [
				'parent' => 'rt-promote',
				'id'     => 'rt-promote-menus',
				'title'  => __( 'Reemplazar en menús', 'replanta-theme' ),
				'href'   => '#',
				'meta'   => [ 'class' => 'rt-promote-action', 'html' => 'data-rt-action="menus" data-rt-id="' . (int) $post->ID . '"' ],
			] );
		}
		$bar->add_node( [
			'parent' => 'rt-promote',
			'id'     => 'rt-promote-front',
			'title'  => __( 'Establecer como Front page', 'replanta-theme' ),
			'href'   => '#',
			'meta'   => [ 'class' => 'rt-promote-action', 'html' => 'data-rt-action="front" data-rt-id="' . (int) $post->ID . '"' ],
		] );
	}

	/* --------------------------------------------------------- Bulk button */

	public function bulk_button( string $post_type ): void {
		if ( $post_type !== RT_CPT_Page::POST_TYPE ) {
			return;
		}
		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		echo '<button type="button" id="rt-bulk-open" class="button" style="margin-left:6px;display:inline-flex;align-items:center;gap:4px">'
			. RT_Icons::svg( 'crown', 12 )
			. esc_html__( 'Adoptar seleccionadas', 'replanta-theme' )
			. '</button>';
	}

	/* ---------------------------------------------------------- Enqueue JS */

	public function enqueue( string $hook ): void {
		$is_admin_screen = in_array( $hook, [ 'edit.php', 'post.php', 'post-new.php' ], true );
		$is_front        = ! is_admin();
		if ( ! $is_admin_screen && ! $is_front ) {
			return;
		}
		if ( $is_admin_screen ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || $screen->post_type !== RT_CPT_Page::POST_TYPE ) {
				return;
			}
		} else {
			$post = get_post();
			if ( ! $post || $post->post_type !== RT_CPT_Page::POST_TYPE ) {
				return;
			}
		}

		$rest_url = esc_url_raw( rest_url( 'replanta/v1/' ) );
		$nonce    = wp_create_nonce( 'wp_rest' );
		$strings  = [
			'confirm_front'   => __( '¿Convertir esta página en la página de inicio del sitio?', 'replanta-theme' ),
			'confirm_undo'    => __( '¿Revertir la adopción? Se restaurarán los slugs originales y se eliminará la redirección 301 creada.', 'replanta-theme' ),
			'adopting'        => __( 'Adoptando…', 'replanta-theme' ),
			'menus_doing'     => __( 'Reemplazando enlaces de menú…', 'replanta-theme' ),
			'front_doing'     => __( 'Aplicando como front page…', 'replanta-theme' ),
			'ok_adopted_full' => __( 'URL adoptada · %m menús · %p posts · %l enlaces. Recargando…', 'replanta-theme' ),
			'ok_undone'       => __( 'Adopción revertida. Recargando…', 'replanta-theme' ),
			'ok_menus'        => __( '%d enlaces de menú actualizados', 'replanta-theme' ),
			'ok_front'        => __( 'Front page establecida. Recargando…', 'replanta-theme' ),
			'err'             => __( 'Error: ', 'replanta-theme' ),
			'btn_confirm'     => __( 'Adoptar ahora', 'replanta-theme' ),
			'bulk_none'       => __( 'Selecciona al menos una página de la tabla.', 'replanta-theme' ),
			'bulk_max'        => __( 'Máximo 50 páginas por lote.', 'replanta-theme' ),
			'bulk_finished'   => __( 'Finalizado: %o correctas · %f con error', 'replanta-theme' ),
			'bulk_close'      => __( 'Cerrar', 'replanta-theme' ),
			'pf_loading'      => __( 'Analizando impacto…', 'replanta-theme' ),
			'pf_no_origin'    => __( 'No se localiza una página original en este sitio.', 'replanta-theme' ),
			'col_source'      => __( 'URL origen', 'replanta-theme' ),
			'col_rt'          => __( 'URL del rt_page', 'replanta-theme' ),
			'col_origin'      => __( 'Original detectado', 'replanta-theme' ),
			'col_front'       => __( 'Es página de inicio', 'replanta-theme' ),
			'col_menus'       => __( 'Enlaces de menú', 'replanta-theme' ),
			'col_internal'    => __( 'Enlaces internos en posts', 'replanta-theme' ),
			'col_revs'        => __( 'Revisiones', 'replanta-theme' ),
			'col_comm'        => __( 'Comentarios', 'replanta-theme' ),
			'col_seo'         => __( 'Metadatos SEO', 'replanta-theme' ),
			'col_trans'       => __( 'Traducciones', 'replanta-theme' ),
			'yes'             => __( 'Sí', 'replanta-theme' ),
			'no'              => __( 'No', 'replanta-theme' ),
			'samples_label'   => __( 'Posts con enlaces internos', 'replanta-theme' ),
			'links_short'     => __( '%d enlaces', 'replanta-theme' ),
			'cmp_loading'     => __( 'Calculando métricas (puede tardar ~30s)…', 'replanta-theme' ),
			'cmp_perf'        => __( 'Rendimiento', 'replanta-theme' ),
			'cmp_seo'         => __( 'SEO', 'replanta-theme' ),
			'cmp_a11y'        => __( 'Accesibilidad', 'replanta-theme' ),
			'cmp_bp'          => __( 'Buenas prácticas', 'replanta-theme' ),
			'cmp_no_key'      => __( 'PageSpeed Insights ha rechazado la consulta. Configura una API key en Ajustes para evitar el límite gratuito.', 'replanta-theme' ),
			'cmp_no_origin'   => __( 'No se puede comparar: no hay URL original detectada.', 'replanta-theme' ),
		];

		$handle = 'rt-promote-admin';
		wp_register_script( $handle, '', [], RT_THEME_VERSION, true );
		wp_enqueue_script( $handle );
		wp_add_inline_script( $handle, $this->inline_js( $rest_url, $nonce, $strings ) );
		wp_add_inline_style( 'admin-bar', '#wpadminbar #wp-admin-bar-rt-promote .rt-icon{vertical-align:middle;margin-top:-2px}#wpadminbar #wp-admin-bar-rt-promote svg{color:#eee}.rt-promote-action .rt-icon{vertical-align:middle}' );
		wp_register_style( 'rt-promote-modal', false, [], RT_THEME_VERSION );
		wp_enqueue_style( 'rt-promote-modal' );
		wp_add_inline_style( 'rt-promote-modal', $this->modal_css() );
	}

	private function modal_css(): string {
		return '.rt-modal{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99998;display:none;align-items:center;justify-content:center;padding:20px}.rt-modal.is-open{display:flex}'
			. '.rt-modal-card{background:#fff;border-radius:12px;width:100%;max-width:680px;max-height:90vh;overflow:auto;box-shadow:0 24px 64px rgba(0,0,0,.3);display:flex;flex-direction:column}'
			. '.rt-modal-card header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #e5e7eb;position:sticky;top:0;background:#fff;z-index:1}'
			. '.rt-modal-card header h2{margin:0;font-size:18px}.rt-modal-card header .rt-x{background:none;border:0;font-size:24px;line-height:1;cursor:pointer;color:#6b7280;padding:0 4px}'
			. '.rt-modal-card footer{display:flex;gap:8px;justify-content:flex-end;padding:16px 20px;border-top:1px solid #e5e7eb;position:sticky;bottom:0;background:#fff}'
			. '.rt-modal-card .rt-pf-body, .rt-modal-card .rt-pf-opts, .rt-modal-card .rt-bulk-progress-wrap, .rt-modal-card .rt-bulk-log{padding:0 20px;margin:16px 0}'
			. '.rt-pf-table{width:100%;border-collapse:collapse;font-size:13px}.rt-pf-table th{text-align:left;padding:6px 8px;color:#6b7280;font-weight:500;width:40%;vertical-align:top}.rt-pf-table td{padding:6px 8px;word-break:break-word}'
			. '.rt-pf-table tr:nth-child(odd){background:#f9fafb}'
			. '.rt-pf-warn{margin:12px 20px 0;padding:10px 12px;background:#fef3c7;color:#92400e;border-radius:6px;font-size:13px}.rt-pf-warn div{padding:2px 0}'
			. '.rt-pf-samples{margin:12px 20px 0;font-size:12px}.rt-pf-samples summary{cursor:pointer;color:#0f172a;padding:4px 0}.rt-pf-samples ul{margin:4px 0 0 16px;color:#374151}'
			. '.rt-pf-opts{display:flex;flex-direction:column;gap:6px;background:#f9fafb;border-radius:6px;padding:12px 16px}.rt-pf-opts label{display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;margin:0}'
			. '.rt-bulk-progress-wrap{height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden}#rt-bulk-progress{height:100%;background:#7c3aed;width:0;transition:width .3s ease}'
			. '.rt-bulk-log{max-height:240px;overflow:auto;background:#f9fafb;border-radius:6px;padding:8px 12px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px}'
			. '.rt-modal-wide{max-width:1100px}'
			. '.rt-tabs{display:flex;gap:0;border-bottom:1px solid #e5e7eb;padding:0 20px;background:#fff;position:sticky;top:53px;z-index:1}'
			. '.rt-tab{background:none;border:0;border-bottom:2px solid transparent;padding:10px 14px;cursor:pointer;font-size:13px;color:#6b7280}.rt-tab.is-active{color:#0f172a;border-bottom-color:#7c3aed;font-weight:600}'
			. '.rt-tab-panel{display:none}.rt-tab-panel.is-active{display:block}'
			. '.rt-compare-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 20px;background:#f9fafb;border-bottom:1px solid #e5e7eb}'
			. '.rt-seg{display:inline-flex;border:1px solid #d1d5db;border-radius:6px;overflow:hidden}.rt-seg-btn{background:#fff;border:0;padding:6px 12px;font-size:12px;cursor:pointer;color:#374151}.rt-seg-btn.is-active{background:#7c3aed;color:#fff}'
			. '.rt-cmp-scores{margin:16px 20px;display:grid;grid-template-columns:repeat(4,1fr);gap:8px}.rt-cmp-scores .rt-cmp-card{background:#f9fafb;border-radius:8px;padding:10px 12px}'
			. '.rt-cmp-card .rt-cmp-cat{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;margin-bottom:6px}'
			. '.rt-cmp-card .rt-cmp-vals{display:flex;align-items:baseline;gap:8px;font-variant-numeric:tabular-nums}'
			. '.rt-cmp-card .rt-cmp-a{color:#6b7280;font-size:14px}.rt-cmp-card .rt-cmp-b{color:#0f172a;font-size:20px;font-weight:600}'
			. '.rt-cmp-card .rt-cmp-d{font-size:12px;font-weight:600;padding:2px 6px;border-radius:4px;background:#f3f4f6;color:#374151}'
			. '.rt-cmp-d.up{background:#dcfce7;color:#166534}.rt-cmp-d.down{background:#fee2e2;color:#991b1b}'
			. '.rt-cmp-frames{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:0 20px 16px}.rt-cmp-col h3{margin:0 0 6px;font-size:13px;color:#374151}.rt-cmp-col iframe{width:100%;height:520px;border:1px solid #e5e7eb;border-radius:8px;background:#fff}'
			. '.rt-cmp-loading{padding:24px;text-align:center;color:#6b7280;font-size:13px}'
			. '.rt-cmp-error{margin:16px 20px;padding:10px 12px;background:#fef3c7;color:#92400e;border-radius:6px;font-size:13px}';
	}

	/* -------------------------------------------------- Modal markup (footer) */

	public function render_modals(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->post_type !== RT_CPT_Page::POST_TYPE ) {
			return;
		}
		$close = '<button type="button" class="rt-x" data-rt-close aria-label="' . esc_attr__( 'Cerrar', 'replanta-theme' ) . '">&times;</button>';
		?>
		<div id="rt-modal-preflight" class="rt-modal" data-rt-modal role="dialog" aria-modal="true" aria-labelledby="rt-pf-title">
			<div class="rt-modal-card rt-modal-wide">
				<header><h2 id="rt-pf-title"><?php esc_html_e( 'Vista previa: Adoptar URL', 'replanta-theme' ); ?></h2><?php echo $close; ?></header>
				<nav class="rt-tabs" role="tablist">
					<button type="button" class="rt-tab is-active" data-rt-tab="impact" role="tab" aria-selected="true"><?php esc_html_e( 'Impacto', 'replanta-theme' ); ?></button>
					<button type="button" class="rt-tab" data-rt-tab="compare" role="tab" aria-selected="false"><?php esc_html_e( 'Comparar antes/después', 'replanta-theme' ); ?></button>
				</nav>
				<section class="rt-tab-panel is-active" data-rt-panel="impact">
					<div id="rt-pf-body" class="rt-pf-body"></div>
				</section>
				<section class="rt-tab-panel" data-rt-panel="compare">
					<div class="rt-compare-toolbar">
						<div class="rt-seg">
							<button type="button" class="rt-seg-btn is-active" data-rt-strategy="mobile"><?php esc_html_e( 'Móvil', 'replanta-theme' ); ?></button>
							<button type="button" class="rt-seg-btn" data-rt-strategy="desktop"><?php esc_html_e( 'Escritorio', 'replanta-theme' ); ?></button>
						</div>
						<button type="button" class="button" id="rt-cmp-refresh"><?php esc_html_e( 'Actualizar métricas', 'replanta-theme' ); ?></button>
					</div>
					<div id="rt-cmp-scores" class="rt-cmp-scores"></div>
					<div class="rt-cmp-frames">
						<div class="rt-cmp-col"><h3><?php esc_html_e( 'Original', 'replanta-theme' ); ?></h3><iframe id="rt-cmp-iframe-a" title="<?php esc_attr_e( 'URL original', 'replanta-theme' ); ?>" loading="lazy" sandbox="allow-same-origin allow-scripts allow-popups"></iframe></div>
						<div class="rt-cmp-col"><h3><?php esc_html_e( 'rt_page', 'replanta-theme' ); ?></h3><iframe id="rt-cmp-iframe-b" title="<?php esc_attr_e( 'Página rt_page', 'replanta-theme' ); ?>" loading="lazy" sandbox="allow-same-origin allow-scripts allow-popups"></iframe></div>
					</div>
				</section>
				<fieldset class="rt-pf-opts">
					<label><input type="checkbox" id="rt-pf-demote" checked> <?php esc_html_e( 'Pasar el original a borrador (recomendado)', 'replanta-theme' ); ?></label>
					<label><input type="checkbox" id="rt-pf-menus" checked> <?php esc_html_e( 'Reasignar enlaces en menús', 'replanta-theme' ); ?></label>
					<label><input type="checkbox" id="rt-pf-links" checked> <?php esc_html_e( 'Reescribir enlaces internos en posts', 'replanta-theme' ); ?></label>
					<label><input type="checkbox" id="rt-pf-front"> <?php esc_html_e( 'Establecer como página de inicio', 'replanta-theme' ); ?></label>
				</fieldset>
				<footer>
					<button type="button" class="button" data-rt-close><?php esc_html_e( 'Cancelar', 'replanta-theme' ); ?></button>
					<button type="button" class="button button-primary" id="rt-pf-confirm" disabled><?php esc_html_e( 'Adoptar ahora', 'replanta-theme' ); ?></button>
				</footer>
			</div>
		</div>

		<div id="rt-modal-bulk" class="rt-modal" data-rt-modal role="dialog" aria-modal="true" aria-labelledby="rt-bulk-title">
			<div class="rt-modal-card">
				<header><h2 id="rt-bulk-title"><?php esc_html_e( 'Adoptar', 'replanta-theme' ); ?> <span id="rt-bulk-count">0</span> <?php esc_html_e( 'páginas', 'replanta-theme' ); ?></h2><?php echo $close; ?></header>
				<fieldset class="rt-pf-opts">
					<label><input type="checkbox" id="rt-bk-demote" checked> <?php esc_html_e( 'Pasar originales a borrador', 'replanta-theme' ); ?></label>
					<label><input type="checkbox" id="rt-bk-menus" checked> <?php esc_html_e( 'Reasignar enlaces en menús', 'replanta-theme' ); ?></label>
					<label><input type="checkbox" id="rt-bk-links" checked> <?php esc_html_e( 'Reescribir enlaces internos', 'replanta-theme' ); ?></label>
				</fieldset>
				<div class="rt-bulk-progress-wrap"><div id="rt-bulk-progress"></div></div>
				<div id="rt-bulk-log" class="rt-bulk-log"></div>
				<footer>
					<button type="button" class="button" data-rt-close><?php esc_html_e( 'Cancelar', 'replanta-theme' ); ?></button>
					<button type="button" class="button button-primary" id="rt-bulk-run"><?php esc_html_e( 'Iniciar', 'replanta-theme' ); ?></button>
				</footer>
			</div>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------- JS */

	/** @param array<string,string> $s */
	private function inline_js( string $rest, string $nonce, array $s ): string {
		$cfg = wp_json_encode( [
			'rest'  => $rest,
			'nonce' => $nonce,
			's'     => $s,
		] );
		return <<<JS
(function(){
	var C={$cfg};
	function call(path,method,data,cb){
		var m=(method||'GET').toUpperCase();
		var opts={method:m,headers:{'X-WP-Nonce':C.nonce,'Content-Type':'application/json'}};
		if(data&&m!=='GET'&&m!=='HEAD')opts.body=JSON.stringify(data);
		fetch(C.rest+path,opts).then(function(r){return r.json().then(function(j){return{ok:r.ok,data:j}});}).then(cb).catch(function(e){cb({ok:false,data:{error:e.message}});});
	}
	function toast(msg,kind){
		var t=document.getElementById('rt-toast');
		if(!t){t=document.createElement('div');t.id='rt-toast';t.style.cssText='position:fixed;bottom:24px;right:24px;z-index:99999;padding:12px 18px;border-radius:8px;color:#fff;font:14px/1.4 system-ui;box-shadow:0 8px 32px rgba(0,0,0,.15);max-width:380px';document.body.appendChild(t);}
		t.style.background=kind==='err'?'#dc2626':kind==='ok'?'#15803d':'#0f172a';
		t.textContent=msg;t.style.display='block';
		clearTimeout(t._h);t._h=setTimeout(function(){t.style.display='none';},4500);
	}
	function modal(id){return document.getElementById(id);}
	function openModal(id){var m=modal(id);if(m)m.classList.add('is-open');}
	function closeModal(id){var m=modal(id);if(m)m.classList.remove('is-open');}
	function escAttr(v){return String(v==null?'':v).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}

	function renderPreflight(d){
		var origin=d.origin?('<a href="'+escAttr(d.origin.permalink)+'" target="_blank" rel="noopener">'+escAttr(d.origin.title||d.origin.permalink)+'</a> <span style="color:#6b7280">('+escAttr(d.origin.type)+', '+escAttr(d.origin.status)+')</span>'):('<em>'+C.s.pf_no_origin+'</em>');
		var rows=[
			[C.s.col_source, '<code style="font-size:11px">'+escAttr(d.source_url||'')+'</code>'],
			[C.s.col_rt, '<code style="font-size:11px">'+escAttr(d.rt_url||'')+'</code>'],
			[C.s.col_origin, origin],
			[C.s.col_front, d.is_front_page?C.s.yes:C.s.no],
			[C.s.col_menus, String(d.menu_links||0)],
			[C.s.col_internal, (d.internal_links.count||0)+' / '+(d.internal_links.links||0)],
			[C.s.col_revs, String(d.revisions||0)],
			[C.s.col_comm, String(d.comments||0)],
			[C.s.col_seo, d.seo_meta?C.s.yes:C.s.no],
			[C.s.col_trans, d.translations.has?(d.translations.count+' ('+escAttr(d.translations.plugin)+')'):C.s.no]
		];
		var html='<table class="rt-pf-table">';
		rows.forEach(function(r){html+='<tr><th>'+r[0]+'</th><td>'+r[1]+'</td></tr>';});
		html+='</table>';
		if(d.warnings&&d.warnings.length){
			var w='<div class="rt-pf-warn">';
			d.warnings.forEach(function(x){w+='<div>'+escAttr(x)+'</div>';});
			w+='</div>';
			html=w+html;
		}
		if(d.internal_links&&d.internal_links.samples&&d.internal_links.samples.length){
			html+='<details class="rt-pf-samples"><summary>'+C.s.samples_label+' ('+d.internal_links.samples.length+')</summary><ul>';
			d.internal_links.samples.forEach(function(x){
				var label=C.s.links_short.replace('%d',x.count);
				html+='<li><a href="'+escAttr(x.edit)+'" target="_blank">'+escAttr(x.title)+'</a> <span style="color:#6b7280">· '+escAttr(x.type)+' · '+label+'</span></li>';
			});
			html+='</ul></details>';
		}
		return html;
	}
	function openPreflight(id){
		var body=document.getElementById('rt-pf-body');
		body.innerHTML='<p style="padding:8px 0;color:#6b7280">'+C.s.pf_loading+'</p>';
		var btn=document.getElementById('rt-pf-confirm');
		btn.disabled=true;btn.dataset.rtId=String(id);btn.textContent=C.s.btn_confirm;
		CMP.id=id;CMP.loaded=false;CMP.strategy='mobile';
		var ifrA=document.getElementById('rt-cmp-iframe-a');var ifrB=document.getElementById('rt-cmp-iframe-b');
		if(ifrA)ifrA.removeAttribute('src');if(ifrB)ifrB.removeAttribute('src');
		var box=document.getElementById('rt-cmp-scores');if(box)box.innerHTML='';
		var btns=document.querySelectorAll('#rt-modal-preflight .rt-seg-btn');
		for(var i=0;i<btns.length;i++)btns[i].classList.toggle('is-active',btns[i].getAttribute('data-rt-strategy')==='mobile');
		activateTab('impact');
		openModal('rt-modal-preflight');
		call('promote/preflight?id='+encodeURIComponent(id),'GET',null,function(r){
			if(!r.ok||!r.data||!r.data.ok){
				body.innerHTML='<p style="color:#dc2626;padding:8px 0">'+escAttr((r.data&&r.data.error)||'Error')+'</p>';
				return;
			}
			body.innerHTML=renderPreflight(r.data);
			btn.disabled=!!r.data.already_promoted||!r.data.origin;
		});
	}
	function adoptConfirm(){
		var btn=document.getElementById('rt-pf-confirm');
		var id=parseInt(btn.dataset.rtId,10);
		if(!id)return;
		var opts={
			demote:document.getElementById('rt-pf-demote').checked,
			replace_menus:document.getElementById('rt-pf-menus').checked,
			rewrite_links:document.getElementById('rt-pf-links').checked,
			set_front:document.getElementById('rt-pf-front').checked
		};
		btn.disabled=true;btn.textContent=C.s.adopting;
		call('promote/adopt','POST',{id:id,opts:opts},function(r){
			var d=r.data||{};
			if(r.ok&&d.ok){
				toast(C.s.ok_adopted_full.replace('%m',d.menus_changed||0).replace('%p',d.posts_rewritten||0).replace('%l',d.links_rewritten||0),'ok');
				setTimeout(function(){location.reload();},1200);
			}else{
				btn.disabled=false;btn.textContent=C.s.btn_confirm;
				toast(C.s.err+(d.error||'desconocido'),'err');
			}
		});
	}
	function undo(id){
		if(!confirm(C.s.confirm_undo))return;
		call('promote/undo','POST',{id:id},function(r){
			var d=r.data||{};
			if(r.ok&&d.ok){toast(C.s.ok_undone,'ok');setTimeout(function(){location.reload();},900);}
			else toast(C.s.err+(d.error||'desconocido'),'err');
		});
	}
	function bulkOpen(){
		var checks=document.querySelectorAll('input[name="post[]"]:checked');
		var ids=[];
		for(var i=0;i<checks.length;i++){var v=parseInt(checks[i].value,10);if(v>0)ids.push(v);}
		if(!ids.length){alert(C.s.bulk_none);return;}
		if(ids.length>50){alert(C.s.bulk_max);return;}
		document.getElementById('rt-bulk-count').textContent=String(ids.length);
		document.getElementById('rt-bulk-progress').style.width='0%';
		document.getElementById('rt-bulk-log').innerHTML='';
		var run=document.getElementById('rt-bulk-run');
		run.dataset.rtIds=ids.join(',');
		run.disabled=false;run.textContent='Iniciar';
		delete run.dataset.rtFinished;
		openModal('rt-modal-bulk');
	}
	function bulkRun(){
		var btn=document.getElementById('rt-bulk-run');
		btn.disabled=true;
		var ids=btn.dataset.rtIds.split(',').map(function(x){return parseInt(x,10);}).filter(function(x){return x>0;});
		var opts={
			demote:document.getElementById('rt-bk-demote').checked,
			replace_menus:document.getElementById('rt-bk-menus').checked,
			rewrite_links:document.getElementById('rt-bk-links').checked
		};
		var total=ids.length, done=0, ok=0, fail=0;
		var log=document.getElementById('rt-bulk-log');
		var bar=document.getElementById('rt-bulk-progress');
		function next(){
			var chunk=ids.splice(0,5);
			if(!chunk.length){
				var div=document.createElement('div');
				div.style.cssText='margin-top:8px;font-weight:600';
				div.textContent=C.s.bulk_finished.replace('%o',ok).replace('%f',fail);
				log.appendChild(div);
				btn.disabled=false;btn.textContent=C.s.bulk_close;btn.dataset.rtFinished='1';
				return;
			}
			call('promote/bulk-adopt','POST',{ids:chunk,opts:opts},function(r){
				var d=r.data||{};
				var results=d.results||[];
				results.forEach(function(it){
					done++;if(it.ok)ok++;else fail++;
					var line=document.createElement('div');
					line.style.cssText='padding:2px 0';
					var status=it.ok?'<span style="color:#15803d">OK</span>':'<span style="color:#dc2626">'+escAttr(it.error||'error')+'</span>';
					line.innerHTML=status+' #'+it.id+(it.new_url?' '+escAttr(it.new_url):'');
					log.appendChild(line);
				});
				bar.style.width=Math.round(done*100/total)+'%';
				log.scrollTop=log.scrollHeight;
				next();
			});
		}
		next();
	}
	var CMP={strategy:'mobile',id:0,loaded:false};
	function fmtScore(v){return (v==null||isNaN(v))?'—':String(v);}
	function fmtMs(v){if(v==null||isNaN(v))return '—';if(Math.abs(v)>=1000)return (v/1000).toFixed(2)+' s';return Math.round(v)+' ms';}
	function fmtCls(v){if(v==null||isNaN(v))return '—';return Number(v).toFixed(3);}
	function deltaPill(d,inverted){
		if(d==null||isNaN(d))return '<span class="rt-cmp-d">·</span>';
		var n=Number(d), good=inverted?(n<0):(n>0);
		var cls=n===0?'':(good?'up':'down');
		var sign=n>0?'+':'';
		return '<span class="rt-cmp-d '+cls+'">'+sign+n+'</span>';
	}
	function scoreCard(label,a,b,delta){
		return '<div class="rt-cmp-card"><div class="rt-cmp-cat">'+label+'</div>'
			+'<div class="rt-cmp-vals"><span class="rt-cmp-a">'+fmtScore(a)+'</span><span class="rt-cmp-b">'+fmtScore(b)+'</span>'+deltaPill(delta,false)+'</div></div>';
	}
	function metricRow(label,m1,m2,fmt){
		var a=m1?m1.value:null, b=m2?m2.value:null;
		var d=(a!=null&&b!=null)?(b-a):null;
		var fa=fmt(a), fb=fmt(b);
		var dtxt='<span class="rt-cmp-d">·</span>';
		if(d!=null){
			var n=Number(d), good=n<0;
			var cls=n===0?'':(good?'up':'down');
			dtxt='<span class="rt-cmp-d '+cls+'">'+(n>0?'+':'')+fmt(n)+'</span>';
		}
		return '<tr><th>'+label+'</th><td>'+fa+'</td><td>'+fb+'</td><td>'+dtxt+'</td></tr>';
	}
	function clearCompareExtras(){
		var nodes=document.querySelectorAll('#rt-modal-preflight [data-rt-cmp-extra]');
		for(var i=0;i<nodes.length;i++)nodes[i].remove();
	}
	function loadCompare(fresh){
		if(!CMP.id)return;
		clearCompareExtras();
		var box=document.getElementById('rt-cmp-scores');
		box.innerHTML='<div class="rt-cmp-loading">'+C.s.cmp_loading+'</div>';
		var qs='id='+encodeURIComponent(CMP.id)+'&strategy='+encodeURIComponent(CMP.strategy)+(fresh?'&fresh=1':'');
		call('promote/diff?'+qs,'GET',null,function(r){
			var d=r.data||{};
			if(!r.ok||!d.ok){
				box.innerHTML='<div class="rt-cmp-error">'+escAttr(d.error||C.s.cmp_no_origin)+'</div>';
				return;
			}
			var ifrA=document.getElementById('rt-cmp-iframe-a');
			var ifrB=document.getElementById('rt-cmp-iframe-b');
			if(ifrA&&d.origin_url&&ifrA.src!==d.origin_url)ifrA.src=d.origin_url;
			if(ifrB&&d.rt_url&&ifrB.src!==d.rt_url)ifrB.src=d.rt_url;
			var oS=(d.origin&&d.origin.scores)||{}, rS=(d.rt&&d.rt.scores)||{}, dS=d.delta||{};
			box.innerHTML=''
				+scoreCard(C.s.cmp_perf, oS.performance, rS.performance, dS.performance)
				+scoreCard(C.s.cmp_seo, oS.seo, rS.seo, dS.seo)
				+scoreCard(C.s.cmp_a11y, oS.accessibility, rS.accessibility, dS.accessibility)
				+scoreCard(C.s.cmp_bp, oS.best_practices, rS.best_practices, dS.best_practices);
			var oM=(d.origin&&d.origin.metrics)||{}, rM=(d.rt&&d.rt.metrics)||{};
			var tbl='<table class="rt-pf-table" data-rt-cmp-extra style="margin:0 20px 16px"><thead><tr><th></th><th>Original</th><th>rt_page</th><th>Δ</th></tr></thead><tbody>';
			tbl+=metricRow('LCP',oM.lcp,rM.lcp,fmtMs);
			tbl+=metricRow('CLS',oM.cls,rM.cls,fmtCls);
			tbl+=metricRow('INP',oM.inp,rM.inp,fmtMs);
			tbl+=metricRow('FCP',oM.fcp,rM.fcp,fmtMs);
			tbl+=metricRow('TBT',oM.tbt,rM.tbt,fmtMs);
			tbl+=metricRow('TTI',oM.tti,rM.tti,fmtMs);
			tbl+='</tbody></table>';
			box.insertAdjacentHTML('afterend',tbl);
			if(d.origin&&d.origin.ok===false){
				box.insertAdjacentHTML('beforebegin','<div class="rt-cmp-error" data-rt-cmp-extra>'+escAttr(d.origin.error||'')+'</div>');
			}
			if(d.rt&&d.rt.ok===false){
				box.insertAdjacentHTML('beforebegin','<div class="rt-cmp-error" data-rt-cmp-extra>'+escAttr(d.rt.error||'')+'</div>');
			}
		});
	}
	function activateTab(name){
		var tabs=document.querySelectorAll('#rt-modal-preflight .rt-tab');
		var panels=document.querySelectorAll('#rt-modal-preflight .rt-tab-panel');
		for(var i=0;i<tabs.length;i++){
			var on=tabs[i].getAttribute('data-rt-tab')===name;
			tabs[i].classList.toggle('is-active',on);
			tabs[i].setAttribute('aria-selected',on?'true':'false');
		}
		for(var j=0;j<panels.length;j++){
			panels[j].classList.toggle('is-active',panels[j].getAttribute('data-rt-panel')===name);
		}
		if(name==='compare'&&!CMP.loaded&&CMP.id){CMP.loaded=true;loadCompare(false);}
	}
	function setStrategy(s){
		CMP.strategy=(s==='desktop')?'desktop':'mobile';
		var btns=document.querySelectorAll('#rt-modal-preflight .rt-seg-btn');
		for(var i=0;i<btns.length;i++)btns[i].classList.toggle('is-active',btns[i].getAttribute('data-rt-strategy')===CMP.strategy);
		CMP.loaded=false;
		loadCompare(false);
	}
	function run(action,id,trigger){
		if(action==='adopt'){openPreflight(id);return;}
		if(action==='undo'){undo(id);return;}
		if(action==='front'&&!confirm(C.s.confirm_front))return;
		if(trigger&&trigger.style)trigger.style.opacity=.5;
		var msg=action==='menus'?C.s.menus_doing:C.s.front_doing;
		toast(msg);
		var endpoint=action==='menus'?'promote/replace-in-menus':'promote/front-page';
		call(endpoint,'POST',{id:id},function(r){
			if(trigger&&trigger.style)trigger.style.opacity=1;
			var d=r.data||{};
			if(r.ok&&d.ok){
				if(action==='menus')toast(C.s.ok_menus.replace('%d',d.count||0),'ok');
				else if(action==='front'){toast(C.s.ok_front,'ok');setTimeout(function(){location.reload();},900);}
			}else{
				toast(C.s.err+(d.error||'desconocido'),'err');
			}
		});
	}
	document.addEventListener('click',function(e){
		var closer=e.target.closest('[data-rt-close]');
		if(closer){var box=closer.closest('[data-rt-modal]');if(box){closeModal(box.id);}return;}
		var tabBtn=e.target.closest('#rt-modal-preflight .rt-tab');
		if(tabBtn){activateTab(tabBtn.getAttribute('data-rt-tab'));return;}
		var segBtn=e.target.closest('#rt-modal-preflight .rt-seg-btn');
		if(segBtn){setStrategy(segBtn.getAttribute('data-rt-strategy'));return;}
		if(e.target.id==='rt-cmp-refresh'){CMP.loaded=false;loadCompare(true);return;}
		if(e.target.id==='rt-pf-confirm'){adoptConfirm();return;}
		if(e.target.id==='rt-bulk-run'){
			if(e.target.dataset.rtFinished==='1'){closeModal('rt-modal-bulk');return;}
			bulkRun();return;
		}
		if(e.target.id==='rt-bulk-open'||(e.target.parentNode&&e.target.parentNode.id==='rt-bulk-open')){bulkOpen();e.preventDefault();return;}
		var a=e.target.closest('.rt-promote-action');
		if(a){
			e.preventDefault();
			var action=a.getAttribute('data-rt-action');
			var id=parseInt(a.getAttribute('data-rt-id'),10);
			if(!action||!id)return;
			run(action,id,a);
		}
	});
	document.addEventListener('keydown',function(e){
		if(e.key==='Escape'){closeModal('rt-modal-preflight');closeModal('rt-modal-bulk');}
	});
})();
JS;
	}

	/* --------------------------------------------------------------- Util */

	private function source_url( int $post_id ): string {
		$src = (string) get_post_meta( $post_id, RT_Content_Sync::META_SOURCE_URL, true );
		if ( $src !== '' ) {
			return $src;
		}
		$json = (string) get_post_meta( $post_id, RT_Content_Sync::META_FRONTMATTER, true );
		if ( $json === '' ) {
			return '';
		}
		$front = json_decode( $json, true );
		return is_array( $front ) ? (string) ( $front['source_url'] ?? '' ) : '';
	}
}
