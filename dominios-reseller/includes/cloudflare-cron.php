<?php
/**
 * Cloudflare Cron Handler
 * 
 * Gestiona la sincronización automática de zonas Cloudflare mediante WP Cron
 * 
 * @package Dominios_Reseller
 * @version 1.0.0
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dominios_Reseller_Cloudflare_Cron {

    /**
     * Hook name para el cron
     */
    const CRON_HOOK = 'dominios_reseller_cf_sync_cron';
    
    /**
     * Intervalo de cron personalizado (8 horas)
     */
    const CRON_INTERVAL = 'dr_cf_8hours';

    /**
     * Instancia singleton
     */
    private static ?Dominios_Reseller_Cloudflare_Cron $instance = null;

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Obtener instancia singleton
     */
    public static function get_instance(): Dominios_Reseller_Cloudflare_Cron {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks(): void {
        // Registrar intervalo personalizado
        add_filter('cron_schedules', [$this, 'add_cron_interval']);
        
        // Registrar acción del cron
        add_action(self::CRON_HOOK, [$this, 'run_sync']);
        
        // Programar cron en activación del plugin si no existe
        add_action('admin_init', [$this, 'maybe_schedule_cron']);
    }

    /**
     * Añadir intervalo de cron personalizado (8 horas)
     */
    public function add_cron_interval(array $schedules): array {
        $schedules[self::CRON_INTERVAL] = [
            'interval' => 8 * HOUR_IN_SECONDS,
            'display'  => __('Cada 8 horas', 'dominios-reseller')
        ];
        return $schedules;
    }

    /**
     * Programar cron si no está ya programado y hay token configurado
     */
    public function maybe_schedule_cron(): void {
        if (!class_exists('Dominios_Reseller_Cloudflare_Service')) {
            return;
        }

        $cf_service = Dominios_Reseller_Cloudflare_Service::get_instance();
        $has_token = !empty($cf_service->get_token());
        $is_scheduled = wp_next_scheduled(self::CRON_HOOK);

        if ($has_token && !$is_scheduled) {
            // Programar primera ejecución en 1 hora
            wp_schedule_event(time() + HOUR_IN_SECONDS, self::CRON_INTERVAL, self::CRON_HOOK);
            error_log('[Dominios Reseller CF] Cron programado cada 8 horas');
        } elseif (!$has_token && $is_scheduled) {
            // Desprogramar si no hay token
            wp_clear_scheduled_hook(self::CRON_HOOK);
            error_log('[Dominios Reseller CF] Cron desprogramado (sin token)');
        }
    }

    /**
     * Ejecutar sincronización desde cron
     */
    public function run_sync(): void {
        if (!class_exists('Dominios_Reseller_Cloudflare_Service')) {
            error_log('[Dominios Reseller CF] Cron: Servicio CF no disponible');
            return;
        }

        $cf_service = Dominios_Reseller_Cloudflare_Service::get_instance();
        
        if (empty($cf_service->get_token())) {
            error_log('[Dominios Reseller CF] Cron: Sin token configurado');
            return;
        }

        error_log('[Dominios Reseller CF] Cron: Iniciando sincronización automática');
        
        $result = $cf_service->sync_zones();
        
        if ($result['success']) {
            error_log('[Dominios Reseller CF] Cron: Sync exitoso - ' . $result['zones_total'] . ' zonas');
        } else {
            error_log('[Dominios Reseller CF] Cron: Error - ' . ($result['error'] ?? 'desconocido'));
        }
    }

    /**
     * Desprogramar cron (llamar en desactivación del plugin)
     */
    public static function unschedule(): void {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * Obtener próxima ejecución programada
     */
    public static function get_next_run(): ?string {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if (!$timestamp) {
            return null;
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Verificar si el cron está programado
     */
    public static function is_scheduled(): bool {
        return (bool) wp_next_scheduled(self::CRON_HOOK);
    }
}

// Inicializar cron
add_action('plugins_loaded', function() {
    Dominios_Reseller_Cloudflare_Cron::get_instance();
});

// Limpiar cron en desactivación
register_deactivation_hook(dirname(__FILE__, 2) . '/dominios-reseller.php', function() {
    Dominios_Reseller_Cloudflare_Cron::unschedule();
});
