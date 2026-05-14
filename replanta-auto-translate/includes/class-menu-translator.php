<?php
/**
 * Clase para traducir menus de navegacion
 */

if (!defined('ABSPATH')) {
    exit;
}

class Replanta_Auto_Translate_Menu_Translator {
    
    private static $instance = null;
    
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
     * Obtener todos los menus del idioma origen
     */
    public function get_source_menus($source_lang) {
        $menus = wp_get_nav_menus();
        $source_menus = [];
        
        foreach ($menus as $menu) {
            // Verificar idioma del menu si Polylang esta activo
            if (function_exists('pll_get_term_language')) {
                $menu_lang = pll_get_term_language($menu->term_id);
                
                if ($menu_lang === $source_lang) {
                    $source_menus[] = $menu;
                }
            } else {
                // Sin Polylang, incluir todos
                $source_menus[] = $menu;
            }
        }
        
        return $source_menus;
    }
    
    /**
     * Obtener menus sin traduccion en el idioma destino
     */
    public function get_untranslated_menus($source_lang, $target_lang) {
        $source_menus = $this->get_source_menus($source_lang);
        $untranslated = [];
        
        foreach ($source_menus as $menu) {
            if (!$this->has_translation($menu->term_id, $target_lang)) {
                $untranslated[] = $menu;
            }
        }
        
        return $untranslated;
    }
    
    /**
     * Verificar si un menu tiene traduccion
     */
    public function has_translation($menu_id, $target_lang) {
        if (function_exists('pll_get_term')) {
            $translated_id = pll_get_term($menu_id, $target_lang);
            return !empty($translated_id);
        }
        return false;
    }
    
    /**
     * Traducir un menu completo
     */
    public function translate_menu($menu_id, $source_lang, $target_lang) {
        $menu = wp_get_nav_menu_object($menu_id);
        
        if (!$menu) {
            return new WP_Error('menu_not_found', 'Menu no encontrado');
        }
        
        // Verificar si ya tiene traduccion
        if ($this->has_translation($menu_id, $target_lang)) {
            return new WP_Error('translation_exists', 'El menu ya tiene traduccion');
        }
        
        $translator = Replanta_Auto_Translate_Translator::instance();
        
        // Traducir nombre del menu
        $translated_name = $translator->translate($menu->name, $source_lang, $target_lang);
        if (is_wp_error($translated_name)) {
            $translated_name = $menu->name . ' (' . strtoupper($target_lang) . ')';
        }
        
        // Crear nuevo menu
        $new_menu_id = wp_create_nav_menu($translated_name);
        
        if (is_wp_error($new_menu_id)) {
            return $new_menu_id;
        }
        
        // Asignar idioma al nuevo menu
        if (function_exists('pll_set_term_language')) {
            pll_set_term_language($new_menu_id, $target_lang);
        }
        
        // Vincular como traduccion
        if (function_exists('pll_save_term_translations')) {
            $translations = [];
            
            // Obtener traducciones existentes
            if (function_exists('pll_get_term_translations')) {
                $translations = pll_get_term_translations($menu_id);
            }
            
            $translations[$source_lang] = $menu_id;
            $translations[$target_lang] = $new_menu_id;
            
            pll_save_term_translations($translations);
        }
        
        // Obtener items del menu original
        $menu_items = wp_get_nav_menu_items($menu_id);
        
        if (empty($menu_items)) {
            return [
                'menu_id' => $new_menu_id,
                'menu_name' => $translated_name,
                'items_translated' => 0,
            ];
        }
        
        // Mapeo de IDs originales a nuevos para mantener jerarquia
        $item_id_map = [];
        
        // Traducir cada item del menu
        foreach ($menu_items as $item) {
            $new_item_id = $this->translate_menu_item($item, $new_menu_id, $source_lang, $target_lang, $item_id_map);
            
            if (!is_wp_error($new_item_id)) {
                $item_id_map[$item->ID] = $new_item_id;
            }
        }
        
        // Actualizar padres con los nuevos IDs
        $this->update_menu_item_parents($new_menu_id, $item_id_map);
        
        return [
            'menu_id' => $new_menu_id,
            'menu_name' => $translated_name,
            'items_translated' => count($item_id_map),
        ];
    }
    
