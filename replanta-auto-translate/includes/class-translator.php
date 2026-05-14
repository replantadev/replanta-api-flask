<?php
/**
 * Clase para traduccion usando OpenAI y Google Translate
 */

if (!defined('ABSPATH')) {
    exit;
}

class Replanta_Auto_Translate_Translator {
    
    private static $instance = null;
    
    /**
     * Mapeo de codigos de idioma a nombres completos
     */
    private $language_names = [
        'es' => 'Spanish',
        'en' => 'English',
        'fr' => 'French',
        'de' => 'German',
        'it' => 'Italian',
        'pt' => 'Portuguese',
        'nl' => 'Dutch',
        'ru' => 'Russian',
        'zh' => 'Chinese',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'ar' => 'Arabic',
        'pl' => 'Polish',
        'sv' => 'Swedish',
        'da' => 'Danish',
        'fi' => 'Finnish',
        'no' => 'Norwegian',
        'cs' => 'Czech',
        'hu' => 'Hungarian',
        'ro' => 'Romanian',
        'bg' => 'Bulgarian',
        'el' => 'Greek',
        'tr' => 'Turkish',
        'he' => 'Hebrew',
        'th' => 'Thai',
        'vi' => 'Vietnamese',
        'id' => 'Indonesian',
        'ms' => 'Malay',
        'ca' => 'Catalan',
        'eu' => 'Basque',
        'gl' => 'Galician',
    ];
    
    /**
     * Cache de traducciones para evitar duplicados
     */
    private $translation_cache = [];
    
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
     * Traducir texto usando el motor configurado
     */
    public function translate($text, $source_lang, $target_lang, $options = []) {
        // Validar entrada
        if (empty($text) || !is_string($text)) {
            return $text;
        }
        
        // Limitar tamano del texto para evitar problemas
        $max_length = 15000; // ~4000 tokens aprox
        if (strlen($text) > $max_length) {
            // Dividir texto largo y traducir por partes
            return $this->translate_long_text($text, $source_lang, $target_lang, $options);
        }
        
        // Limpiar cache si esta muy grande (prevenir memory leak)
        if (count($this->translation_cache) > 500) {
            $this->translation_cache = array_slice($this->translation_cache, -100, 100, true);
        }
        
        // Verificar cache
        $cache_key = md5($text . $source_lang . $target_lang);
        if (isset($this->translation_cache[$cache_key])) {
            return $this->translation_cache[$cache_key];
        }
        
        // Obtener configuracion
        $settings = Replanta_Auto_Translate::get_settings();
        $engine = $settings['default_engine'] ?? 'openai';
        
        // Intentar con el motor principal
        $result = null;
        
        if ($engine === 'openai' && !empty($settings['openai_api_key'])) {
            $result = $this->translate_with_openai($text, $source_lang, $target_lang, $options);
        } elseif ($engine === 'google' && !empty($settings['google_api_key'])) {
            $result = $this->translate_with_google($text, $source_lang, $target_lang, $options);
        }
        
        // Fallback si el motor principal falla
        if (is_wp_error($result) || $result === null) {
            if ($engine === 'openai' && !empty($settings['google_api_key'])) {
                $result = $this->translate_with_google($text, $source_lang, $target_lang, $options);
            } elseif ($engine === 'google' && !empty($settings['openai_api_key'])) {
                $result = $this->translate_with_openai($text, $source_lang, $target_lang, $options);
            }
        }
        
        // Si todo falla, retornar error o texto original
        if (is_wp_error($result) || $result === null) {
            return is_wp_error($result) ? $result : new WP_Error('no_translation', 'No se pudo traducir el texto');
        }
        
        // Guardar en cache
        $this->translation_cache[$cache_key] = $result;
        
        return $result;
    }
    
