<?php
/**
 * OpenAI Handler - Gestiona llamadas a la API de OpenAI
 *
 * @package Replanta_Meta_Fill
 */

if (!defined('ABSPATH')) {
    exit;
}

class Replanta_Meta_Fill_OpenAI_Handler {
    
    private static $instance = null;
    private $api_key;
    private $model;
    private $max_length;
    private $temperature;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_options();
    }
    
    /**
     * Cargar opciones del plugin
     */
    private function load_options() {
        $options = get_option('replanta_meta_fill_options', []);
        
        $this->api_key = isset($options['openai_api_key']) ? $options['openai_api_key'] : '';
        $this->model = isset($options['openai_model']) ? $options['openai_model'] : 'gpt-4o-mini';
        $this->max_length = isset($options['max_length']) ? (int) $options['max_length'] : 155;
        $this->temperature = isset($options['temperature']) ? (float) $options['temperature'] : 0.7;
    }
    
    /**
     * Verificar si OpenAI está configurado
     *
     * @return bool
     */
    public function is_configured() {
        return !empty($this->api_key) && strpos($this->api_key, 'sk-') === 0;
    }
    
    /**
     * Generar meta descripción para un post
     *
     * @param int $post_id ID del post
     * @return array Array con 'success', 'meta_description' o 'error'
     */
    public function generate_meta_description($post_id) {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'error' => 'OpenAI API key no configurada. Ve a Ajustes > Replanta Meta Fill.'
            ];
        }
        
        // Obtener contenido del post
        $crawler = Replanta_Meta_Fill_Content_Crawler::instance();
        $post_data = $crawler->get_post_content($post_id);
        
        if (!$post_data) {
            return [
                'success' => false,
                'error' => 'No se pudo obtener el contenido del post.'
            ];
        }
        
        // Verificar si ya tiene meta descripción
        $existing_meta = $crawler->get_existing_meta_description($post_id);
        
        // Preparar contexto
        $context = $crawler->prepare_context_for_ai($post_data, 500);
        
        // Detectar si es producto
        $post = get_post($post_id);
        $is_product = $post->post_type === 'product' && class_exists('WooCommerce');
        
        // Obtener prompt template
        $options = get_option('replanta_meta_fill_options', []);
        $prompt_template = isset($options['prompt_template']) ? $options['prompt_template'] : $this->get_default_prompt();
        
        // Preparar variables adicionales para productos
        $replacements = ['{max_length}', '{title}', '{content}'];
        $values = [$this->max_length, $post_data['title'], $context];
        
        // Añadir contexto de producto si aplica
        if ($is_product) {
            $sku = isset($post_data['sku']) ? $post_data['sku'] : 'N/A';
            $price = isset($post_data['price']) ? $post_data['price'] : 'Precio no definido';
            $categories = !empty($post_data['categories']) ? implode(', ', $post_data['categories']) : 'Sin categoría';
            $short_desc = isset($post_data['short_description']) ? $post_data['short_description'] : '';
            
            $replacements = array_merge($replacements, ['{sku}', '{price}', '{categories}', '{short_description}', '{product_context_start}', '{product_context_end}', '{post_context_start}', '{post_context_end}', '{short_description_start}', '{short_description_end}']);
            $short_desc_block = !empty($short_desc) ? $short_desc : '';
            $values = array_merge($values, [
                $sku, 
                $price, 
                $categories, 
                $short_desc_block,
                '', // product_context_start (sin salto de línea)
                '', // product_context_end (sin salto de línea)
                '', // post_context_start (ocultar)
                '', // post_context_end (ocultar)
                !empty($short_desc) ? '' : '', // short_description_start
                !empty($short_desc) ? '' : ''  // short_description_end
            ]);
        } else {
            // Para posts/páginas: ocultar contexto de producto
            $replacements = array_merge($replacements, ['{product_context_start}', '{product_context_end}', '{post_context_start}', '{post_context_end}', '{sku}', '{price}', '{categories}', '{short_description}', '{short_description_start}', '{short_description_end}']);
            $values = array_merge($values, ['', '', '', '', '', '', '', '', '', '']);
        }
        
        // Reemplazar variables en el prompt
        $prompt = str_replace($replacements, $values, $prompt_template);
        
        Replanta_Meta_Fill::log("Generando meta para post $post_id: " . $post_data['title'], 'info');
        
        // Llamar a OpenAI
        $result = $this->call_openai_api($prompt);
        
        if (!$result['success']) {
            return $result;
        }
        
        // Guardar la meta descripción
        $meta_description = $result['meta_description'];
        $crawler->save_meta_description($post_id, $meta_description);
        
        Replanta_Meta_Fill::log("Meta generada exitosamente para post $post_id", 'success');
        
        return [
            'success' => true,
            'meta_description' => $meta_description,
            'previous_meta' => $existing_meta,
        ];
    }
    
    /**
     * Llamar a la API de OpenAI
     *
     * @param string $prompt Prompt para OpenAI
     * @return array Array con 'success', 'meta_description' o 'error'
     */
    private function call_openai_api($prompt) {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $body = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Eres un experto en SEO y marketing de contenidos. Tu tarea es crear meta descripciones atractivas, persuasivas y optimizadas para motores de búsqueda.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => $this->temperature,
            'max_tokens' => 200, // Suficiente para meta description
        ];
        
        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body' => json_encode($body),
        ]);
        
        // Verificar errores de conexión
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            Replanta_Meta_Fill::log("Error de conexión con OpenAI: $error_message", 'error');
            
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . $error_message
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        // Verificar código de respuesta
        if ($response_code !== 200) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Error desconocido';
            Replanta_Meta_Fill::log("Error de OpenAI (código $response_code): $error_message", 'error');
            
            return [
                'success' => false,
                'error' => "Error API ($response_code): $error_message"
            ];
        }
        
        // Extraer meta descripción
        if (!isset($data['choices'][0]['message']['content'])) {
            Replanta_Meta_Fill::log("Respuesta de OpenAI inválida: " . $response_body, 'error');
            
            return [
                'success' => false,
                'error' => 'Respuesta de OpenAI inválida'
            ];
        }
        
        $meta_description = trim($data['choices'][0]['message']['content']);
        
        // Remover comillas si las tiene
        $meta_description = trim($meta_description, '"\'');
        
        // Verificar longitud
        if (strlen($meta_description) > $this->max_length + 10) {
            $meta_description = substr($meta_description, 0, $this->max_length);
            
            // Cortar en última palabra completa
            $last_space = strrpos($meta_description, ' ');
            if ($last_space !== false) {
                $meta_description = substr($meta_description, 0, $last_space) . '...';
            }
        }
        
        return [
            'success' => true,
            'meta_description' => $meta_description,
        ];
    }
    
    /**
     * Obtener prompt por defecto
     *
     * @return string
     */
    private function get_default_prompt() {
        return 'Genera una meta descripción SEO atractiva y concisa (máximo {max_length} caracteres) que sea persuasiva e incluya palabras clave relevantes para motivar al clic.

{product_context_start}
ES UN PRODUCTO DE ECOMMERCE:
- Nombre: {title}
- SKU: {sku}
- Precio: {price}
- Categorías: {categories}
{short_description_start}Descripción: {short_description}
{short_description_end}

La meta descripción debe destacar el producto, sus beneficios o usos principales, e idealmente incluir el precio o una llamada a la acción para comprar.
{product_context_end}

{post_context_start}
CONTENIDO:
Título: {title}
Contenido: {content}
{post_context_end}

Meta descripción (máximo {max_length} caracteres):';
    }
    
    /**
     * Validar API key
     *
     * @param string $api_key API key a validar
     * @return array Array con 'valid' (bool) y 'message' (string)
     */
    public function validate_api_key($api_key) {
        if (empty($api_key)) {
            return [
                'valid' => false,
                'message' => 'API key vacía'
            ];
        }
        
        if (strpos($api_key, 'sk-') !== 0) {
            return [
                'valid' => false,
                'message' => 'API key inválida (debe comenzar con sk-)'
            ];
        }
        
        // Test simple con OpenAI
        $url = 'https://api.openai.com/v1/models';
        
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
            ],
        ]);
        
        if (is_wp_error($response)) {
            return [
                'valid' => false,
                'message' => 'Error de conexión: ' . $response->get_error_message()
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            return [
                'valid' => true,
                'message' => 'API key válida'
            ];
        }
        
        return [
            'valid' => false,
            'message' => 'API key rechazada por OpenAI (código: ' . $response_code . ')'
        ];
    }
}