    /**
     * Traducir un item de menu individual
     */
    private function translate_menu_item($item, $new_menu_id, $source_lang, $target_lang, $item_id_map) {
        $translator = Replanta_Auto_Translate_Translator::instance();
        $polylang = Replanta_Auto_Translate_Polylang_Bridge::instance();
        
        // Traducir titulo del item
        $translated_title = $translator->translate($item->title, $source_lang, $target_lang);
        if (is_wp_error($translated_title)) {
            $translated_title = $item->title;
        }
        
        // Determinar el objeto enlazado traducido
        $object_id = 0;
        $object = '';
        $type = $item->type;
        $url = '';
        
        switch ($item->type) {
            case 'post_type':
                // Enlace a pagina/post - buscar traduccion
                $translated_post_id = $polylang->get_post_translation($item->object_id, $target_lang);
                
                if ($translated_post_id) {
                    $object_id = $translated_post_id;
                    $object = $item->object;
                } else {
                    // No hay traduccion, mantener enlace al original
                    $object_id = $item->object_id;
                    $object = $item->object;
                }
                break;
                
            case 'taxonomy':
                // Enlace a categoria/termino - buscar traduccion
                if (function_exists('pll_get_term')) {
                    $translated_term_id = pll_get_term($item->object_id, $target_lang);
                    
                    if ($translated_term_id) {
                        $object_id = $translated_term_id;
                    } else {
                        $object_id = $item->object_id;
                    }
                } else {
                    $object_id = $item->object_id;
                }
                $object = $item->object;
                break;
                
            case 'custom':
                // Enlace personalizado - mantener URL
                $type = 'custom';
                $url = $item->url;
                break;
        }
        
        // Preparar argumentos para el nuevo item
        $menu_item_data = [
            'menu-item-title' => $translated_title,
            'menu-item-status' => 'publish',
            'menu-item-type' => $type,
            'menu-item-position' => $item->menu_order,
        ];
        
        if ($type === 'custom') {
            $menu_item_data['menu-item-url'] = $url;
        } else {
            $menu_item_data['menu-item-object-id'] = $object_id;
            $menu_item_data['menu-item-object'] = $object;
        }
        
        // Mantener padre (se actualizara despues)
        if ($item->menu_item_parent > 0) {
            // Temporalmente usar el ID original, se actualizara despues
            $menu_item_data['menu-item-parent-id'] = 0; // Se actualiza despues
        }
        
        // Traducir atributo title si existe
        if (!empty($item->attr_title)) {
            $translated_attr = $translator->translate($item->attr_title, $source_lang, $target_lang);
            $menu_item_data['menu-item-attr-title'] = is_wp_error($translated_attr) ? $item->attr_title : $translated_attr;
        }
        
        // Traducir descripcion si existe
        if (!empty($item->description)) {
            $translated_desc = $translator->translate($item->description, $source_lang, $target_lang);
            $menu_item_data['menu-item-description'] = is_wp_error($translated_desc) ? $item->description : $translated_desc;
        }
        
        // Copiar clases CSS
        if (!empty($item->classes)) {
            $menu_item_data['menu-item-classes'] = implode(' ', array_filter($item->classes));
        }
        
        // Copiar target
        if (!empty($item->target)) {
            $menu_item_data['menu-item-target'] = $item->target;
        }
        
        // Copiar xfn
        if (!empty($item->xfn)) {
            $menu_item_data['menu-item-xfn'] = $item->xfn;
        }
        
        // Agregar item al menu
        $new_item_id = wp_update_nav_menu_item($new_menu_id, 0, $menu_item_data);
        
        return $new_item_id;
    }
    
