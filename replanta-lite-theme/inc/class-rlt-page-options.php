<?php
/**
 * Per-page visual behavior options (Astra-like overrides).
 *
 * @package ReplantaLite
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class RLTPageOptions
{
    public const META_HEADER_MODE = '_rlt_header_mode';
    public const META_HEADER_FIXED = '_rlt_header_fixed';
    public const META_PALETTE = '_rlt_palette_preset';
    public const META_HIDE_TITLE = '_rlt_hide_title';

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post_page', [$this, 'savePageOptions']);
    }

    public function addMetaBoxes(): void
    {
        add_meta_box(
            'rlt-page-options',
            __('Replanta Display Options', 'replanta-lite'),
            [$this, 'renderMetaBox'],
            'page',
            'side',
            'default'
        );
    }

    public function renderMetaBox(WP_Post $post): void
    {
        wp_nonce_field('rlt_page_options_save', 'rlt_page_options_nonce');

        $headerMode = (string) get_post_meta($post->ID, self::META_HEADER_MODE, true);
        $headerFixed = (string) get_post_meta($post->ID, self::META_HEADER_FIXED, true);
        $palette = (string) get_post_meta($post->ID, self::META_PALETTE, true);

        echo '<p><label for="rlt_header_mode"><strong>' . esc_html__('Header mode', 'replanta-lite') . '</strong></label><br>';
        echo '<select id="rlt_header_mode" name="rlt_header_mode" style="width:100%;">';
        foreach (['global' => __('Global', 'replanta-lite'), 'normal' => __('Normal', 'replanta-lite'), 'transparent' => __('Transparent', 'replanta-lite')] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($headerMode !== '' ? $headerMode : 'global', $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p>';

        echo '<p><label for="rlt_header_fixed"><strong>' . esc_html__('Header fixed', 'replanta-lite') . '</strong></label><br>';
        echo '<select id="rlt_header_fixed" name="rlt_header_fixed" style="width:100%;">';
        foreach (['global' => __('Global', 'replanta-lite'), '0' => __('Disabled', 'replanta-lite'), '1' => __('Enabled', 'replanta-lite')] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($headerFixed !== '' ? $headerFixed : 'global', $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p>';

        echo '<p><label for="rlt_palette_preset"><strong>' . esc_html__('Palette override', 'replanta-lite') . '</strong></label><br>';
        echo '<select id="rlt_palette_preset" name="rlt_palette_preset" style="width:100%;">';
        foreach (['global' => __('Global', 'replanta-lite'), 'replanta' => __('Replanta', 'replanta-lite'), 'forest' => __('Forest', 'replanta-lite'), 'ocean' => __('Ocean', 'replanta-lite'), 'contrast' => __('High Contrast', 'replanta-lite')] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($palette !== '' ? $palette : 'global', $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p>';

        $hideTitle = (string) get_post_meta($post->ID, self::META_HIDE_TITLE, true);
        echo '<p><label for="rlt_hide_title"><strong>' . esc_html__('Título de página', 'replanta-lite') . '</strong></label><br>';
        echo '<select id="rlt_hide_title" name="rlt_hide_title" style="width:100%;">';
        foreach (['global' => __('Global', 'replanta-lite'), 'show' => __('Mostrar', 'replanta-lite'), 'hide' => __('Ocultar', 'replanta-lite')] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($hideTitle !== '' ? $hideTitle : 'global', $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></p>';
    }

    public function savePageOptions(int $postId): void
    {
        if (!isset($_POST['rlt_page_options_nonce']) || !wp_verify_nonce((string) $_POST['rlt_page_options_nonce'], 'rlt_page_options_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $headerMode = isset($_POST['rlt_header_mode']) ? sanitize_key((string) wp_unslash($_POST['rlt_header_mode'])) : 'global';
        $headerFixed = isset($_POST['rlt_header_fixed']) ? sanitize_key((string) wp_unslash($_POST['rlt_header_fixed'])) : 'global';
        $palette = isset($_POST['rlt_palette_preset']) ? sanitize_key((string) wp_unslash($_POST['rlt_palette_preset'])) : 'global';

        if (!in_array($headerMode, ['global', 'normal', 'transparent'], true)) {
            $headerMode = 'global';
        }
        if (!in_array($headerFixed, ['global', '0', '1'], true)) {
            $headerFixed = 'global';
        }
        if (!in_array($palette, ['global', 'replanta', 'forest', 'ocean', 'contrast'], true)) {
            $palette = 'global';
        }

        $hideTitle = isset($_POST['rlt_hide_title']) ? sanitize_key((string) wp_unslash($_POST['rlt_hide_title'])) : 'global';
        if (!in_array($hideTitle, ['global', 'show', 'hide'], true)) {
            $hideTitle = 'global';
        }

        update_post_meta($postId, self::META_HEADER_MODE, $headerMode);
        update_post_meta($postId, self::META_HEADER_FIXED, $headerFixed);
        update_post_meta($postId, self::META_PALETTE, $palette);
        update_post_meta($postId, self::META_HIDE_TITLE, $hideTitle);
    }
}