    /**
     * Traducir texto largo dividiendolo en partes
     */
    private function translate_long_text($text, $source_lang, $target_lang, $options = []) {
        $max_chunk_size = 12000;
        $chunks = [];
        
        // Dividir por parrafos primero
        $paragraphs = preg_split('/(\n\s*\n|\r\n\s*\r\n)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        $current_chunk = '';
        foreach ($paragraphs as $para) {
            if (strlen($current_chunk . $para) < $max_chunk_size) {
                $current_chunk .= $para;
            } else {
                if (!empty($current_chunk)) {
                    $chunks[] = $current_chunk;
                }
                $current_chunk = $para;
            }
        }
        if (!empty($current_chunk)) {
            $chunks[] = $current_chunk;
        }
        
        // Traducir cada chunk
        $translated_chunks = [];
        foreach ($chunks as $chunk) {
            $result = $this->translate($chunk, $source_lang, $target_lang, $options);
            if (is_wp_error($result)) {
                return $result; // Propagar error
            }
            $translated_chunks[] = $result;
        }
        
        return implode('', $translated_chunks);
    }
    
    /**
     * Traducir con OpenAI
     */
    private function translate_with_openai($text, $source_lang, $target_lang, $options = []) {
        $settings = Replanta_Auto_Translate::get_settings();
        $api_key = $settings['openai_api_key'];
        $model = $settings['openai_model'] ?? 'gpt-4o-mini';
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'API key de OpenAI no configurada');
        }
        
        $source_name = $this->get_language_name($source_lang);
        $target_name = $this->get_language_name($target_lang);
        
        $is_html = !empty($options['is_html']);
        $context = $options['context'] ?? '';
        
        // Construir prompt
        $system_prompt = "You are a professional translator. Translate the following text from {$source_name} to {$target_name}. ";
        
        if ($is_html) {
            $system_prompt .= "The text contains HTML markup - preserve all HTML tags, attributes, and structure exactly as they are. Only translate the visible text content within the tags. ";
        }
        
        $system_prompt .= "Provide ONLY the translation, without any explanations, notes, or additional text. Maintain the same tone and style as the original.";
        
        if ($context) {
            $system_prompt .= " Context: {$context}";
        }
        