    /**
     * Actualizar padres de items de menu con los nuevos IDs
     */
    private function update_menu_item_parents($menu_id, $item_id_map) {
        $menu_items = wp_get_nav_menu_items($menu_id);
        
        if (empty($menu_items)) {
            return;
        }
        
        // Revertir el mapeo para buscar por ID original
        $reverse_map = array_flip($item_id_map);
        
        foreach ($menu_items as $item) {
            // Buscar el item original correspondiente
            $original_id = isset($reverse_map[$item->ID]) ? $reverse_map[$item->ID] : null;
            
            if (!$original_id) {
                continue;
            }
            
            // Obtener item original para ver su padre
            $original_item = wp_setup_nav_menu_item(get_post($original_id));
            
            if ($original_item && $original_item->menu_item_parent > 0) {
                // Buscar el nuevo ID del padre
                if (isset($item_id_map[$original_item->menu_item_parent])) {
                    $new_parent_id = $item_id_map[$original_item->menu_item_parent];
                    
                    // Actualizar el padre
                    update_post_meta($item->ID, '_menu_item_menu_item_parent', $new_parent_id);
                }
            }
        }
    }
    
    /**
     * Traducir todos los menus sin traduccion
     */
    public function translate_all_menus($source_lang, $target_lang) {
        $untranslated = $this->get_untranslated_menus($source_lang, $target_lang);
        
        $results = [
            'success' => [],
            'errors' => [],
        ];
        
        foreach ($untranslated as $menu) {
            $result = $this->translate_menu($menu->term_id, $source_lang, $target_lang);
            
            if (is_wp_error($result)) {
                $results['errors'][] = [
                    'menu_id' => $menu->term_id,
                    'menu_name' => $menu->name,
                    'error' => $result->get_error_message(),
                ];
            } else {
                $results['success'][] = $result;
            }
        }
        
        return $results;
    }
    
    /**
     * Asignar menu traducido a una ubicacion
     */
    public function assign_menu_to_location($menu_id, $location, $target_lang) {
        // En Polylang, las ubicaciones de menu son por idioma
        // El formato suele ser: primary___en, primary___es, etc.
        
        $locations = get_nav_menu_locations();
        $polylang_location = $location . '___' . $target_lang;
        
        // Verificar si existe la ubicacion con sufijo de idioma
        $registered_locations = get_registered_nav_menus();
        
        if (isset($registered_locations[$polylang_location])) {
            $locations[$polylang_location] = $menu_id;
        } else {
            // Polylang puede manejar ubicaciones de forma diferente
            // Intentar asignar a la ubicacion base
            $locations[$location] = $menu_id;
        }
        
        set_theme_mod('nav_menu_locations', $locations);
        
        return true;
    }
    
    /**
     * Copiar asignaciones de ubicacion del menu original
     */
    public function copy_menu_locations($source_menu_id, $target_menu_id, $source_lang, $target_lang) {
        $locations = get_nav_menu_locations();
        
        foreach ($locations as $location => $menu_id) {
            if ($menu_id !== $source_menu_id) {
                continue;
            }
            
            // Encontrar la ubicacion equivalente para el idioma destino
            // Quitar sufijo de idioma si existe
            $base_location = preg_replace('/___[a-z]{2}$/', '', $location);
            $target_location = $base_location . '___' . $target_lang;
            
            $locations[$target_location] = $target_menu_id;
        }
        
        set_theme_mod('nav_menu_locations', $locations);
    }
    
    /**
     * Obtener estadisticas de menus
     */
    public function get_menu_stats($source_lang, $target_lang) {
        $source_menus = $this->get_source_menus($source_lang);
        $untranslated = $this->get_untranslated_menus($source_lang, $target_lang);
        
        $total_items = 0;
        foreach ($source_menus as $menu) {
            $items = wp_get_nav_menu_items($menu->term_id);
            $total_items += count($items);
        }
        
        return [
            'total_menus' => count($source_menus),
            'untranslated_menus' => count($untranslated),
            'total_items' => $total_items,
        ];
    }
    
    /**
     * Obtener items de un menu para preview
     */
    public function get_menu_items_preview($menu_id) {
        $items = wp_get_nav_menu_items($menu_id);
        
        if (empty($items)) {
            return [];
        }
        
        $preview = [];
        foreach ($items as $item) {
            $preview[] = [
                'id' => $item->ID,
                'title' => $item->title,
                'type' => $item->type,
                'parent' => $item->menu_item_parent,
                'order' => $item->menu_order,
            ];
        }
        
        return $preview;
    }
    
