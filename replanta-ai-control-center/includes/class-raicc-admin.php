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
        private RAICCPageService $pageService
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'adminMenu']);
        add_action('admin_post_raicc_create_prompt', [$this, 'handleCreatePrompt']);
        add_action('admin_post_raicc_set_status', [$this, 'handleSetStatus']);
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
        echo '<h1>' . esc_html__('Replanta AI Control Center', 'replanta-ai-control-center') . '</h1>';
        echo '<p>' . esc_html__('Crea páginas semánticas por prompt y controla publicación en un solo panel.', 'replanta-ai-control-center') . '</p>';

        if ($notice !== '') {
            echo '<div class="notice notice-info"><p>' . esc_html($notice) . '</p></div>';
        }

        echo '<div style="display:grid;grid-template-columns:1.1fr 1fr;gap:18px;align-items:start;">';

        echo '<div class="postbox" style="padding:16px;">';
        echo '<h2>' . esc_html__('Nueva página desde prompt', 'replanta-ai-control-center') . '</h2>';
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

        echo '<div class="postbox" style="padding:16px;">';
        echo '<h2>' . esc_html__('Estado del conector', 'replanta-ai-control-center') . '</h2>';
        echo '<p><strong>' . esc_html__('Modo', 'replanta-ai-control-center') . ':</strong> ' . esc_html((string) ($status['mode'] ?? 'unknown')) . '</p>';
        echo '<p><strong>' . esc_html__('Conector activo', 'replanta-ai-control-center') . ':</strong> ' . esc_html((string) ($status['active_connector'] ?? 'none')) . '</p>';
        $health = isset($status['connector_health']) && is_array($status['connector_health']) ? $status['connector_health'] : [];
        echo '<p><strong>' . esc_html__('Health', 'replanta-ai-control-center') . ':</strong> ' . esc_html(!empty($health['ok']) ? 'OK' : 'Warning') . '</p>';
        if (!empty($health['message'])) {
            echo '<p>' . esc_html((string) $health['message']) . '</p>';
        }
        echo '</div>';

        echo '</div>';

        echo '<div class="postbox" style="padding:16px;margin-top:16px;">';
        echo '<h2>' . esc_html__('Páginas recientes', 'replanta-ai-control-center') . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Título', 'replanta-ai-control-center') . '</th><th>' . esc_html__('Estado', 'replanta-ai-control-center') . '</th><th>' . esc_html__('Modificado', 'replanta-ai-control-center') . '</th><th>' . esc_html__('Acciones', 'replanta-ai-control-center') . '</th></tr></thead><tbody>';

        foreach ($pages as $page) {
            $edit = get_edit_post_link((int) $page->ID, 'raw');
            $view = get_permalink((int) $page->ID);
            echo '<tr>';
            echo '<td><a href="' . esc_url((string) $edit) . '">' . esc_html((string) $page->post_title) . '</a></td>';
            echo '<td>' . esc_html((string) $page->post_status) . '</td>';
            echo '<td>' . esc_html((string) get_the_modified_date('', (int) $page->ID)) . '</td>';
            echo '<td style="display:flex;gap:8px;align-items:center;">';

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

            echo '</td>';
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
            'user_id' => get_current_user_id(),
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

        if ($postId <= 0 || ($status !== 'publish' && $status !== 'draft')) {
            $this->redirectNotice(__('Petición inválida.', 'replanta-ai-control-center'));
        }

        $res = $this->pageService->setPageStatus($postId, $status);
        if (empty($res['ok'])) {
            $this->redirectNotice(__('No se pudo actualizar el estado.', 'replanta-ai-control-center'));
        }

        $this->redirectNotice($status === 'publish'
            ? __('Página publicada.', 'replanta-ai-control-center')
            : __('Página despublicada.', 'replanta-ai-control-center'));
    }

    private function redirectNotice(string $message): void
    {
        $url = add_query_arg([
            'page' => self::PAGE_SLUG,
            'raicc_notice' => rawurlencode($message),
        ], admin_url('admin.php'));

        wp_safe_redirect($url);
        exit;
    }
}
