<?php
/**
 * Onboarding Job Worker
 * 
 * Procesa trabajos de onboarding de forma asíncrona usando WP-Cron con locking
 * 
 * @package Dominios_Reseller
 * @version 1.0.0
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Dominios_Reseller_Onboarding_Worker {

    /**
     * Hook del cron para procesar cola
     */
    const CRON_HOOK = 'dominios_reseller_process_onboarding_queue';

    /**
     * Opción para lock de procesamiento
     */
    const LOCK_OPTION = 'dr_onboarding_worker_lock';

    /**
     * Máximo tiempo de lock (segundos)
     */
    const LOCK_TIMEOUT = 300; // 5 minutos

    /**
     * Estados del onboarding
     */
    const STATE_QUEUED = 'queued';
    const STATE_ZONE_CHECK = 'zone_check';
    const STATE_ZONE_WAIT_NS = 'zone_wait_ns';
    const STATE_PRESET_APPLY = 'preset_apply';
    const STATE_NS_UPDATE = 'ns_update';
    const STATE_COMPLETED = 'completed';
    const STATE_FAILED = 'failed';

    /**
     * Hooks de acciones para cada paso
     */
    const ACTION_ZONE_CHECK = 'dr_onboarding_zone_check';
    const ACTION_ZONE_WAIT_NS = 'dr_onboarding_zone_wait_ns';
    const ACTION_PRESET_APPLY = 'dr_onboarding_preset_apply';
    const ACTION_NS_UPDATE = 'dr_onboarding_ns_update';

    /**
     * Timeouts para cada paso (segundos)
     */
    const TIMEOUT_ZONE_CHECK = 30;     // 30s para crear zona
    const TIMEOUT_ZONE_WAIT_NS = 120;  // 2min esperando NS
    const TIMEOUT_PRESET_APPLY = 60;   // 1min para aplicar preset
    const TIMEOUT_NS_UPDATE = 30;      // 30s para actualizar NS

    /**
     * Máximo de reintentos por acción
     */
    const MAX_RETRIES = 3;

    /**
     * Intervalo de polling para NS (segundos)
     */
    const NS_POLL_INTERVAL = 10;

    /**
     * Instancia singleton
     */
    private static ?Dominios_Reseller_Onboarding_Worker $instance = null;

    /**
     * Servicio Cloudflare
     */
    private Dominios_Reseller_Cloudflare_Service $cf_service;

    /**
     * Constructor
     */
    private function __construct() {
        $this->cf_service = Dominios_Reseller_Cloudflare_Service::get_instance();
        $this->init_hooks();
    }

    /**
     * Obtener instancia singleton
     */
    public static function get_instance(): Dominios_Reseller_Onboarding_Worker {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks(): void {
        // Hook del sistema legacy (cron)
        add_action(self::CRON_HOOK, [$this, 'process_queue']);
        
        // Hooks del nuevo sistema async
        add_action(self::ACTION_ZONE_CHECK, [$this, 'action_zone_check'], 10, 2);
        add_action(self::ACTION_ZONE_WAIT_NS, [$this, 'action_zone_wait_ns'], 10, 2);
        add_action(self::ACTION_PRESET_APPLY, [$this, 'action_preset_apply'], 10, 2);
        add_action(self::ACTION_NS_UPDATE, [$this, 'action_ns_update'], 10, 2);
        
        // Programar cron si no existe
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'every_minute', self::CRON_HOOK);
        }

        // Registrar intervalo personalizado
        add_filter('cron_schedules', [$this, 'add_cron_interval']);
    }

    /**
     * Añadir intervalo de cron personalizado
     */
    public function add_cron_interval(array $schedules): array {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display'  => __('Cada minuto', 'dominios-reseller')
        ];
        return $schedules;
    }

    /**
     * Encolar un dominio para onboarding
     * 
     * @param string $primary_domain Dominio a procesar
     * @param string $preset_key Key del preset a aplicar
     * @param bool $auto_update_ns Actualizar NS en Openprovider automáticamente
     * @return array Resultado del encolado
     */
    public function enqueue(string $primary_domain, string $preset_key, bool $auto_update_ns = false): array {
        $primary_domain = strtolower(trim($primary_domain));
        
        // Validar preset
        $preset = Dominios_Reseller_Onboarding_DB::get_preset($preset_key);
        if (!$preset) {
            return [
                'success' => false,
                'error'   => "Preset '$preset_key' no encontrado"
            ];
        }

        // Verificar estado actual
        $current_state = Dominios_Reseller_Onboarding_DB::get_onboarding_state($primary_domain);

        // Lógica mejorada de estados
        if ($current_state) {
            $state = $current_state['state'];

            // Estados que permiten re-encolar
            if (in_array($state, ['error', 'failed', 'needs_manual_ns', 'partial'])) {
                // Permitir re-encolar si falló o está incompleto
                Dominios_Reseller_Onboarding_DB::log(
                    null,
                    $primary_domain,
                    'enqueue',
                    'info',
                    "Re-encolando dominio con estado '$state' - preset anterior: {$current_state['preset_key']}"
                );
            }
            // Estados activos - no permitir
            elseif (in_array($state, [self::STATE_ZONE_CHECK, self::STATE_ZONE_WAIT_NS, self::STATE_PRESET_APPLY, self::STATE_NS_UPDATE])) {
                return [
                    'success' => false,
                    'error'   => "El dominio está siendo procesado actualmente (estado: $state)",
                    'current_state' => $state,
                    'can_retry' => false
                ];
            }
            // Estados completados exitosamente - permitir actualizar configuración
            elseif (in_array($state, ['onboarded', 'completed'])) {
                // Si el preset es diferente, permitir actualización
                if ($current_state['preset_key'] !== $preset_key) {
                    return [
                        'success' => false,
                        'error'   => "El dominio ya está configurado con preset '{$current_state['preset_key']}'. ¿Desea cambiar a preset '$preset_key'?",
                        'current_state' => $state,
                        'can_update' => true,
                        'current_preset' => $current_state['preset_key'],
                        'new_preset' => $preset_key
                    ];
                } else {
                    return [
                        'success' => false,
                        'error'   => "El dominio ya está completamente configurado con el mismo preset. No se requieren cambios.",
                        'current_state' => $state,
                        'can_update' => false,
                        'current_preset' => $current_state['preset_key']
                    ];
                }
            }
        }

        // Generar run_id
        $run_id = Dominios_Reseller_Onboarding_DB::generate_run_id();

        // Inicializar estado en BD con nueva estructura
        Dominios_Reseller_Onboarding_DB::init_onboarding_run($run_id, $primary_domain, [
            'preset_key' => $preset_key,
            'auto_update_ns' => $auto_update_ns,
            'state' => self::STATE_QUEUED,
            'started_at' => current_time('mysql'),
            'retries' => 0
        ]);

        // Programar primera acción: verificar/crear zona
        wp_schedule_single_event(time() + 5, self::ACTION_ZONE_CHECK, [$run_id, $primary_domain]);

        // Log inicio
        Dominios_Reseller_Onboarding_DB::log(
            $run_id,
            $primary_domain,
            'enqueue',
            'info',
            "Dominio encolado para onboarding con preset '$preset_key'"
        );

        return [
            'success' => true,
            'run_id'  => $run_id,
            'message' => 'Dominio encolado correctamente'
        ];
    }

    /**
     * Procesar cola de onboarding
     */
    public function process_queue(): void {
        // Intentar obtener lock
        if (!$this->acquire_lock()) {
            error_log('[DR Onboarding] No se pudo obtener lock, otro proceso está corriendo');
            return;
        }

        try {
            $pending = Dominios_Reseller_Onboarding_DB::get_pending_domains();
            
            if (empty($pending)) {
                error_log('[DR Onboarding] No hay dominios pendientes');
                return;
            }

            foreach ($pending as $item) {
                $this->process_single($item);
                
                // Renovar lock después de cada dominio
                $this->renew_lock();
                
                // Pausa entre dominios para no saturar
                sleep(1);
            }
        } finally {
            $this->release_lock();
        }
    }

    /**
     * Procesar un dominio individual
     */
    private function process_single(array $item): void {
        $primary_domain = $item['primary_domain'];
        $run_id = $item['last_run_id'] ?? Dominios_Reseller_Onboarding_DB::generate_run_id();
        $preset_key = $item['preset_key'];
        $auto_update_ns = (bool) $item['auto_update_ns'];

        // Marcar como running
        Dominios_Reseller_Onboarding_DB::update_state($primary_domain, 'running');
        Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'start', 'info', 'Iniciando proceso de onboarding');

        try {
            // Paso 1: Verificar/crear zona
            $zone_result = $this->step_ensure_zone($run_id, $primary_domain);
            if (!$zone_result['success']) {
                $this->handle_error($run_id, $primary_domain, $zone_result['error']);
                return;
            }

            $zone_id = $zone_result['zone_id'];
            $nameservers = $zone_result['nameservers'];

            // Guardar zone_id y NS
            Dominios_Reseller_Onboarding_DB::upsert_onboarding($primary_domain, [
                'zone_id'     => $zone_id,
                'nameservers' => json_encode($nameservers),
                'ns_verified' => ($zone_result['ns_verified'] ?? false) ? 1 : 0,
            ]);

            // Si la zona ya existía pero NS no apuntan a CF, marcar como pending_ns
            if (($zone_result['existed'] ?? false) && !($zone_result['ns_verified'] ?? true)) {
                $final_state = 'pending_ns';
                $error_msg = 'Zona existe en CF pero los NS del dominio no apuntan a Cloudflare. ' 
                           . ($zone_result['ns_message'] ?? 'Configura los NS en tu registrar.');
                
                Dominios_Reseller_Onboarding_DB::update_state($primary_domain, $final_state, $error_msg);
                Dominios_Reseller_Onboarding_DB::log(
                    $run_id, $primary_domain, 'ns_pending', 'warning',
                    $error_msg,
                    ['zone_id' => $zone_id, 'ns_verified' => false]
                );
                
                // Continuar con preset pero registrar que NS están pendientes
                Dominios_Reseller_Onboarding_DB::log(
                    $run_id, $primary_domain, 'preset_info', 'info',
                    'Se aplicará preset aunque NS estén pendientes (se activará cuando el dominio migre sus NS).'
                );
            }

            // Paso 2: Aplicar preset
            $preset_result = $this->step_apply_preset($run_id, $primary_domain, $zone_id, $preset_key);
            
            // Paso 3: Actualizar NS en Openprovider (si está habilitado)
            $ns_result = ['success' => true, 'skipped' => true];
            if ($auto_update_ns && !empty($nameservers)) {
                $ns_result = $this->step_update_nameservers($run_id, $primary_domain, $nameservers);
            }

            // Determinar estado final
            $final_state = 'onboarded';
            $error_msg = null;

            if (!$preset_result['success']) {
                if ($preset_result['partial']) {
                    $final_state = 'partial';
                    $error_msg = 'Preset aplicado parcialmente: ' . implode(', ', $preset_result['errors']);
                } else {
                    $final_state = 'error';
                    $error_msg = 'Error aplicando preset: ' . implode(', ', $preset_result['errors']);
                }
            }

            if (!$ns_result['success'] && !$ns_result['skipped']) {
                if ($final_state === 'onboarded') {
                    $final_state = 'needs_manual_ns';
                }
                $error_msg = ($error_msg ? $error_msg . ' | ' : '') . 'NS no actualizados: ' . ($ns_result['error'] ?? 'Error desconocido');
            }

            // Actualizar estado final
            Dominios_Reseller_Onboarding_DB::update_state($primary_domain, $final_state, $error_msg);
            
            Dominios_Reseller_Onboarding_DB::log(
                $run_id, 
                $primary_domain, 
                'complete', 
                $final_state === 'onboarded' ? 'info' : 'warning',
                "Onboarding completado con estado: $final_state",
                ['preset_result' => $preset_result, 'ns_result' => $ns_result]
            );

        } catch (\Exception $e) {
            $this->handle_error($run_id, $primary_domain, 'Excepción: ' . $e->getMessage());
        }
    }

    /**
     * Paso 1: Asegurar que existe la zona en Cloudflare
     */
    private function step_ensure_zone(string $run_id, string $primary_domain): array {
        Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'ensure_zone', 'info', 'Verificando/creando zona en Cloudflare');

        // Verificar si ya existe
        $existing_zone = $this->cf_service->get_zone($primary_domain);
        
        if ($existing_zone) {
            // NUEVO: Verificar que los NS reales apuntan a CF (evitar falsos positivos como adfc.com.co)
            $ns_check = $this->cf_service->verify_domain_ns($primary_domain, $existing_zone['name_servers']);
            
            Dominios_Reseller_Onboarding_DB::log(
                $run_id, 
                $primary_domain, 
                'ensure_zone', 
                $ns_check['verified'] ? 'info' : 'warning', 
                $ns_check['verified'] 
                    ? 'Zona existente con NS verificados' 
                    : 'Zona existente pero NS NO apuntan a CF: ' . $ns_check['message'],
                [
                    'zone_id'     => $existing_zone['zone_id'],
                    'ns_verified' => $ns_check['verified'],
                    'actual_ns'   => $ns_check['actual_ns'],
                    'expected_ns' => $ns_check['expected_ns'],
                ]
            );
            
            return [
                'success'      => true,
                'zone_id'      => $existing_zone['zone_id'],
                'nameservers'  => $existing_zone['name_servers'],
                'existed'      => true,
                'ns_verified'  => $ns_check['verified'],
                'ns_message'   => $ns_check['message'],
            ];
        }

        // Crear zona nueva
        $create_result = $this->cf_service->create_zone($primary_domain, false);
        
        if (is_wp_error($create_result)) {
            Dominios_Reseller_Onboarding_DB::log(
                $run_id, 
                $primary_domain, 
                'ensure_zone', 
                'error', 
                'Error creando zona: ' . $create_result->get_error_message()
            );
            
            return [
                'success' => false,
                'error'   => 'Error creando zona: ' . $create_result->get_error_message()
            ];
        }

        Dominios_Reseller_Onboarding_DB::log(
            $run_id, 
            $primary_domain, 
            'ensure_zone', 
            'info', 
            'Zona creada exitosamente',
            ['zone_id' => $create_result['zone_id'], 'nameservers' => $create_result['name_servers']]
        );

        // AUTO-DEPLOY: Desplegar endpoint PHP automáticamente
        $this->auto_deploy_php_endpoint($primary_domain, $run_id);

        // Log de actividad
        Dominios_Reseller_Onboarding_DB::log_activity(
            'cf_migration',
            $primary_domain,
            "Dominio migrado a Cloudflare",
            ['zone_id' => $create_result['zone_id'], 'nameservers' => $create_result['name_servers']]
        );

        return [
            'success'     => true,
            'zone_id'     => $create_result['zone_id'],
            'nameservers' => $create_result['name_servers'],
            'existed'     => $create_result['already_existed'] ?? false
        ];
    }

    /**
     * Auto-deploy endpoint PHP para health check
     * Se ejecuta automáticamente al crear/migrar una zona a Cloudflare
     */
    private function auto_deploy_php_endpoint(string $domain, string $run_id): void {
        try {
            $debug_hub = Dominios_Reseller_Debug_Hub::get_instance();
            
            // Generar token único
            $token = substr(md5($domain . 'dr_maintenance_2025'), 0, 12);
            $filename = "dr-health-{$token}.php";
            
            // Llamar al método de deploy (ahora es público)
            $result = $debug_hub->deploy_maintenance_endpoint($domain, $filename, $token);
            
            // Verificar si fue exitoso
            if (strpos($result, 'EXITOSAMENTE') !== false) {
                Dominios_Reseller_Onboarding_DB::log(
                    $run_id,
                    $domain,
                    'auto_deploy_endpoint',
                    'info',
                    'Endpoint PHP desplegado automáticamente',
                    ['filename' => $filename, 'token' => $token]
                );
                
                Dominios_Reseller_Onboarding_DB::log_activity(
                    'endpoint_deploy',
                    $domain,
                    'Endpoint PHP desplegado automáticamente tras migración CF',
                    ['filename' => $filename, 'auto' => true]
                );
            } else {
                Dominios_Reseller_Onboarding_DB::log(
                    $run_id,
                    $domain,
                    'auto_deploy_endpoint',
                    'warning',
                    'No se pudo desplegar endpoint automáticamente',
                    ['result' => substr($result, 0, 200)]
                );
            }
        } catch (Exception $e) {
            Dominios_Reseller_Onboarding_DB::log(
                $run_id,
                $domain,
                'auto_deploy_endpoint',
                'error',
                'Error desplegando endpoint: ' . $e->getMessage()
            );
        }
    }

    /**
     * Paso 2: Aplicar preset a la zona
     */
    private function step_apply_preset(string $run_id, string $primary_domain, string $zone_id, string $preset_key): array {
        Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'apply_preset', 'info', "Aplicando preset '$preset_key'");

        // Asegurar que los presets estén actualizados
        Dominios_Reseller_Onboarding_DB::insert_default_presets();

        // Obtener preset
        $preset = Dominios_Reseller_Onboarding_DB::get_preset($preset_key);
        
        // Debug: verificar qué se obtuvo de la BD
        Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'apply_preset', 'info', "Debug preset", [
            'preset_found' => $preset ? 'yes' : 'no',
            'payload_length' => $preset ? strlen($preset['payload'] ?? '') : 0,
            'payload_decoded_exists' => isset($preset['payload_decoded']),
            'payload_decoded_type' => gettype($preset['payload_decoded'] ?? null)
        ]);
        
        if (!$preset) {
            Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'apply_preset', 'error', "Preset '$preset_key' no encontrado en BD");
            return [
                'success' => false,
                'partial' => false,
                'errors'  => ["Preset '$preset_key' no encontrado en base de datos"]
            ];
        }

        if (empty($preset['payload_decoded'])) {
            Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'apply_preset', 'error', "Preset '$preset_key' tiene payload vacío", ['payload_raw' => $preset['payload']]);
            return [
                'success' => false,
                'partial' => false,
                'errors'  => ["Preset '$preset_key' tiene payload malformado"]
            ];
        }

        $payload = $preset['payload_decoded'];
        Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'apply_preset', 'info', "Preset cargado correctamente", ['version' => $payload['version'] ?? 'unknown']);

        // Validar payload
        $validation = $this->cf_service->validate_preset_payload($payload);
        if (!$validation['valid']) {
            Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'apply_preset', 'error', 'Payload inválido', $validation['errors']);
            return [
                'success' => false,
                'partial' => false,
                'errors'  => $validation['errors']
            ];
        }

        // Aplicar preset
        $result = $this->cf_service->apply_preset($zone_id, $payload);

        // Log resultado
        $level = $result['success'] ? 'info' : ($result['partial'] ? 'warning' : 'error');
        Dominios_Reseller_Onboarding_DB::log(
            $run_id, 
            $primary_domain, 
            'apply_preset', 
            $level,
            $result['success'] ? 'Preset aplicado correctamente' : 'Preset aplicado con problemas',
            [
                'settings_applied' => $result['settings_applied'],
                'settings_skipped' => $result['settings_skipped'],
                'settings_failed'  => $result['settings_failed'],
                'rules_applied'    => $result['rules_applied'],
                'errors'           => $result['errors']
            ]
        );

        return [
            'success' => $result['success'],
            'partial' => $result['partial'],
            'errors'  => $result['errors']
        ];
    }

    /**
     * Paso 3: Actualizar nameservers en Openprovider
     */
    private function step_update_nameservers(string $run_id, string $primary_domain, array $nameservers): array {
        Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'update_ns', 'info', 'Actualizando nameservers en Openprovider');

        // Esperar un poco para que Cloudflare genere los NS
        sleep(3);

        // Verificar si Openprovider está configurado
        if (!class_exists('Dominios_Reseller_Openprovider_Service')) {
            Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'update_ns', 'warning', 'Servicio Openprovider no disponible');
            return [
                'success' => false,
                'skipped' => true,
                'error'   => 'Servicio Openprovider no configurado'
            ];
        }

        $op_service = Dominios_Reseller_Openprovider_Service::get_instance();
        
        if (!$op_service->is_configured()) {
            Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'update_ns', 'warning', 'Openprovider no configurado');
            return [
                'success' => false,
                'skipped' => true,
                'error'   => 'Credenciales de Openprovider no configuradas'
            ];
        }

        // Actualizar NS
        $result = $op_service->update_nameservers($primary_domain, $nameservers);

        if (is_wp_error($result)) {
            $error_msg = $result->get_error_message();
            
            // Manejar error específico de NS inválidos para .es
            if (strpos($error_msg, 'Invalid request') !== false && strpos($primary_domain, '.es') !== false) {
                Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'update_ns', 'warning', 
                    'NS rechazados por Openprovider. Posiblemente requieren autorización especial para .es. ' .
                    'NS intentados: ' . implode(', ', $nameservers));
                
                // Marcar como completado parcialmente - zona creada, NS requieren configuración manual
                Dominios_Reseller_Onboarding_DB::update_run_data($run_id, [
                    'ns_update_error' => $error_msg,
                    'ns_requires_manual' => true,
                    'final_status' => 'partial'
                ]);
                
                return [
                    'success' => false,
                    'skipped' => true,
                    'error'   => 'NS rechazados por registro .es. Requiere configuración manual o autorización especial. ' .
                                'Zona Cloudflare creada correctamente. NS a configurar manualmente: ' . implode(', ', $nameservers)
                ];
            }
            
            Dominios_Reseller_Onboarding_DB::log(
                $run_id, 
                $primary_domain, 
                'update_ns', 
                'error',
                'Error actualizando NS: ' . $error_msg
            );
            return [
                'success' => false,
                'skipped' => false,
                'error'   => $error_msg
            ];
        }

        Dominios_Reseller_Onboarding_DB::log(
            $run_id, 
            $primary_domain, 
            'update_ns', 
            'info',
            'Nameservers actualizados correctamente',
            ['nameservers' => $nameservers]
        );

        // Pedir a Cloudflare que vuelva a comprobar los NS de la zona.
        // Crítico para zonas en estado `moved` o `pending`: sin esta llamada
        // CF puede tardar horas en reactivar la zona automáticamente.
        $state = Dominios_Reseller_Onboarding_DB::get_onboarding_state($primary_domain);
        $zone_id = $state['zone_id'] ?? null;
        if ($zone_id && method_exists($this->cf_service, 'trigger_zone_activation_check')) {
            $check = $this->cf_service->trigger_zone_activation_check($zone_id);
            Dominios_Reseller_Onboarding_DB::log(
                $run_id,
                $primary_domain,
                'activation_check',
                is_wp_error($check) ? 'warning' : 'info',
                is_wp_error($check)
                    ? 'No se pudo solicitar reactivación a CF: ' . $check->get_error_message()
                    : 'Reactivación de zona solicitada a Cloudflare',
                ['zone_id' => $zone_id]
            );
        }

        return [
            'success' => true,
            'skipped' => false
        ];
    }

    /**
     * Manejar error en el proceso
     */
    private function handle_error(string $run_id, string $primary_domain, string $error): void {
        Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'error', 'error', $error);
        Dominios_Reseller_Onboarding_DB::update_state($primary_domain, 'error', $error);
    }

    /**
     * Verificar si hay lock activo
     */
    private function is_locked(): bool {
        $lock = get_option(self::LOCK_OPTION);
        if (!$lock) {
            return false;
        }
        
        // Verificar timeout
        if (time() - $lock['time'] > self::LOCK_TIMEOUT) {
            $this->release_lock();
            return false;
        }

        return true;
    }

    /**
     * Adquirir lock
     */
    private function acquire_lock(): bool {
        if ($this->is_locked()) {
            return false;
        }

        return update_option(self::LOCK_OPTION, [
            'time'   => time(),
            'pid'    => getmypid()
        ]);
    }

    /**
     * Renovar lock
     */
    private function renew_lock(): void {
        update_option(self::LOCK_OPTION, [
            'time'   => time(),
            'pid'    => getmypid()
        ]);
    }

    /**
     * Liberar lock
     */
    private function release_lock(): void {
        delete_option(self::LOCK_OPTION);
    }

    /**
     * Forzar procesamiento inmediato de la cola
     */
    public function process_now(): array {
        if ($this->is_locked()) {
            return [
                'success' => false,
                'error'   => 'Otro proceso está ejecutándose. Intenta en unos segundos.'
            ];
        }

        $this->process_queue();
        
        return [
            'success' => true,
            'message' => 'Cola procesada'
        ];
    }

    /**
     * Reintentar onboarding de un dominio fallido
     * Resetea el estado y re-encola para procesamiento
     */
    public function retry(string $domain): array {
        $state = Dominios_Reseller_Onboarding_DB::get_onboarding_state($domain);

        if (!$state) {
            return [
                'success' => false,
                'error' => 'Dominio no encontrado en cola de onboarding'
            ];
        }

        // Solo se puede reintentar si falló o tiene error
        if (!in_array($state['state'], ['failed', 'error', self::STATE_FAILED])) {
            return [
                'success' => false,
                'error' => "No se puede reintentar: estado actual es '{$state['state']}'"
            ];
        }

        // Resetear estado y re-encolar
        Dominios_Reseller_Onboarding_DB::update_state($domain, 'queued');

        // Crear nuevo run
        $run_id = Dominios_Reseller_Onboarding_DB::generate_run_id();
        Dominios_Reseller_Onboarding_DB::init_onboarding_run($run_id, $domain, [
            'state' => 'queued',
            'preset_key' => $state['preset_key'] ?? 'wp',
            'auto_update_ns' => $state['auto_update_ns'] ?? false,
            'retries' => 0,
            'started_at' => current_time('mysql')
        ]);

        Dominios_Reseller_Onboarding_DB::log($run_id, $domain, 'retry', 'info', 'Reintento manual iniciado');

        // Forzar procesamiento
        $this->process_queue();

        return [
            'success' => true,
            'message' => "Reintento iniciado para $domain",
            'run_id' => $run_id
        ];
    }

    /**
     * Obtener estado de la cola
     */
    public function get_queue_status(): array {
        $pending = Dominios_Reseller_Onboarding_DB::get_pending_domains();
        $lock = get_option(self::LOCK_OPTION);

        return [
            'pending_count' => count($pending),
            'pending_items' => array_column($pending, 'primary_domain'),
            'is_running'    => $this->is_locked(),
            'lock_info'     => $lock ?: null,
            'next_scheduled'=> wp_next_scheduled(self::CRON_HOOK)
        ];
    }

    /**
     * Actualizar configuración de un dominio ya onboarded
     */
    public function update_config(string $primary_domain, string $preset_key, bool $auto_update_ns = false): array {
        $primary_domain = strtolower(trim($primary_domain));

        // Validar preset
        $preset = Dominios_Reseller_Onboarding_DB::get_preset($preset_key);
        if (!$preset) {
            return [
                'success' => false,
                'error'   => "Preset '$preset_key' no encontrado"
            ];
        }

        // Verificar que el dominio esté onboarded
        $current_state = Dominios_Reseller_Onboarding_DB::get_onboarding_state($primary_domain);
        if (!$current_state || !in_array($current_state['state'], ['onboarded', 'completed'])) {
            return [
                'success' => false,
                'error'   => 'El dominio no está completamente configurado'
            ];
        }

        // Crear nuevo run para actualización
        $run_id = Dominios_Reseller_Onboarding_DB::generate_run_id();

        Dominios_Reseller_Onboarding_DB::init_onboarding_run($run_id, $primary_domain, [
            'preset_key' => $preset_key,
            'auto_update_ns' => $auto_update_ns,
            'state' => self::STATE_PRESET_APPLY, // Saltar a aplicar preset directamente
            'started_at' => current_time('mysql'),
            'retries' => 0,
            'is_update' => true
        ]);

        // Programar aplicación del preset directamente (sin crear zona)
        wp_schedule_single_event(time() + 2, self::ACTION_PRESET_APPLY, [$run_id, $primary_domain]);

        Dominios_Reseller_Onboarding_DB::log(
            $run_id,
            $primary_domain,
            'update_config',
            'info',
            "Actualizando configuración con preset '$preset_key'"
        );

        return [
            'success' => true,
            'run_id'  => $run_id,
            'message' => "Configuración actualizada para '$primary_domain'"
        ];
    }

    // ========================================
    // MÉTODOS DE ACCIÓN PROGRAMADA (NUEVO SISTEMA ASYNC)
    // ========================================

    /**
     * Acción 1: Verificar/crear zona en Cloudflare
     */
    public function action_zone_check(string $run_id, string $primary_domain): void {
        Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'action_zone_check', 'info', 'Iniciando verificación/creación de zona');

        // Actualizar estado
        $this->transition_state($run_id, $primary_domain, self::STATE_ZONE_CHECK);

        try {
            // Verificar si ya existe la zona
            $existing_zone = $this->cf_service->get_zone($primary_domain);
            
            if ($existing_zone) {
                // Zona ya existe, pasar directamente a esperar NS
                Dominios_Reseller_Onboarding_DB::update_run_data($run_id, [
                    'zone_id' => $existing_zone['zone_id'],
                    'nameservers' => json_encode($existing_zone['name_servers'])
                ]);
                
                Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'action_zone_check', 'info', 'Zona ya existente, pasando a esperar NS');
                wp_schedule_single_event(time() + 5, self::ACTION_ZONE_WAIT_NS, [$run_id, $primary_domain]);
                return;
            }

            // Crear zona nueva
            $create_result = $this->cf_service->create_zone($primary_domain, false);
            
            if (is_wp_error($create_result)) {
                throw new \Exception('Error creando zona: ' . $create_result->get_error_message());
            }

            // Guardar datos de la zona
            Dominios_Reseller_Onboarding_DB::update_run_data($run_id, [
                'zone_id' => $create_result['zone_id'],
                'nameservers' => json_encode($create_result['name_servers'])
            ]);

            Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'action_zone_check', 'info', 'Zona creada exitosamente, esperando NS');

            // Programar siguiente acción: esperar NS
            wp_schedule_single_event(time() + self::NS_POLL_INTERVAL, self::ACTION_ZONE_WAIT_NS, [$run_id, $primary_domain]);

        } catch (\Exception $e) {
            $this->handle_action_error($run_id, $primary_domain, 'zone_check', $e->getMessage());
        }
    }

    /**
     * Acción 2: Esperar a que los NS estén disponibles
     */
    public function action_zone_wait_ns(string $run_id, string $primary_domain): void {
        Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'action_zone_wait_ns', 'info', 'Verificando disponibilidad de NS');

        // Actualizar estado
        $this->transition_state($run_id, $primary_domain, self::STATE_ZONE_WAIT_NS);

        try {
            $run_data = Dominios_Reseller_Onboarding_DB::get_run_data($run_id);
            if (!$run_data || empty($run_data['zone_id'])) {
                throw new \Exception('No se encontraron datos de zona para este run_id');
            }

            $zone_id = $run_data['zone_id'];
            $nameservers = json_decode($run_data['nameservers'] ?? '[]', true);

            // Verificar estado de la zona
            $zone_status = $this->cf_service->get_zone_status($zone_id);
            
            if (is_wp_error($zone_status)) {
                throw new \Exception('Error obteniendo estado de zona: ' . $zone_status->get_error_message());
            }

            if ($zone_status['status'] === 'active') {
                // Zona activa, pasar a aplicar preset
                Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'action_zone_wait_ns', 'info', 'Zona activa, aplicando preset');
                wp_schedule_single_event(time() + 2, self::ACTION_PRESET_APPLY, [$run_id, $primary_domain]);
                return;
            }

            // Verificar timeout
            $elapsed = time() - strtotime($run_data['started_at']);
            if ($elapsed > self::TIMEOUT_ZONE_WAIT_NS) {
                throw new \Exception("Timeout esperando activación de zona ({$elapsed}s > " . self::TIMEOUT_ZONE_WAIT_NS . 's)');
            }

            // Zona aún no activa, reprogramar verificación
            Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'action_zone_wait_ns', 'info', "Zona aún pendiente (estado: {$zone_status['status']}), reintentando en " . self::NS_POLL_INTERVAL . 's');
            wp_schedule_single_event(time() + self::NS_POLL_INTERVAL, self::ACTION_ZONE_WAIT_NS, [$run_id, $primary_domain]);

        } catch (\Exception $e) {
            $this->handle_action_error($run_id, $primary_domain, 'zone_wait_ns', $e->getMessage());
        }
    }

    /**
     * Acción 3: Aplicar preset a la zona
     */
    public function action_preset_apply(string $run_id, string $primary_domain): void {
        Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'action_preset_apply', 'info', 'Aplicando preset a la zona');

        // Actualizar estado
        $this->transition_state($run_id, $primary_domain, self::STATE_PRESET_APPLY);

        try {
            $run_data = Dominios_Reseller_Onboarding_DB::get_run_data($run_id);
            if (!$run_data || empty($run_data['zone_id'])) {
                throw new \Exception('No se encontraron datos de zona para este run_id');
            }

            $zone_id = $run_data['zone_id'];
            $preset_key = $run_data['preset_key'];

            // Cargar preset desde DB
            $preset = Dominios_Reseller_Onboarding_DB::get_preset($preset_key);
            if (!$preset || empty($preset['payload_decoded'])) {
                throw new \Exception("Preset '$preset_key' no encontrado o sin payload");
            }

            // Obtener configuración actual de la zona antes de aplicar preset
            $current_settings = $this->cf_service->get_zone_settings($zone_id);
            if (!is_wp_error($current_settings)) {
                Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'action_preset_apply', 'info', 'Configuración actual de zona obtenida', $current_settings);
            } else {
                Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'action_preset_apply', 'warning', 'No se pudo obtener configuración actual: ' . $current_settings->get_error_message());
            }

            // Aplicar preset
            $result = $this->cf_service->apply_preset($zone_id, $preset['payload_decoded']);

            // Guardar resultado del preset
            Dominios_Reseller_Onboarding_DB::update_run_data($run_id, [
                'preset_applied' => $result['success'],
                'preset_partial' => $result['partial'] ?? false,
                'preset_errors' => json_encode($result['errors'] ?? [])
            ]);

            if ($result['success']) {
                Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'action_preset_apply', 'info', 'Preset aplicado correctamente');
            } else if ($result['partial']) {
                Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'action_preset_apply', 'warning', 'Preset aplicado parcialmente', $result['errors']);
            } else {
                throw new \Exception('Error aplicando preset: ' . implode(', ', $result['errors']));
            }

            // Verificar si necesitamos actualizar NS
            if ($run_data['auto_update_ns']) {
                wp_schedule_single_event(time() + 2, self::ACTION_NS_UPDATE, [$run_id, $primary_domain]);
            } else {
                // Completar onboarding
                $this->complete_onboarding($run_id, $primary_domain, $result['success'] ? 'completed' : 'partial');
            }

        } catch (\Exception $e) {
            $this->handle_action_error($run_id, $primary_domain, 'preset_apply', $e->getMessage());
        }
    }

    /**
     * Acción 4: Actualizar NS en Openprovider
     */
    public function action_ns_update(string $run_id, string $primary_domain): void {
        Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'action_ns_update', 'info', 'Actualizando NS en Openprovider');

        // Actualizar estado
        $this->transition_state($run_id, $primary_domain, self::STATE_NS_UPDATE);

        try {
            $run_data = Dominios_Reseller_Onboarding_DB::get_run_data($run_id);
            if (!$run_data || empty($run_data['nameservers'])) {
                throw new \Exception('No se encontraron nameservers para este run_id');
            }

            $nameservers = json_decode($run_data['nameservers'], true);

            // Verificar Openprovider
            if (!class_exists('Dominios_Reseller_Openprovider_Service')) {
                throw new \Exception('Servicio Openprovider no disponible');
            }

            $op_service = Dominios_Reseller_Openprovider_Service::get_instance();
            if (!$op_service->is_configured()) {
                throw new \Exception('Openprovider no configurado');
            }

            // Actualizar NS
            $result = $op_service->update_nameservers($primary_domain, $nameservers);

            if (is_wp_error($result)) {
                throw new \Exception('Error actualizando NS: ' . $result->get_error_message());
            }

            Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'action_ns_update', 'info', 'NS actualizados correctamente');

            // Completar onboarding
            $this->complete_onboarding($run_id, $primary_domain, 'completed');

        } catch (\Exception $e) {
            $this->handle_action_error($run_id, $primary_domain, 'ns_update', $e->getMessage());
        }
    }

    // ========================================
    // MÉTODOS AUXILIARES PARA EL SISTEMA ASYNC
    // ========================================

    /**
     * Transición de estado con logging
     */
    private function transition_state(string $run_id, string $primary_domain, string $new_state): void {
        Dominios_Reseller_Onboarding_DB::update_run_data($run_id, [
            'state' => $new_state,
            'updated_at' => current_time('mysql')
        ]);

        Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'state_transition', 'info', "Estado cambiado a: $new_state");
    }

    /**
     * Completar onboarding exitosamente
     */
    private function complete_onboarding(string $run_id, string $primary_domain, string $final_status): void {
        $run_data = Dominios_Reseller_Onboarding_DB::get_run_data($run_id);
        
        Dominios_Reseller_Onboarding_DB::update_run_data($run_id, [
            'state' => self::STATE_COMPLETED,
            'completed_at' => current_time('mysql'),
            'final_status' => $final_status
        ]);

        // Actualizar estado legacy para compatibilidad
        Dominios_Reseller_Onboarding_DB::update_state($primary_domain, $final_status);

        Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'completed', 'info', "Onboarding completado con estado: $final_status");
    }

    /**
     * Manejar error en acción programada con reintento
     */
    private function handle_action_error(string $run_id, string $primary_domain, string $action, string $error): void {
        $run_data = Dominios_Reseller_Onboarding_DB::get_run_data($run_id);
        $retries = ($run_data['retries'] ?? 0) + 1;

        Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'action_error', 'error', "Error en $action (intento $retries): $error");

        if ($retries < self::MAX_RETRIES) {
            // Reintentar con backoff exponencial
            $delay = pow(2, $retries - 1) * 30; // 30s, 60s, 120s...
            
            Dominios_Reseller_Onboarding_DB::update_run_data($run_id, ['retries' => $retries]);
            
            Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'retry', 'warning', "Reintentando $action en {$delay}s (intento $retries/" . self::MAX_RETRIES . ')');
            
            // Reprogramar la misma acción
            $action_hook = constant("self::ACTION_" . strtoupper($action));
            wp_schedule_single_event(time() + $delay, $action_hook, [$run_id, $primary_domain]);
            
        } else {
            // Máximo de reintentos alcanzado
            Dominios_Reseller_Onboarding_DB::update_run_data($run_id, [
                'state' => self::STATE_FAILED,
                'failed_at' => current_time('mysql'),
                'error_message' => $error
            ]);

            // Actualizar estado legacy
            Dominios_Reseller_Onboarding_DB::update_state($primary_domain, 'error', $error);

            Dominios_Reseller_Onboarding_DB::log($run_id, $primary_domain, 'failed', 'error', "Máximo de reintentos alcanzado para $action: $error");
        }
    }

    /**
     * Procesar dominio individual para test (Debug Hub)
     */
    public function process_single_domain_test(string $domain): array {
        try {
            Dominios_Reseller_Onboarding_DB::log('test', $domain, 'test', 'info', "Procesando dominio de test: {$domain}");

            // Verificar si ya existe un run para este dominio
            $existing_run = Dominios_Reseller_Onboarding_DB::get_latest_run_by_domain($domain);

            if (!$existing_run) {
                return [
                    'success' => false,
                    'error' => 'Dominio no encontrado en cola de onboarding',
                    'current_state' => 'not_queued'
                ];
            }

            $run_id = $existing_run['run_id'];
            $current_state = $existing_run['state'];

            // Simular procesamiento basado en el estado actual
            $result = [
                'success' => true,
                'current_state' => $current_state,
                'final_state' => $current_state,
                'cloudflare_zone' => null,
                'preset_applied' => null,
                'message' => 'Procesamiento simulado completado'
            ];

            // Simular diferentes resultados basados en el dominio
            if (strpos($domain, 'test-success') !== false) {
                $result['final_state'] = 'completed';
                $result['cloudflare_zone'] = 'test-zone-' . time();
                $result['preset_applied'] = 'wp';
                $result['message'] = 'Onboarding completado exitosamente (test)';
            } elseif (strpos($domain, 'test-cf-error') !== false) {
                $result['success'] = false;
                $result['error'] = 'Error simulado en Cloudflare API';
                $result['final_state'] = 'failed';
            } elseif (strpos($domain, 'test-ns-wait') !== false) {
                $result['final_state'] = 'zone_wait_ns';
                $result['cloudflare_zone'] = 'test-zone-' . time();
                $result['message'] = 'Esperando propagación de NS (test)';
            } else {
                // Caso por defecto - simular éxito básico
                $result['final_state'] = 'completed';
                $result['cloudflare_zone'] = 'zone-' . substr(md5($domain), 0, 8);
                $result['preset_applied'] = 'wp';
            }

            // Actualizar estado si cambió
            if ($result['final_state'] !== $current_state) {
                Dominios_Reseller_Onboarding_DB::update_run_data($run_id, [
                    'state' => $result['final_state'],
                    'completed_at' => $result['final_state'] === 'completed' ? current_time('mysql') : null,
                    'failed_at' => $result['final_state'] === 'failed' ? current_time('mysql') : null
                ]);

                // Actualizar estado legacy
                $legacy_state = $this->map_state_to_legacy($result['final_state']);
                Dominios_Reseller_Onboarding_DB::update_state($domain, $legacy_state, $result['message']);
            }

            Dominios_Reseller_Onboarding_DB::log('test', $domain, 'test', 'info', "Test completado para {$domain}: " . json_encode($result));

            return $result;

        } catch (Exception $e) {
            Dominios_Reseller_Onboarding_DB::log('test', $domain, 'test', 'error', "Error en procesamiento de test: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'current_state' => 'error'
            ];
        }
    }

    /**
     * Mapear estado moderno a legacy
     */
    private function map_state_to_legacy(string $state): string {
        $mapping = [
            'queued' => 'queued',
            'zone_check' => 'checking',
            'zone_wait_ns' => 'waiting_ns',
            'preset_apply' => 'applying_preset',
            'ns_update' => 'updating_ns',
            'completed' => 'onboarded',
            'failed' => 'error'
        ];

        return $mapping[$state] ?? 'unknown';
    }
}

// Inicializar worker
add_action('plugins_loaded', function() {
    if (class_exists('Dominios_Reseller_Cloudflare_Service')) {
        Dominios_Reseller_Onboarding_Worker::get_instance();
    }
});