    /**
     * Poblar un menu existente vacio copiando items del menu hermano en otro idioma
     * Útil cuando los menus ya existen pero están vacíos
     */
    public function populate_empty_menu($target_menu_id, $source_lang, $target_lang) {
        $target_menu = wp_get_nav_menu_object($target_menu_id);
        
        if (!$target_menu) {
            return new WP_Error('menu_not_found', 'Menu destino no encontrado');
        }
        
        // Verificar que está vacío
        $existing_items = wp_get_nav_menu_items($target_menu_id);
        if (!empty($existing_items)) {
            return new WP_Error('menu_not_empty', 'El menú ya tiene items. Usa sync_menu_from_source() para actualizar.');
        }
        
        // Buscar el menu hermano en el idioma origen
        $source_menu_id = null;
        
        if (function_exists('pll_get_term')) {
            $source_menu_id = pll_get_term($target_menu_id, $source_lang);
        }
        
        if (!$source_menu_id) {
            return new WP_Error('source_not_found', 'No se encontró el menú en idioma origen vinculado');
        }
        
        // Obtener items del menu origen
        $source_items = wp_get_nav_menu_items($source_menu_id);
        
        if (empty($source_items)) {
            return new WP_Error('source_empty', 'El menú origen está vacío');
        }
        
        $translator = Replanta_Auto_Translate_Translator::instance();
        $polylang = Replanta_Auto_Translate_Polylang_Bridge::instance();
        
        // Mapeo de IDs originales a nuevos para mantener jerarquía
        $item_id_map = [];
        
        // Copiar cada item traduciendo
        foreach ($source_items as $item) {
            $new_item_id = $this->translate_menu_item($item, $target_menu_id, $source_lang, $target_lang, $item_id_map);
            
            if (!is_wp_error($new_item_id)) {
                $item_id_map[$item->ID] = $new_item_id;
            }
        }
        
        // Actualizar padres con los nuevos IDs
        $this->update_menu_item_parents($target_menu_id, $item_id_map);
        
        return [
            'menu_id' => $target_menu_id,
            'menu_name' => $target_menu->name,
            'items_translated' => count($item_id_map),
            'source_menu_id' => $source_menu_id,
        ];
    }
    
    /**
     * Obtener menús del idioma destino que están vacíos pero vinculados
     */
    public function get_empty_target_menus($source_lang, $target_lang) {
        $menus = wp_get_nav_menus();
        $empty_menus = [];
        
        foreach ($menus as $menu) {
            if (!function_exists('pll_get_term_language')) {
                continue;
            }
            
            $menu_lang = pll_get_term_language($menu->term_id);
            
            // Solo menús en idioma destino
            if ($menu_lang !== $target_lang) {
                continue;
            }
            
            // Verificar si está vacío
            $items = wp_get_nav_menu_items($menu->term_id);
            if (!empty($items)) {
                continue;
            }
            
            // Verificar si tiene hermano en idioma origen
            $source_menu_id = pll_get_term($menu->term_id, $source_lang);
            if (!$source_menu_id) {
                continue;
            }
            
            // Verificar que el hermano tiene items
            $source_items = wp_get_nav_menu_items($source_menu_id);
            if (empty($source_items)) {
                continue;
            }
            
            $source_menu = wp_get_nav_menu_object($source_menu_id);
            
            $empty_menus[] = [
                'id' => $menu->term_id,
                'name' => $menu->name,
                'source_id' => $source_menu_id,
                'source_name' => $source_menu ? $source_menu->name : '',
                'source_items_count' => count($source_items),
            ];
        }
        
        return $empty_menus;
    }
    
    /**
     * Poblar todos los menús vacíos del idioma destino
     */
    public function populate_all_empty_menus($source_lang, $target_lang) {
        $empty_menus = $this->get_empty_target_menus($source_lang, $target_lang);
        
        $results = [
            'success' => [],
            'errors' => [],
        ];
        
        foreach ($empty_menus as $menu_info) {
            $result = $this->populate_empty_menu($menu_info['id'], $source_lang, $target_lang);
            
            if (is_wp_error($result)) {
                $results['errors'][] = [
                    'menu_id' => $menu_info['id'],
                    'menu_name' => $menu_info['name'],
                    'error' => $result->get_error_message(),
                ];
            } else {
                $results['success'][] = $result;
            }
        }
        
        return $results;
    }
}
