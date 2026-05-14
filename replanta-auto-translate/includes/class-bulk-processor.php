<?php
/**
 * Clase para procesamiento masivo de traducciones
 */

if (!defined('ABSPATH')) {
    exit;
}

class Replanta_Auto_Translate_Bulk_Processor {
    
    private static $instance = null;
    
    /**
     * Clave de opcion para estado del proceso
     */
    const PROCESS_STATE_OPTION = 'replanta_translate_process_state';
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor vacio
    }
    
    /**
     * Traducir un post individual
     */
    public function translate_single_post($post_id) {
        $settings = Replanta_Auto_Translate::get_settings();
        $source_lang = $settings['source_language'];
        $target_lang = $settings['target_language'];
        
        // Iniciar log
        $log_id = $this->create_log_entry($post_id, $source_lang, $target_lang);
        
        try {
            // Extraer contenido
            $extractor = Replanta_Auto_Translate_Content_Extractor::instance();
            $content = $extractor->extract($post_id);
            
            if (is_wp_error($content)) {
                $this->update_log_entry($log_id, 'error', null, $content->get_error_message());
                return $content;
            }
            
            // Traducir contenido
            $translated = $extractor->translate_content($content, $source_lang, $target_lang);
            
            // Crear traduccion en Polylang
            $polylang = Replanta_Auto_Translate_Polylang_Bridge::instance();
            $new_post_id = $polylang->create_translation($post_id, $target_lang, $translated);
            
            if (is_wp_error($new_post_id)) {
                $this->update_log_entry($log_id, 'error', null, $new_post_id->get_error_message());
                return $new_post_id;
            }
            
            // Guardar campos personalizados traducidos
            foreach ($translated['custom_fields'] as $key => $value) {
                update_post_meta($new_post_id, $key, $value);
            }
            
            // Actualizar log
            $this->update_log_entry($log_id, 'completed', $new_post_id);
            
            return [
                'success' => true,
                'source_id' => $post_id,
                'translated_id' => $new_post_id,
                'title' => $translated['title'],
            ];
            
        } catch (Exception $e) {
            $this->update_log_entry($log_id, 'error', null, $e->getMessage());
            return new WP_Error('translation_exception', $e->getMessage());
        }
    }
    
    /**
     * Iniciar proceso de traduccion masiva
     */
    public function start_bulk_process($post_type, $post_ids = []) {
        $settings = Replanta_Auto_Translate::get_settings();
        $source_lang = $settings['source_language'];
        $target_lang = $settings['target_language'];
        
        // Si no se especifican IDs, obtener todos los posts sin traducir
        if (empty($post_ids)) {
            $polylang = Replanta_Auto_Translate_Polylang_Bridge::instance();
            $untranslated = $polylang->get_untranslated_posts($post_type, $source_lang, $target_lang);
            $post_ids = wp_list_pluck($untranslated, 'ID');
        }
        
        if (empty($post_ids)) {
            return new WP_Error('no_posts', 'No hay posts para traducir');
        }
        
        // Crear estado del proceso
        $state = [
            'status' => 'running',
            'post_type' => $post_type,
            'total' => count($post_ids),
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'post_ids' => $post_ids,
            'current_index' => 0,
            'results' => [],
            'started_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];
        
        update_option(self::PROCESS_STATE_OPTION, $state);
        
        return [
            'success' => true,
            'total' => count($post_ids),
            'state' => $state,
        ];
    }
    
    /**
     * Tiempo maximo de ejecucion por batch (segundos)
     */
    const MAX_BATCH_TIME = 25;
    
    /**
     * Procesar el siguiente lote de posts
     */
    public function process_next_batch() {
        $state = get_option(self::PROCESS_STATE_OPTION);
        
        if (!$state || $state['status'] !== 'running') {
            return new WP_Error('no_process', 'No hay proceso activo');
        }
        
        $settings = Replanta_Auto_Translate::get_settings();
        $batch_size = intval($settings['batch_size'] ?? 1);
        // Limitar batch size para evitar timeouts
        $batch_size = min($batch_size, 3);
        
        $post_ids = $state['post_ids'];
        $current_index = $state['current_index'];
        $batch_start_time = time();
        
        // Obtener siguientes posts para procesar
        $batch = array_slice($post_ids, $current_index, $batch_size);
        
        if (empty($batch)) {
            // Proceso completado
            $state['status'] = 'completed';
            $state['updated_at'] = current_time('mysql');
            update_option(self::PROCESS_STATE_OPTION, $state);
            
            return [
                'success' => true,
                'completed' => true,
                'state' => $state,
            ];
        }
        
        $results = [];
        
        foreach ($batch as $post_id) {
            // Verificar tiempo de ejecucion antes de cada post
            if ((time() - $batch_start_time) >= self::MAX_BATCH_TIME) {
                // Guardar estado y salir para evitar timeout
                $state['updated_at'] = current_time('mysql');
                update_option(self::PROCESS_STATE_OPTION, $state);
                break;
            }
            
            $result = $this->translate_single_post($post_id);
            
            $state['processed']++;
            $state['current_index']++;
            
            if (is_wp_error($result)) {
                $state['failed']++;
                $results[] = [
                    'post_id' => $post_id,
                    'success' => false,
                    'error' => $result->get_error_message(),
                ];
                
                // Si es error de rate limit, pausar mas tiempo
                $error_msg = $result->get_error_message();
                if (stripos($error_msg, 'rate') !== false || stripos($error_msg, 'limit') !== false) {
                    sleep(5); // Pausa de 5 segundos para rate limits
                }
            } else {
                $state['successful']++;
                $results[] = [
                    'post_id' => $post_id,
                    'success' => true,
                    'translated_id' => $result['translated_id'],
                    'title' => $result['title'],
                ];
            }
            
            $state['updated_at'] = current_time('mysql');
            update_option(self::PROCESS_STATE_OPTION, $state);
        }
        
        // Verificar si hay mas posts
        $has_more = $state['current_index'] < count($post_ids);
        
        if (!$has_more) {
            $state['status'] = 'completed';
            update_option(self::PROCESS_STATE_OPTION, $state);
        }
        
        return [
            'success' => true,
            'completed' => !$has_more,
            'results' => $results,
            'state' => [
                'total' => $state['total'],
                'processed' => $state['processed'],
                'successful' => $state['successful'],
                'failed' => $state['failed'],
                'status' => $state['status'],
            ],
        ];
    }
    
    /**
     * Cancelar proceso activo
     */
    public function cancel_process() {
        $state = get_option(self::PROCESS_STATE_OPTION);
        
        if ($state) {
            $state['status'] = 'cancelled';
            $state['updated_at'] = current_time('mysql');
            update_option(self::PROCESS_STATE_OPTION, $state);
        }
        
        return ['success' => true];
    }
    
    /**
     * Obtener estado actual del proceso
     */
    public function get_process_state() {
        $state = get_option(self::PROCESS_STATE_OPTION);
        
        if (!$state) {
            return [
                'status' => 'idle',
                'total' => 0,
                'processed' => 0,
            ];
        }
        
        return [
            'status' => $state['status'],
            'total' => $state['total'],
            'processed' => $state['processed'],
            'successful' => $state['successful'],
            'failed' => $state['failed'],
            'started_at' => $state['started_at'],
            'updated_at' => $state['updated_at'],
        ];
    }
    
    /**
     * Limpiar estado del proceso
     */
    public function clear_process_state() {
        delete_option(self::PROCESS_STATE_OPTION);
        return ['success' => true];
    }
    
    /**
     * Crear entrada en el log
     */
    private function create_log_entry($post_id, $source_lang, $target_lang) {
        global $wpdb;
        
        $settings = Replanta_Auto_Translate::get_settings();
        $engine = $settings['default_engine'] ?? 'openai';
        
        $table_name = $wpdb->prefix . 'replanta_translate_log';
        
        $wpdb->insert($table_name, [
            'post_id' => $post_id,
            'source_lang' => $source_lang,
            'target_lang' => $target_lang,
            'status' => 'processing',
            'engine' => $engine,
            'created_at' => current_time('mysql'),
        ]);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Actualizar entrada en el log
     */
    private function update_log_entry($log_id, $status, $translated_post_id = null, $error_message = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'replanta_translate_log';
        
        $data = [
            'status' => $status,
            'completed_at' => current_time('mysql'),
        ];
        
        if ($translated_post_id) {
            $data['translated_post_id'] = $translated_post_id;
        }
        
        if ($error_message) {
            $data['error_message'] = $error_message;
        }
        
        $wpdb->update($table_name, $data, ['id' => $log_id]);
    }
    
    /**
     * Traducir posts seleccionados
     */
    public function translate_selected($post_ids) {
        if (empty($post_ids) || !is_array($post_ids)) {
            return new WP_Error('no_posts', 'No se especificaron posts');
        }
        
        // Limpiar estado anterior
        $this->clear_process_state();
        
        // Iniciar proceso con los posts seleccionados
        $result = $this->start_bulk_process('mixed', $post_ids);
        
        return $result;
    }
    
    /**
     * Obtener estimacion de tiempo para traducir posts
     */
    public function estimate_bulk_time($post_ids) {
        $extractor = Replanta_Auto_Translate_Content_Extractor::instance();
        $total_seconds = 0;
        
        foreach ($post_ids as $post_id) {
            $total_seconds += $extractor->estimate_translation_time($post_id);
        }
        
        // Agregar overhead por requests API
        $settings = Replanta_Auto_Translate::get_settings();
        $delay = intval($settings['delay_between_requests'] ?? 1000) / 1000; // ms a segundos
        $total_seconds += count($post_ids) * $delay;
        
        return [
            'seconds' => $total_seconds,
            'formatted' => $this->format_time($total_seconds),
        ];
    }
    
    /**
     * Formatear tiempo en formato legible
     */
    private function format_time($seconds) {
        if ($seconds < 60) {
            return $seconds . ' segundos';
        } elseif ($seconds < 3600) {
            $minutes = ceil($seconds / 60);
            return $minutes . ' minutos';
        } else {
            $hours = floor($seconds / 3600);
            $minutes = ceil(($seconds % 3600) / 60);
            return $hours . ' horas ' . $minutes . ' minutos';
        }
    }
    
    /**
     * Obtener historial de traducciones recientes
     */
    public function get_recent_translations($limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'replanta_translate_log';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Obtener estadisticas generales
     */
    public function get_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'replanta_translate_log';
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $completed = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'");
        $failed = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'error'");
        
        return [
            'total_translations' => intval($total),
            'successful' => intval($completed),
            'failed' => intval($failed),
            'success_rate' => $total > 0 ? round(($completed / $total) * 100) : 0,
        ];
    }
    
    /**
     * Reintentar traducciones fallidas
     */
    public function retry_failed() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'replanta_translate_log';
        
        $failed = $wpdb->get_results(
            "SELECT DISTINCT post_id FROM $table_name WHERE status = 'error'"
        );
        
        $post_ids = wp_list_pluck($failed, 'post_id');
        
        if (empty($post_ids)) {
            return new WP_Error('no_failed', 'No hay traducciones fallidas para reintentar');
        }
        
        return $this->translate_selected($post_ids);
    }
}