        $body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $system_prompt
                ],
                [
                    'role' => 'user',
                    'content' => $text
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => min(4000, strlen($text) * 2 + 500),
        ];
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
            'timeout' => 30, // Reducido para evitar bloqueos largos
            'sslverify' => true,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($response_code !== 200) {
            $error_message = $response_body['error']['message'] ?? 'Error desconocido de OpenAI';
            return new WP_Error('openai_error', $error_message);
        }
        
        if (!isset($response_body['choices'][0]['message']['content'])) {
            return new WP_Error('invalid_response', 'Respuesta invalida de OpenAI');
        }
        
        $translated = trim($response_body['choices'][0]['message']['content']);
        
        // Limpiar posibles comillas o formatos no deseados
        $translated = preg_replace('/^["\']|["\']$/', '', $translated);
        
        return $translated;
    }
    
    /**
     * Traducir con Google Translate
     */
    private function translate_with_google($text, $source_lang, $target_lang, $options = []) {
        $settings = Replanta_Auto_Translate::get_settings();
        $api_key = $settings['google_api_key'];
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'API key de Google Translate no configurada');
        }
        
        $is_html = !empty($options['is_html']);
        
        // Google Cloud Translation API v2
        $url = 'https://translation.googleapis.com/language/translate/v2';
        
        $body = [
            'key' => $api_key,
            'q' => $text,
            'source' => $source_lang,
            'target' => $target_lang,
            'format' => $is_html ? 'html' : 'text',
        ];
        
        $response = wp_remote_post($url, [
            'body' => $body,
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($response_code !== 200) {
            $error_message = $response_body['error']['message'] ?? 'Error desconocido de Google Translate';
            return new WP_Error('google_error', $error_message);
        }
        
        if (!isset($response_body['data']['translations'][0]['translatedText'])) {
            return new WP_Error('invalid_response', 'Respuesta invalida de Google Translate');
        }
        
        $translated = $response_body['data']['translations'][0]['translatedText'];
        
        // Google devuelve entidades HTML codificadas, decodificar
        $translated = html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $translated;
    }
    
    /**
     * Traducir multiples textos de una vez (batch)
     */
    public function translate_batch($texts, $source_lang, $target_lang, $options = []) {
        $results = [];
        $settings = Replanta_Auto_Translate::get_settings();
        $delay = intval($settings['delay_between_requests'] ?? 1000);
        
        foreach ($texts as $key => $text) {
            $result = $this->translate($text, $source_lang, $target_lang, $options);
            $results[$key] = $result;
            
            // Delay entre requests para evitar rate limits
            if ($delay > 0) {
                usleep($delay * 1000); // Convertir ms a microsegundos
            }
        }
        
        return $results;
    }
    
    /**
     * Traducir slug/URL
     */
    public function translate_slug($slug, $source_lang, $target_lang) {
        // Convertir slug a texto legible
        $text = str_replace(['-', '_'], ' ', $slug);
        
        // Traducir
        $translated = $this->translate($text, $source_lang, $target_lang);
        
        if (is_wp_error($translated)) {
            return $slug; // Mantener original si hay error
        }
        
        // Convertir de vuelta a formato slug
        $translated_slug = sanitize_title($translated);
        
        return $translated_slug;
    }
    
    /**
     * Obtener nombre completo del idioma
     */
    public function get_language_name($code) {
        return $this->language_names[$code] ?? ucfirst($code);
    }
    
    /**
     * Probar conexion con OpenAI
     */
    public function test_openai_connection() {
        $settings = Replanta_Auto_Translate::get_settings();
        
        if (empty($settings['openai_api_key'])) {
            return new WP_Error('no_api_key', 'API key no configurada');
        }
        
        $result = $this->translate_with_openai('Hello', 'en', 'es');
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return [
            'success' => true,
            'message' => 'Conexion con OpenAI exitosa',
            'test_translation' => $result,
        ];
    }
    
    /**
     * Probar conexion con Google Translate
     */
    public function test_google_connection() {
        $settings = Replanta_Auto_Translate::get_settings();
        
        if (empty($settings['google_api_key'])) {
            return new WP_Error('no_api_key', 'API key no configurada');
        }
        
        $result = $this->translate_with_google('Hello', 'en', 'es');
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return [
            'success' => true,
            'message' => 'Conexion con Google Translate exitosa',
            'test_translation' => $result,
        ];
    }
    
    /**
     * Limpiar cache de traducciones
     */
    public function clear_cache() {
        $this->translation_cache = [];
    }
    
    /**
     * Obtener estadisticas de uso
     */
    public function get_cache_stats() {
        return [
            'cached_translations' => count($this->translation_cache),
        ];
    }
    
    /**
     * Traducir contenido HTML preservando estructura (metodo basico)
     */
    public function translate_html($html, $source_lang, $target_lang) {
        if (empty($html)) {
            return $html;
        }
        
        // Si es HTML complejo, usar el parser DOM
        if (preg_match('/<[a-z][\s\S]*>/i', $html)) {
            return $this->translate_html_with_dom($html, $source_lang, $target_lang);
        }
        
        // Marcar shortcodes para preservarlos
        $shortcode_placeholders = [];
        $html = preg_replace_callback('/\[[\w\-]+[^\]]*\](?:[^\[]*\[\/[\w\-]+\])?/', function($match) use (&$shortcode_placeholders) {
            $placeholder = '{{SHORTCODE_' . count($shortcode_placeholders) . '}}';
            $shortcode_placeholders[$placeholder] = $match[0];
            return $placeholder;
        }, $html);
        
        // Traducir con opcion HTML
        $translated = $this->translate($html, $source_lang, $target_lang, ['is_html' => true]);
        
        if (is_wp_error($translated)) {
            return $html;
        }
        
        // Restaurar shortcodes
        foreach ($shortcode_placeholders as $placeholder => $shortcode) {
            $translated = str_replace($placeholder, $shortcode, $translated);
        }
        
        return $translated;
    }
    
    /**
     * Traducir HTML usando DOMDocument (preserva estructura perfectamente)
     * Similar a como lo hace WPML
     */
    public function translate_html_with_dom($html, $source_lang, $target_lang) {
        if (empty($html)) {
            return $html;
        }
        
        // Preservar shortcodes
        $shortcodes = [];
        $html = preg_replace_callback('/\[[\w\-]+[^\]]*\](?:[\s\S]*?\[\/[\w\-]+\])?/', function($match) use (&$shortcodes) {
            $key = '<!--SHORTCODE_' . count($shortcodes) . '-->';
            $shortcodes[$key] = $match[0];
            return $key;
        }, $html);
        
        // Preservar scripts y styles
        $preserved = [];
        $html = preg_replace_callback('/<(script|style)[^>]*>[\s\S]*?<\/\1>/i', function($match) use (&$preserved) {
            $key = '<!--PRESERVED_' . count($preserved) . '-->';
            $preserved[$key] = $match[0];
            return $key;
        }, $html);
        
        // Cargar HTML con DOMDocument
        $dom = new DOMDocument('1.0', 'UTF-8');
        
        // Suprimir errores de HTML malformado
        libxml_use_internal_errors(true);
        
        // Envolver en estructura para preservar encoding
        $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        libxml_clear_errors();
        
        // Extraer y traducir nodos de texto
        $xpath = new DOMXPath($dom);
        $text_nodes = $xpath->query('//text()[normalize-space()]');
        
        // Recopilar textos unicos para traducir en batch
        $texts_to_translate = [];
        $node_map = [];
        
        foreach ($text_nodes as $node) {
            $text = $node->nodeValue;
            
            // Ignorar si es solo espacios o numeros
            $trimmed = trim($text);
            if (empty($trimmed) || is_numeric($trimmed)) {
                continue;
            }
            
            // Ignorar textos muy cortos (probablemente no son traducibles)
            if (mb_strlen($trimmed) < 2) {
                continue;
            }
            
            // Ignorar si parece ser codigo o placeholder
            if (preg_match('/^[\{\[\<].*[\}\]\>]$/', $trimmed)) {
                continue;
            }
            
            // Crear hash para agrupar textos identicos
            $hash = md5($trimmed);
            if (!isset($texts_to_translate[$hash])) {
                $texts_to_translate[$hash] = $trimmed;
            }
            $node_map[] = ['node' => $node, 'hash' => $hash, 'original' => $text];
        }
        
        // Traducir textos unicos
        $translations = [];
        foreach ($texts_to_translate as $hash => $text) {
            $result = $this->translate($text, $source_lang, $target_lang);
            if (!is_wp_error($result)) {
                $translations[$hash] = $result;
            } else {
                $translations[$hash] = $text; // Mantener original si falla
            }
        }
        
        // Aplicar traducciones a los nodos
        foreach ($node_map as $item) {
            $hash = $item['hash'];
            $node = $item['node'];
            $original = $item['original'];
            
            if (isset($translations[$hash])) {
                // Preservar espacios al inicio/final
                $leading_space = '';
                $trailing_space = '';
                
                if (preg_match('/^(\s+)/', $original, $m)) {
                    $leading_space = $m[1];
                }
                if (preg_match('/(\s+)$/', $original, $m)) {
                    $trailing_space = $m[1];
                }
                
                $node->nodeValue = $leading_space . $translations[$hash] . $trailing_space;
            }
        }
        
        // Traducir atributos traducibles (alt, title, placeholder, aria-label)
        $translatable_attrs = ['alt', 'title', 'placeholder', 'aria-label', 'aria-description'];
        foreach ($translatable_attrs as $attr) {
            $nodes_with_attr = $xpath->query('//*[@' . $attr . ']');
            foreach ($nodes_with_attr as $node) {
                $value = $node->getAttribute($attr);
                if (!empty($value) && mb_strlen($value) > 1) {
                    $result = $this->translate($value, $source_lang, $target_lang);
                    if (!is_wp_error($result)) {
                        $node->setAttribute($attr, $result);
                    }
                }
            }
        }
        
        // Extraer el body traducido
        $body = $dom->getElementsByTagName('body')->item(0);
        $result = '';
        foreach ($body->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }
        
        // Restaurar preserved content
        foreach ($preserved as $key => $content) {
            $result = str_replace($key, $content, $result);
        }
        
        // Restaurar shortcodes
        foreach ($shortcodes as $key => $content) {
            $result = str_replace($key, $content, $result);
        }
        
        return $result;
    }
    
    /**
     * Detectar idioma de un texto (solo con Google)
     */
    public function detect_language($text) {
        $settings = Replanta_Auto_Translate::get_settings();
        $api_key = $settings['google_api_key'];
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'Se requiere Google API key para detectar idioma');
        }
        
        $url = 'https://translation.googleapis.com/language/translate/v2/detect';
        
        $response = wp_remote_post($url, [
            'body' => [
                'key' => $api_key,
                'q' => substr($text, 0, 500), // Limitar texto para deteccion
            ],
            'timeout' => 15,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($response_body['data']['detections'][0][0]['language'])) {
            return $response_body['data']['detections'][0][0]['language'];
        }
        
        return new WP_Error('detection_failed', 'No se pudo detectar el idioma');
    }
}
