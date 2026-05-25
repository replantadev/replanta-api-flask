<?php
/**
 * Replanta AI admin dashboard.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class RAICCAdmin
{
    public const PAGE_SLUG = 'raicc-control-center';

    public function __construct(
        private RAICCAIConnectorService $connectorService,
        private RAICCBlueprintValidator $validator,
        private RAICCPageService $pageService,
        private RAICCRateLimiter $rateLimiter,
        private RAICCOperationLogger $logger,
        private RAICCThemeLayoutService $themeLayoutService,
        private RAICCElementorMigrationService $elementorMigrationService
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'adminMenu']);
        add_action('admin_post_raicc_create_prompt', [$this, 'handleCreatePrompt']);
        add_action('admin_post_raicc_set_status', [$this, 'handleSetStatus']);
        add_action('admin_post_raicc_apply_theme_prompt', [$this, 'handleThemePrompt']);
        add_action('admin_post_raicc_migrate_elementor', [$this, 'handleMigrateElementor']);
        add_action('admin_head', [$this, 'printAdminCss']);
    }

    public function printAdminCss(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'toplevel_page_' . self::PAGE_SLUG) {
            return;
        }
        echo '<style>';
        echo '.raicc-grid{display:grid;grid-template-columns:1.25fr .95fr;gap:18px;align-items:start}';
        echo '.raicc-card{background:#fff;border:1px solid #d6dfd8;border-radius:10px;padding:16px;box-shadow:0 1px 2px rgba(0,0,0,.03)}';
        echo '.raicc-title{display:flex;align-items:center;gap:8px;margin:0 0 10px;color:#153024}';
        echo '.raicc-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:0 0 14px}';
        echo '.raicc-kpi{border:1px solid #e1e7e3;border-radius:8px;padding:10px}';
        echo '.raicc-kpi b{display:block;font-size:1rem;color:#0f2d1f}';
        echo '.raicc-kpi span{color:#607267;font-size:.8rem}';
        echo '.raicc-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}';
        echo '.raicc-badge{display:inline-flex;align-items:center;gap:6px;padding:3px 8px;border-radius:999px;font-size:12px;font-weight:600}';
        echo '.raicc-ok{background:#dcfce7;color:#166534}';
        echo '.raicc-warn{background:#fef3c7;color:#92400e}';
        echo '.raicc-muted{color:#5f6d63}';
        echo '.raicc-inline-icon svg{vertical-align:middle}';
        echo '@media (max-width:1100px){.raicc-grid{grid-template-columns:1fr}.raicc-kpis{grid-template-columns:1fr}}';
        echo '</style>';
    }

    public function adminMenu(): void
    {
        add_menu_page(
            __('Replanta AI', 'replanta-ai-control-center'),
            __('Replanta AI', 'replanta-ai-control-center'),
            'edit_pages',
            self::PAGE_SLUG,
            [$this, 'renderPage'],
            'dashicons-superhero-alt',
            58
        );
    }

    public function renderPage(): void
    {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('Insufficient permissions', 'replanta-ai-control-center'));
        }

        $notice = isset($_GET['raicc_notice']) ? sanitize_text_field((string) $_GET['raicc_notice']) : '';
        $status = $this->connectorService->status();
        $pages = $this->pageService->latestPages(40);

        echo '<div class="wrap">';
        echo '<h1 style="display:flex;align-items:center;gap:8px;">' . RAICCIcons::svg('rocket', 20) . esc_html__('Replanta AI Control Center', 'replanta-ai-control-center') . '</h1>';
        echo '<p>' . esc_html__('Crea páginas semánticas por prompt y controla publicación en un solo panel.', 'replanta-ai-control-center') . '</p>';

        if ($notice !== '') {
            echo '<div class="notice notice-info"><p>' . esc_html($notice) . '</p></div>';
        }

        echo '<div class="raicc-kpis">';
        echo '<div class="raicc-kpi"><b>' . esc_html((string) count($pages)) . '</b><span>' . esc_html__('Páginas cargadas', 'replanta-ai-control-center') . '</span></div>';
        echo '<div class="raicc-kpi"><b>' . esc_html((string) ($status['active_connector'] ?? 'none')) . '</b><span>' . esc_html__('Conector activo', 'replanta-ai-control-center') . '</span></div>';
        echo '<div class="raicc-kpi"><b>' . esc_html(!empty($status['connector_health']['ok']) ? 'OK' : 'Warning') . '</b><span>' . esc_html__('Salud IA', 'replanta-ai-control-center') . '</span></div>';
        echo '</div>';

        echo '<div class="raicc-grid">';

        echo '<div class="raicc-card">';
        echo '<h2 class="raicc-title">' . RAICCIcons::svg('wand', 18) . esc_html__('Nueva página desde prompt', 'replanta-ai-control-center') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('raicc_create_prompt');
        echo '<input type="hidden" name="action" value="raicc_create_prompt">';
        echo '<p><label>' . esc_html__('Título', 'replanta-ai-control-center') . '<br><input type="text" name="title" class="regular-text" required></label></p>';
        echo '<p><label>' . esc_html__('Slug (opcional)', 'replanta-ai-control-center') . '<br><input type="text" name="slug" class="regular-text"></label></p>';
        echo '<p><label>' . esc_html__('Idioma', 'replanta-ai-control-center') . '<br><input type="text" name="lang" value="es" class="small-text"></label></p>';
        echo '<p><label>' . esc_html__('Prompt', 'replanta-ai-control-center') . '<br><textarea name="prompt" rows="7" class="large-text" required placeholder="Ejemplo: Crea una landing semántica de servicios de jardinería ecológica con CTA claro."></textarea></label></p>';
        submit_button(__('Crear borrador', 'replanta-ai-control-center'));
        echo '</form>';
        echo '</div>';

        echo '<div class="raicc-card">';
        echo '<h2 class="raicc-title">' . RAICCIcons::svg('gauge', 18) . esc_html__('Estado del conector', 'replanta-ai-control-center') . '</h2>';
        echo '<p><strong>' . esc_html__('Modo', 'replanta-ai-control-center') . ':</strong> ' . esc_html((string) ($status['mode'] ?? 'unknown')) . '</p>';
        echo '<p><strong>' . esc_html__('Conector activo', 'replanta-ai-control-center') . ':</strong> ' . esc_html((string) ($status['active_connector'] ?? 'none')) . '</p>';
        $health = isset($status['connector_health']) && is_array($status['connector_health']) ? $status['connector_health'] : [];
        $healthClass = !empty($health['ok']) ? 'raicc-badge raicc-ok' : 'raicc-badge raicc-warn';
        $healthIcon = !empty($health['ok']) ? RAICCIcons::svg('check', 14) : RAICCIcons::svg('warning', 14);
        echo '<p><strong>' . esc_html__('Health', 'replanta-ai-control-center') . ':</strong> <span class="' . esc_attr($healthClass) . '">' . $healthIcon . esc_html(!empty($health['ok']) ? 'OK' : 'Warning') . '</span></p>';
        if (!empty($health['message'])) {
            echo '<p class="raicc-muted">' . esc_html((string) $health['message']) . '</p>';
        }
        echo '</div>';

        echo '<div class="raicc-card">';
        echo '<h2 class="raicc-title">' . RAICCIcons::svg('sparkle', 18) . esc_html__('IA para Header/Footer del Tema', 'replanta-ai-control-center') . '</h2>';
        echo '<p class="raicc-muted">' . esc_html__('Describe el layout y la IA ajustará filas, columnas, módulos, textos y botones del tema.', 'replanta-ai-control-center') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('raicc_apply_theme_prompt');
        echo '<input type="hidden" name="action" value="raicc_apply_theme_prompt">';
        echo '<p><label>' . esc_html__('Prompt de layout', 'replanta-ai-control-center') . '<br><textarea name="prompt" rows="6" class="large-text" required placeholder="Ejemplo: Header main 3 columnas con logo, menú y botón Presupuesto. Footer main 3 columnas con texto, menú y redes; bottom 1 columna copyright."></textarea></label></p>';
        submit_button(__('Generar y aplicar layout', 'replanta-ai-control-center'), 'secondary');
        echo '</form>';
        echo '</div>';

        echo '<div class="raicc-card">';
        echo '<h2 class="raicc-title">' . RAICCIcons::svg('wand', 18) . esc_html__('Migrar Elementor a Tema Semántico', 'replanta-ai-control-center') . '</h2>';
        echo '<p class="raicc-muted">' . esc_html__('Convierte páginas Elementor a contenido semántico compatible con el tema. Guarda backup y opción de desactivar metadatos Elementor.', 'replanta-ai-control-center') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('raicc_migrate_elementor');
        echo '<input type="hidden" name="action" value="raicc_migrate_elementor">';
        echo '<p><label><input type="checkbox" name="disable_elementor_meta" value="1"> ' . esc_html__('Eliminar metadatos Elementor tras migrar (recomendado para rendimiento final)', 'replanta-ai-control-center') . '</label></p>';
        echo '<p><label><input type="checkbox" name="migrate_theme_builder" value="1" checked> ' . esc_html__('Migrar plantillas Header/Footer de Elementor al tema (módulo HTML)', 'replanta-ai-control-center') . '</label></p>';
        submit_button(__('Migrar páginas Elementor', 'replanta-ai-control-center'), 'secondary');
        echo '</form>';
        echo '</div>';

        echo '</div>';

        echo '<div class="raicc-card" style="margin-top:16px;">';
        echo '<h2 class="raicc-title">' . RAICCIcons::svg('sparkle', 18) . esc_html__('Páginas recientes', 'replanta-ai-control-center') . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Título', 'replanta-ai-control-center') . '</th><th>' . esc_html__('Estado', 'replanta-ai-control-center') . '</th><th>' . esc_html__('Modificado', 'replanta-ai-control-center') . '</th><th>' . esc_html__('Acciones', 'replanta-ai-control-center') . '</th></tr></thead><tbody>';

        foreach ($pages as $page) {
            $edit = get_edit_post_link((int) $page->ID, 'raw');
            $view = get_permalink((int) $page->ID);
            echo '<tr>';
            echo '<td><a href="' . esc_url((string) $edit) . '">' . esc_html((string) $page->post_title) . '</a></td>';
            echo '<td>' . esc_html((string) $page->post_status) . '</td>';
            echo '<td>' . esc_html((string) get_the_modified_date('', (int) $page->ID)) . '</td>';
            echo '<td><div class="raicc-actions">';

            echo '<a class="button" href="' . esc_url((string) $edit) . '">' . esc_html__('Editar', 'replanta-ai-control-center') . '</a>';
            if ($view !== '') {
                echo '<a class="button" href="' . esc_url((string) $view) . '" target="_blank" rel="noopener">' . esc_html__('Ver', 'replanta-ai-control-center') . '</a>';
            }

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-flex;">';
            wp_nonce_field('raicc_set_status_' . (int) $page->ID);
            echo '<input type="hidden" name="action" value="raicc_set_status">';
            echo '<input type="hidden" name="post_id" value="' . (int) $page->ID . '">';
            echo '<input type="hidden" name="target_status" value="' . esc_attr($page->post_status === 'publish' ? 'draft' : 'publish') . '">';
            submit_button(
                $page->post_status === 'publish' ? __('Despublicar', 'replanta-ai-control-center') : __('Publicar', 'replanta-ai-control-center'),
                'secondary',
                'submit',
                false
            );
            echo '</form>';

            echo '</div></td>';
            echo '</tr>';
        }

        if ($pages === []) {
            echo '<tr><td colspan="4">' . esc_html__('No hay páginas todavía.', 'replanta-ai-control-center') . '</td></tr>';
        }

        echo '</tbody></table>';
        echo '</div>';

        echo '</div>';
    }

    public function handleCreatePrompt(): void
    {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('Insufficient permissions', 'replanta-ai-control-center'));
        }
        check_admin_referer('raicc_create_prompt');

        $userId = get_current_user_id();
        $limit = $this->rateLimiter->check('admin_create_from_prompt', $userId, 8, 60);
        if (empty($limit['allowed'])) {
            $this->logger->log('rate_limited', [
                'bucket' => 'admin_create_from_prompt',
                'user_id' => $userId,
                'retry_after' => (int) ($limit['retry_after'] ?? 60),
            ]);

            $this->redirectNotice(__('Límite temporal alcanzado. Espera unos segundos e intenta nuevamente.', 'replanta-ai-control-center'));
        }

        $prompt = isset($_POST['prompt']) ? trim((string) wp_unslash($_POST['prompt'])) : '';
        $title = isset($_POST['title']) ? sanitize_text_field((string) wp_unslash($_POST['title'])) : '';
        $slug = isset($_POST['slug']) ? sanitize_title((string) wp_unslash($_POST['slug'])) : '';
        $lang = isset($_POST['lang']) ? sanitize_text_field((string) wp_unslash($_POST['lang'])) : 'es';

        if ($prompt === '' || $title === '') {
            $this->redirectNotice(__('Debes completar título y prompt.', 'replanta-ai-control-center'));
        }

        $connector = $this->connectorService->execute('create_page', [
            'prompt' => $prompt,
            'title' => $title,
            'slug' => $slug,
            'lang' => $lang,
        ], [
            'user_id' => $userId,
        ]);

        $this->logger->log('admin_create_from_prompt', [
            'user_id' => $userId,
            'ok' => !empty($connector['ok']) ? 1 : 0,
            'connector_id' => (string) ($connector['connector_id'] ?? ''),
            'latency_ms' => (int) ($connector['latency_ms'] ?? 0),
        ]);

        if (empty($connector['ok'])) {
            $this->redirectNotice(__('Falló la ejecución del conector IA.', 'replanta-ai-control-center'));
        }

        $blueprint = isset($connector['blueprint_json']) && is_array($connector['blueprint_json']) ? $connector['blueprint_json'] : [];
        $valid = $this->validator->validate($blueprint);
        if (empty($valid['ok'])) {
            $this->redirectNotice(__('La salida IA no pasó validación semántica.', 'replanta-ai-control-center'));
        }

        $created = $this->pageService->createPageFromBlueprint($blueprint, $prompt);
        if (empty($created['ok'])) {
            $this->redirectNotice(__('No se pudo crear la página.', 'replanta-ai-control-center'));
        }

        $this->redirectNotice(__('Borrador creado correctamente.', 'replanta-ai-control-center'));
    }

    public function handleSetStatus(): void
    {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('Insufficient permissions', 'replanta-ai-control-center'));
        }

        $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $status = isset($_POST['target_status']) ? sanitize_key((string) $_POST['target_status']) : '';
        check_admin_referer('raicc_set_status_' . $postId);

        $userId = get_current_user_id();
        $limitBucket = $status === 'publish' ? 'admin_publish' : 'admin_unpublish';
        $limit = $this->rateLimiter->check($limitBucket, $userId, 20, 60);
        if (empty($limit['allowed'])) {
            $this->logger->log('rate_limited', [
                'bucket' => $limitBucket,
                'user_id' => $userId,
                'retry_after' => (int) ($limit['retry_after'] ?? 60),
            ]);

            $this->redirectNotice(__('Límite temporal alcanzado para cambios de estado.', 'replanta-ai-control-center'));
        }

        if ($postId <= 0 || ($status !== 'publish' && $status !== 'draft')) {
            $this->redirectNotice(__('Petición inválida.', 'replanta-ai-control-center'));
        }

        $res = $this->pageService->setPageStatus($postId, $status);
        $this->logger->log('admin_set_status', [
            'user_id' => $userId,
            'post_id' => $postId,
            'target_status' => $status,
            'ok' => !empty($res['ok']) ? 1 : 0,
            'error' => isset($res['error']) ? (string) $res['error'] : '',
        ]);

        if (empty($res['ok'])) {
            if (isset($res['gate']) && is_array($res['gate'])) {
                $blockers = isset($res['gate']['blockers']) && is_array($res['gate']['blockers'])
                    ? implode('; ', array_map('strval', $res['gate']['blockers']))
                    : '';

                $this->redirectNotice(__('Bloqueado por validaciones de publicación: ', 'replanta-ai-control-center') . $blockers);
            }

            $this->redirectNotice(__('No se pudo actualizar el estado.', 'replanta-ai-control-center'));
        }

        $this->redirectNotice($status === 'publish'
            ? __('Página publicada.', 'replanta-ai-control-center')
            : __('Página despublicada.', 'replanta-ai-control-center'));
    }

    public function handleThemePrompt(): void
    {
        if (!current_user_can('customize')) {
            wp_die(esc_html__('Insufficient permissions', 'replanta-ai-control-center'));
        }

        check_admin_referer('raicc_apply_theme_prompt');

        $userId = get_current_user_id();
        $limit = $this->rateLimiter->check('admin_theme_layout_prompt', $userId, 8, 60);
        if (empty($limit['allowed'])) {
            $this->redirectNotice(__('Límite temporal alcanzado para prompts de layout.', 'replanta-ai-control-center'));
        }

        $prompt = isset($_POST['prompt']) ? trim((string) wp_unslash($_POST['prompt'])) : '';
        if ($prompt === '') {
            $this->redirectNotice(__('Debes escribir un prompt para el layout del tema.', 'replanta-ai-control-center'));
        }

        $connector = $this->connectorService->execute('theme_layout', [
            'prompt' => $prompt,
            'current_layout' => $this->themeLayoutService->currentLayout(),
        ], [
            'user_id' => $userId,
        ]);

        $layout = isset($connector['layout_json']) && is_array($connector['layout_json']) ? $connector['layout_json'] : [];
        $applied = $this->themeLayoutService->applyLayout($layout);

        $this->logger->log('admin_theme_layout_apply', [
            'user_id' => $userId,
            'ok' => !empty($applied['ok']) ? 1 : 0,
            'connector_id' => (string) ($connector['connector_id'] ?? ''),
            'latency_ms' => (int) ($connector['latency_ms'] ?? 0),
            'error' => isset($applied['error']) ? (string) $applied['error'] : '',
        ]);

        if (empty($applied['ok'])) {
            $this->redirectNotice(__('No se pudo aplicar el layout IA al tema.', 'replanta-ai-control-center'));
        }

        $this->redirectNotice(__('Layout de cabecera/footer aplicado correctamente.', 'replanta-ai-control-center'));
    }

    public function handleMigrateElementor(): void
    {
        if (!current_user_can('edit_pages')) {
            wp_die(esc_html__('Insufficient permissions', 'replanta-ai-control-center'));
        }

        check_admin_referer('raicc_migrate_elementor');

        $userId = get_current_user_id();
        $limit = $this->rateLimiter->check('admin_migrate_elementor', $userId, 2, 60);
        if (empty($limit['allowed'])) {
            $this->redirectNotice(__('Límite temporal alcanzado para migraciones.', 'replanta-ai-control-center'));
        }

        $disableElementorMeta = isset($_POST['disable_elementor_meta']) && (string) $_POST['disable_elementor_meta'] === '1';
        $migrateThemeBuilder = isset($_POST['migrate_theme_builder']) && (string) $_POST['migrate_theme_builder'] === '1';

        $pages = $this->elementorMigrationService->migrateAllPages($disableElementorMeta, 500);
        $theme = $migrateThemeBuilder
            ? $this->elementorMigrationService->migrateThemeBuilderTemplatesToThemeMods()
            : ['ok' => true, 'header_found' => false, 'footer_found' => false];

        $this->logger->log('admin_elementor_migration_run', [
            'user_id' => $userId,
            'disable_elementor_meta' => $disableElementorMeta ? 1 : 0,
            'migrate_theme_builder' => $migrateThemeBuilder ? 1 : 0,
            'pages_found' => (int) ($pages['found'] ?? 0),
            'pages_migrated' => (int) ($pages['migrated'] ?? 0),
            'pages_failed' => (int) ($pages['failed'] ?? 0),
            'theme_header_found' => !empty($theme['header_found']) ? 1 : 0,
            'theme_footer_found' => !empty($theme['footer_found']) ? 1 : 0,
        ]);

        $notice = sprintf(
            __('Migración completada. Encontradas: %1$d | Migradas: %2$d | Fallidas: %3$d', 'replanta-ai-control-center'),
            (int) ($pages['found'] ?? 0),
            (int) ($pages['migrated'] ?? 0),
            (int) ($pages['failed'] ?? 0)
        );

        $this->redirectNotice($notice);
    }

    private function redirectNotice(string $message): void
    {
        $url = add_query_arg([
            'page' => self::PAGE_SLUG,
            'raicc_notice' => $message,
        ], admin_url('admin.php'));

        wp_safe_redirect($url);
        exit;
    }
}
