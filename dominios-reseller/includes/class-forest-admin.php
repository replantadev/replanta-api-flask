<?php
/**
 * Forest Program Admin Page
 * 
 * Renderiza la interfaz de administración del Forest Program.
 * Todo async con AJAX para máximo rendimiento.
 *
 * @package DominiosReseller
 * @since   1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Dominios_Reseller_Forest_Admin {

    /**
     * Register admin page
     */
    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ], 25 );
    }

    /**
     * Add submenu under Dominios Reseller
     */
    public static function add_menu(): void {
        add_submenu_page(
            'dominios-reseller',
            'Forest Program',
            'Forest Program',
            'manage_options',
            'dr-forest-program',
            [ __CLASS__, 'render_page' ]
        );
    }

    /**
     * Render admin page
     */
    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Acceso denegado' );
        }

        $settings = Dominios_Reseller_Forest_Program::get_settings();
        $nonce = wp_create_nonce( 'dr_forest_nonce' );
        ?>
        <div class="wrap dr-forest-wrap">
        <style>
        /* ══════════════════════════════════════════════════════════════
           FOREST PROGRAM ADMIN STYLES
        ══════════════════════════════════════════════════════════════ */
        .dr-forest-wrap { max-width: 1400px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .ph-icon { width: 20px; height: 20px; display: inline-block; vertical-align: middle; flex-shrink: 0; }
        .fp-stat-icon .ph-icon { width: 28px; height: 28px; }
        
        /* ─── Header ─── */
        .fp-header {
            background: linear-gradient(135deg, #071a0e 0%, #0f2d1a 50%, #1a3d28 100%);
            border-radius: 16px;
            padding: 28px 36px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
        }
        .fp-header-left h1 { color: #93F1C9; font-size: 1.5rem; margin: 0 0 6px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .fp-header-left p { color: rgba(255,255,255,.6); margin: 0; font-size: .9rem; }
        .fp-header-right { display: flex; gap: 12px; }
        .fp-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 20px; border-radius: 10px;
            font: 600 .88rem/1 inherit;
            cursor: pointer; border: none;
            transition: all .2s ease;
        }
        .fp-btn--primary { background: #41999F; color: #fff; }
        .fp-btn--primary:hover { background: #37878d; transform: translateY(-1px); }
        .fp-btn--ghost { background: rgba(255,255,255,.08); color: #93F1C9; border: 1px solid rgba(147,241,201,.3); }
        .fp-btn--ghost:hover { background: rgba(147,241,201,.1); }
        .fp-btn--sm { padding: 6px 12px; font-size: .8rem; }
        .fp-btn:disabled { opacity: .5; cursor: not-allowed; }
        
        /* ─── Tabs ─── */
        .fp-tabs { display: flex; gap: 4px; border-bottom: 2px solid #e5e7eb; margin-bottom: 24px; }
        .fp-tab {
            padding: 12px 20px; background: none; border: none;
            font: 600 .88rem/1 inherit; color: #6b7280;
            cursor: pointer; position: relative; transition: color .2s;
        }
        .fp-tab:hover { color: #374151; }
        .fp-tab.is-active { color: #166534; }
        .fp-tab.is-active::after {
            content: ''; position: absolute; bottom: -2px; left: 0; right: 0;
            height: 2px; background: #16a34a; border-radius: 2px 2px 0 0;
        }
        .fp-tab-panel { display: none; }
        .fp-tab-panel.is-active { display: block; }
        
        /* ─── Stats Grid ─── */
        .fp-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .fp-stat {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
            padding: 20px 24px; text-align: center;
            transition: all .2s; position: relative; overflow: hidden;
        }
        .fp-stat:hover { border-color: #d1fae5; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,.06); }
        .fp-stat-icon { font-size: 1.5rem; margin-bottom: 8px; }
        .fp-stat-val { font-size: 2rem; font-weight: 700; color: #166534; line-height: 1; }
        .fp-stat-val.is-loading { color: #d1d5db; }
        .fp-stat-lbl { font-size: .75rem; color: #6b7280; text-transform: uppercase; letter-spacing: .07em; margin-top: 6px; }
        .fp-stat--teal .fp-stat-val { color: #0d9488; }
        .fp-stat--purple .fp-stat-val { color: #7c3aed; }
        .fp-stat--amber .fp-stat-val { color: #d97706; }
        
        /* ─── Card Box ─── */
        .fp-box { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 20px; overflow: hidden; }
        .fp-box-head {
            padding: 16px 24px; border-bottom: 1px solid #f3f4f6;
            display: flex; align-items: center; gap: 12px;
        }
        .fp-box-head h2 { margin: 0; font-size: 1rem; font-weight: 600; flex: 1; color: #1f2937; }
        .fp-box-head h2 span { font-weight: 400; color: #9ca3af; font-size: .85rem; }
        .fp-box-body { padding: 24px; }
        .fp-box-body.is-loading { min-height: 200px; display: flex; align-items: center; justify-content: center; }
        
        /* ─── Forms ─── */
        .fp-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .fp-form-group { margin-bottom: 16px; }
        .fp-form-group label { display: block; font-size: .82rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .fp-form-group label small { font-weight: 400; color: #9ca3af; }
        .fp-input, .fp-textarea {
            width: 100%; padding: 10px 14px;
            border: 1.5px solid #e5e7eb; border-radius: 8px;
            font: inherit; font-size: .9rem;
            transition: border-color .2s, box-shadow .2s;
        }
        .fp-input:focus, .fp-textarea:focus {
            border-color: #41999F; outline: none;
            box-shadow: 0 0 0 3px rgba(65,153,159,.12);
        }
        .fp-input--mono { font-family: 'Consolas', 'Monaco', monospace; font-size: .85rem; }
        .fp-textarea { resize: vertical; min-height: 80px; }
        .fp-hint { font-size: .78rem; color: #6b7280; margin-top: 4px; }
        .fp-toggle-row { display: flex; align-items: center; gap: 12px; padding: 8px 0; }
        .fp-toggle {
            position: relative; width: 44px; height: 24px;
            background: #d1d5db; border-radius: 24px;
            cursor: pointer; transition: background .2s;
        }
        .fp-toggle::after {
            content: ''; position: absolute;
            width: 18px; height: 18px; top: 3px; left: 3px;
            background: #fff; border-radius: 50%;
            transition: transform .2s;
        }
        .fp-toggle.is-active { background: #16a34a; }
        .fp-toggle.is-active::after { transform: translateX(20px); }
        
        /* ─── Table ─── */
        .fp-table { width: 100%; border-collapse: collapse; font-size: .87rem; }
        .fp-table th {
            background: #f9fafb; padding: 10px 14px; text-align: left;
            font-size: .72rem; text-transform: uppercase; letter-spacing: .07em;
            color: #6b7280; border-bottom: 2px solid #e5e7eb; white-space: nowrap;
        }
        .fp-table td { padding: 12px 14px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        .fp-table tbody tr:hover td { background: #f0fdf4; }
        .fp-table tbody tr:last-child td { border-bottom: none; }
        
        /* ─── Domain Row ─── */
        .fp-domain-name { font-weight: 600; color: #1f2937; }
        .fp-domain-sub { font-size: .78rem; color: #9ca3af; }
        .fp-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 10px; border-radius: 999px;
            font-size: .72rem; font-weight: 600;
        }
        .fp-badge--green { background: #dcfce7; color: #166534; }
        .fp-badge--teal { background: #ccf0f2; color: #0d9488; }
        .fp-badge--gray { background: #f3f4f6; color: #6b7280; }
        .fp-badge--amber { background: #fef3c7; color: #92400e; }
        .fp-product { font-weight: 600; text-transform: capitalize; }
        .fp-trees-count { font-weight: 700; color: #166534; }
        
        /* ─── Toggle Switch (mini) ─── */
        .fp-switch {
            position: relative; width: 36px; height: 20px;
            background: #d1d5db; border-radius: 20px;
            cursor: pointer; transition: background .2s;
            border: none;
        }
        .fp-switch::after {
            content: ''; position: absolute;
            width: 14px; height: 14px; top: 3px; left: 3px;
            background: #fff; border-radius: 50%;
            transition: transform .2s;
            box-shadow: 0 1px 3px rgba(0,0,0,.2);
        }
        .fp-switch.is-on { background: #16a34a; }
        .fp-switch.is-on::after { transform: translateX(16px); }
        .fp-switch:disabled { opacity: .5; cursor: not-allowed; }
        
        /* ─── History ─── */
        .fp-tree-species { display: flex; align-items: center; gap: 8px; }
        .fp-tree-emoji { font-size: 1.2rem; }
        .fp-tree-info { flex: 1; }
        .fp-tree-name { font-weight: 600; color: #1f2937; }
        .fp-tree-project { font-size: .78rem; color: #6b7280; }
        .fp-co2-val { font-weight: 600; color: #0d9488; }
        .fp-email-status { display: inline-flex; align-items: center; gap: 4px; }
        .fp-email-status.is-sent { color: #16a34a; }
        .fp-email-status.is-pending { color: #d97706; }
        .fp-email-status.is-failed { color: #dc2626; }
        
        /* ─── Pagination ─── */
        .fp-pagination { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 20px; }
        .fp-page-btn {
            padding: 6px 12px; background: #f3f4f6; border: 1px solid #e5e7eb;
            border-radius: 6px; font-size: .85rem; cursor: pointer;
            transition: all .2s;
        }
        .fp-page-btn:hover:not(:disabled) { background: #e5e7eb; }
        .fp-page-btn.is-current { background: #166534; color: #fff; border-color: #166534; }
        .fp-page-btn:disabled { opacity: .5; cursor: not-allowed; }
        .fp-page-info { font-size: .85rem; color: #6b7280; }
        
        /* ─── Search/Filter Bar ─── */
        .fp-toolbar { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .fp-search {
            flex: 1; min-width: 200px; padding: 8px 14px;
            border: 1.5px solid #e5e7eb; border-radius: 8px;
            font-size: .9rem;
        }
        .fp-search:focus { border-color: #41999F; outline: none; }
        .fp-filter-select {
            padding: 8px 14px; border: 1.5px solid #e5e7eb;
            border-radius: 8px; font-size: .9rem; background: #fff;
        }
        
        /* ─── Toast ─── */
        .fp-toast {
            position: fixed; bottom: 24px; right: 24px;
            padding: 14px 20px; border-radius: 10px;
            font-size: .9rem; font-weight: 500;
            box-shadow: 0 8px 30px rgba(0,0,0,.15);
            z-index: 99999; transform: translateY(100px);
            opacity: 0; transition: all .3s ease;
        }
        .fp-toast.is-visible { transform: translateY(0); opacity: 1; }
        .fp-toast--success { background: #166534; color: #fff; }
        .fp-toast--error { background: #dc2626; color: #fff; }
        
        /* ─── Spinner ─── */
        .fp-spinner {
            width: 24px; height: 24px;
            border: 3px solid #e5e7eb;
            border-top-color: #16a34a;
            border-radius: 50%;
            animation: fp-spin 1s linear infinite;
        }
        @keyframes fp-spin { to { transform: rotate(360deg); } }
        .dashicons.fp-spin { animation: fp-spin 1s linear infinite; }
        .fp-btn .dashicons { font-size: 16px; width: 16px; height: 16px; vertical-align: -3px; }
        
        /* ─── Empty State ─── */
        .fp-empty { text-align: center; padding: 48px 24px; color: #6b7280; }
        .fp-empty-icon { font-size: 3rem; margin-bottom: 12px; opacity: .5; }
        .fp-empty h3 { margin: 0 0 8px; color: #374151; }
        .fp-empty p { margin: 0; font-size: .9rem; }
        
        /* ─── Responsive ─── */
        @media (max-width: 1024px) {
            .fp-stats { grid-template-columns: repeat(2, 1fr); }
            .fp-form-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .fp-header { flex-direction: column; align-items: flex-start; }
            .fp-stats { grid-template-columns: 1fr 1fr; }
            .fp-table { font-size: .8rem; }
            .fp-table th, .fp-table td { padding: 8px 10px; }
        }
        </style>

        <!-- Header -->
        <header class="fp-header">
            <div class="fp-header-left">
                <h1><svg class="ph-icon" viewBox="0 0 256 256"><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm-8-136v96a8,8,0,0,1-16,0V80a8,8,0,0,1,16,0Zm48,0v96a8,8,0,0,1-16,0V80a8,8,0,0,1,16,0Z" fill="currentColor"/></svg> Forest Program</h1>
                <p>Automatiza la plantación de árboles para tus clientes de hosting</p>
            </div>
            <div class="fp-header-right">
                <button class="fp-btn fp-btn--ghost" id="fp-sync-upmind" style="display:none;">
                    <svg class="ph-icon" viewBox="0 0 256 256"><path d="M197.67,186.37a8,8,0,0,1,0,11.29C196.58,198.73,170.82,224,128,224c-37.39,0-64.53-22.4-80-39.85V208a8,8,0,0,1-16,0V160a8,8,0,0,1,8-8H88a8,8,0,0,1,0,16H55.44C67.76,183.35,93,208,128,208c36,0,58.14-21.46,58.36-21.68A8,8,0,0,1,197.67,186.37ZM216,40a8,8,0,0,0-8,8V71.85C192.53,54.4,165.39,32,128,32,85.18,32,59.42,57.27,58.34,58.34a8,8,0,0,0,11.3,11.34C69.86,69.46,92,48,128,48c35,0,60.24,24.65,72.56,40H168a8,8,0,0,0,0,16h48a8,8,0,0,0,8-8V48A8,8,0,0,0,216,40Z" fill="currentColor"/></svg>
                    Sync Upmind
                </button>
                <button class="fp-btn fp-btn--primary" id="fp-test-plant">
                    <svg class="ph-icon" viewBox="0 0 256 256"><path d="M223.45,40.07a8,8,0,0,0-7.52-7.52C139.8,28.08,78.82,50,52.82,76a87.09,87.09,0,0,0-23.11,43.3,8,8,0,0,0,4.2,8.51,8.2,8.2,0,0,0,3.54.83,8,8,0,0,0,5.6-2.31l71-71C114.05,55.33,187.93,46.87,214.64,44.53,219.38,46.9,221.81,54.36,220.33,62.8c-2.85,16.14-18.26,38.69-44.15,64.58L132,171.56V216a8,8,0,0,0,8,8,8.25,8.25,0,0,0,2-.25,87.67,87.67,0,0,0,51.11-30.48c21.57-25.9,25.93-55.68,12.85-88.65a8,8,0,1,0-14.74,6.26c10.09,25.37,6.93,48.77-9.41,69.36A72.15,72.15,0,0,1,148,200v-16.69l51.32-51.31c25.89-25.9,44.74-54.06,52.91-79A91.1,91.1,0,0,0,223.45,40.07ZM104,144H40a8,8,0,0,0-8,8v8a72,72,0,0,0,72,72h8a8,8,0,0,0,8-8V160A16,16,0,0,0,104,144Z" fill="currentColor"/></svg>
                    Test Plant
                </button>
            </div>
        </header>

        <!-- Stats -->
        <div class="fp-stats" id="fp-stats">
            <div class="fp-stat">
                <div class="fp-stat-icon"><svg class="ph-icon" viewBox="0 0 256 256"><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm-8-136v96a8,8,0,0,1-16,0V80a8,8,0,0,1,16,0Zm48,0v96a8,8,0,0,1-16,0V80a8,8,0,0,1,16,0Z" fill="currentColor"/></svg></div>
                <div class="fp-stat-val is-loading" id="st-trees">—</div>
                <div class="fp-stat-lbl">Árboles plantados</div>
            </div>
            <div class="fp-stat fp-stat--teal">
                <div class="fp-stat-icon"><svg class="ph-icon" viewBox="0 0 256 256"><path d="M24,128a8,8,0,0,1,8-8H56a72,72,0,0,1,72-72v64a8,8,0,0,0,16,0V48a72,72,0,0,1,72,72h24a8,8,0,0,1,0,16H216a72,72,0,0,1-72,72V144a8,8,0,0,0-16,0v64a72,72,0,0,1-72-72H32A8,8,0,0,1,24,128Z" fill="currentColor"/></svg></div>
                <div class="fp-stat-val is-loading" id="st-co2">—</div>
                <div class="fp-stat-lbl">Kg CO₂ capturados</div>
            </div>
            <div class="fp-stat fp-stat--purple">
                <div class="fp-stat-icon"><svg class="ph-icon" viewBox="0 0 256 256"><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm45.66,85.66-56,56a8,8,0,0,1-11.32,0l-24-24a8,8,0,0,1,11.32-11.32L112,148.69l50.34-50.35a8,8,0,0,1,11.32,11.32Z" fill="currentColor"/></svg></div>
                <div class="fp-stat-val is-loading" id="st-forest">—</div>
                <div class="fp-stat-lbl">Forest enabled</div>
            </div>
            <div class="fp-stat fp-stat--amber">
                <div class="fp-stat-icon"><svg class="ph-icon" viewBox="0 0 256 256"><path d="M216,40H40A16,16,0,0,0,24,56V200a16,16,0,0,0,16,16H216a16,16,0,0,0,16-16V56A16,16,0,0,0,216,40Zm0,160H40V56H216V200ZM184,96a8,8,0,0,1-8,8H80a8,8,0,0,1,0-16h96A8,8,0,0,1,184,96Zm0,32a8,8,0,0,1-8,8H80a8,8,0,0,1,0-16h96A8,8,0,0,1,184,128Zm0,32a8,8,0,0,1-8,8H80a8,8,0,0,1,0-16h96A8,8,0,0,1,184,160Z" fill="currentColor"/></svg></div>
                <div class="fp-stat-val is-loading" id="st-queue">—</div>
                <div class="fp-stat-lbl">En cola</div>
            </div>
        </div>

        <!-- Forecast -->
        <div class="fp-box" id="fp-forecast-box" style="margin:0 0 24px;">
            <div class="fp-box-head">
                <h2><span class="dashicons dashicons-chart-line" style="font-size:20px;width:20px;height:20px;vertical-align:-3px;margin-right:6px;color:#16a34a;"></span> Previsión de plantaciones</h2>
            </div>
            <div class="fp-box-body">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;">
                    <div style="padding:14px;border-radius:10px;background:#ecfdf5;">
                        <div style="font-size:11px;color:#047857;text-transform:uppercase;letter-spacing:.05em;">Próximos 30 días</div>
                        <div style="font-size:24px;font-weight:700;color:#065f46;" id="fc-30">—</div>
                    </div>
                    <div style="padding:14px;border-radius:10px;background:#eff6ff;">
                        <div style="font-size:11px;color:#1d4ed8;text-transform:uppercase;letter-spacing:.05em;">Próximos 60 días</div>
                        <div style="font-size:24px;font-weight:700;color:#1e3a8a;" id="fc-60">—</div>
                    </div>
                    <div style="padding:14px;border-radius:10px;background:#fef3c7;">
                        <div style="font-size:11px;color:#92400e;text-transform:uppercase;letter-spacing:.05em;">Próximos 90 días</div>
                        <div style="font-size:24px;font-weight:700;color:#78350f;" id="fc-90">—</div>
                    </div>
                    <div style="padding:14px;border-radius:10px;background:#f5f3ff;">
                        <div style="font-size:11px;color:#6d28d9;text-transform:uppercase;letter-spacing:.05em;">Media mensual</div>
                        <div style="font-size:24px;font-weight:700;color:#4c1d95;" id="fc-avg">—</div>
                        <div style="font-size:11px;color:#6b7280;margin-top:4px;" id="fc-runway">runway: —</div>
                    </div>
                </div>
                <canvas id="fc-chart" height="80" style="margin-top:18px;width:100%;"></canvas>
            </div>
        </div>

        <!-- Tabs -->
        <div class="fp-tabs">
            <button class="fp-tab is-active" data-tab="domains">Dominios</button>
            <button class="fp-tab" data-tab="history">Historial</button>
            <button class="fp-tab" data-tab="logs">Logs / Fallidos</button>
            <button class="fp-tab" data-tab="settings">Configuración</button>
        </div>

        <!-- Tab: Domains -->
        <div class="fp-tab-panel is-active" id="panel-domains">
            <div class="fp-box">
                <div class="fp-box-head">
                    <h2>Dominios registrados <span id="domains-count"></span></h2>
                </div>
                <div class="fp-box-body">
                    <div class="fp-toolbar">
                        <input type="text" class="fp-search" id="domains-search" placeholder="Buscar dominio o cliente...">
                        <select class="fp-filter-select" id="domains-filter">
                            <option value="all">Todos los dominios</option>
                            <option value="forest">Solo Forest Program</option>
                            <option value="eligible">Solo elegibles</option>
                        </select>
                    </div>
                    <div id="domains-table-wrap">
                        <div class="fp-box-body is-loading"><div class="fp-spinner"></div></div>
                    </div>
                    <div class="fp-pagination" id="domains-pagination"></div>
                </div>
            </div>
        </div>

        <!-- Tab: History -->
        <div class="fp-tab-panel" id="panel-history">
            <div class="fp-box">
                <div class="fp-box-head">
                    <h2>Historial de plantación <span id="history-count"></span></h2>
                </div>
                <div class="fp-box-body">
                    <div id="history-table-wrap">
                        <div class="fp-box-body is-loading"><div class="fp-spinner"></div></div>
                    </div>
                    <div class="fp-pagination" id="history-pagination"></div>
                </div>
            </div>
        </div>

        <!-- Tab: Logs / Failed -->
        <div class="fp-tab-panel" id="panel-logs">
            <!-- Failed Items -->
            <div class="fp-box" style="margin-bottom:24px;">
                <div class="fp-box-head">
                    <h2>❌ Items fallidos <span id="failed-count"></span></h2>
                </div>
                <div class="fp-box-body">
                    <p style="margin:0 0 16px;color:#64748b;font-size:13px;">
                        Items que fallaron después de 3 intentos. Puedes reintentar o investigar el error.
                    </p>
                    <div id="failed-table-wrap">
                        <div class="fp-box-body is-loading"><div class="fp-spinner"></div></div>
                    </div>
                </div>
            </div>
            
            <!-- Activity Logs -->
            <div class="fp-box">
                <div class="fp-box-head">
                    <h2>📋 Logs de actividad <span id="logs-count"></span></h2>
                </div>
                <div class="fp-box-body">
                    <div class="fp-toolbar" style="margin-bottom:16px;">
                        <select class="fp-filter-select" id="logs-filter">
                            <option value="all">Todos los niveles</option>
                            <option value="info">Info</option>
                            <option value="warning">Warning</option>
                            <option value="error">Error</option>
                            <option value="critical">Critical</option>
                        </select>
                        <button type="button" class="fp-btn" id="btn-refresh-logs" style="margin-left:8px;">🔄 Actualizar</button>
                    </div>
                    <div id="logs-table-wrap">
                        <div class="fp-box-body is-loading"><div class="fp-spinner"></div></div>
                    </div>
                    <div class="fp-pagination" id="logs-pagination"></div>
                </div>
            </div>
        </div>

        <!-- Tab: Settings -->
        <div class="fp-tab-panel" id="panel-settings">
            <form id="fp-settings-form">
                
                <!-- Tree-Nation -->
                <div class="fp-box">
                    <div class="fp-box-head">
                        <h2><svg class="ph-icon" style="width:20px;height:20px;margin-right:8px;vertical-align:middle;" viewBox="0 0 256 256"><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm-8-136v96a8,8,0,0,1-16,0V80a8,8,0,0,1,16,0Zm48,0v96a8,8,0,0,1-16,0V80a8,8,0,0,1,16,0Z" fill="currentColor"/></svg>Tree-Nation API</h2>
                    </div>
                    <div class="fp-box-body">
                        <div class="fp-form-grid">
                            <div class="fp-form-group">
                                <label for="tn_api_token">API Token <small>(Bearer)</small></label>
                                <input type="password" class="fp-input fp-input--mono" id="tn_api_token" name="tn_api_token" 
                                       value="<?php echo esc_attr( $settings['tn_api_token'] ); ?>" 
                                       placeholder="Tu token de Tree-Nation">
                                <p class="fp-hint">Obtén tu token en <a href="https://tree-nation.com/api" target="_blank">tree-nation.com/api</a></p>
                            </div>
                            <div class="fp-form-group">
                                <label for="tn_forest_id">Forest ID</label>
                                <input type="text" class="fp-input" id="tn_forest_id" name="tn_forest_id" 
                                       value="<?php echo esc_attr( $settings['tn_forest_id'] ); ?>" 
                                       placeholder="ID numérico de tu bosque">
                            </div>
                        </div>
                        <div class="fp-form-group">
                            <label for="tn_message">Mensaje del árbol</label>
                            <textarea class="fp-textarea" id="tn_message" name="tn_message" rows="2"
                                      placeholder="Mensaje que verá el cliente en Tree-Nation"><?php echo esc_textarea( $settings['tn_message'] ); ?></textarea>
                        </div>
                        <div class="fp-form-group">
                            <label for="tn_species_id">Especie de árbol</label>
                            <input class="fp-input" type="number" id="tn_species_id" name="tn_species_id" 
                                   value="<?php echo esc_attr( $settings['tn_species_id'] ?? 0 ); ?>"
                                   placeholder="0 = automático (más barato)" min="0">
                            <p class="fp-hint">0 = selección automática de la especie más económica. Usa el diagnóstico para ver las especies disponibles y sus precios.</p>
                        </div>
                        <div class="fp-toggle-row">
                            <div class="fp-toggle <?php echo $settings['tn_sandbox_mode'] ? 'is-active' : ''; ?>" 
                                 id="tn_sandbox_toggle"></div>
                            <input type="hidden" name="tn_sandbox_mode" id="tn_sandbox_mode" 
                                   value="<?php echo $settings['tn_sandbox_mode'] ? '1' : '0'; ?>">
                            <span>Modo Sandbox (testing)</span>
                        </div>
                    </div>
                </div>
                
                <!-- Upmind -->
                <div class="fp-box">
                    <div class="fp-box-head">
                        <h2><svg class="ph-icon" style="width:20px;height:20px;margin-right:8px;vertical-align:middle;" viewBox="0 0 256 256"><path d="M223.68,66.15,135.68,18a15.88,15.88,0,0,0-15.36,0l-88,48.17a16,16,0,0,0-8.32,14v95.64a16,16,0,0,0,8.32,14l88,48.17a15.88,15.88,0,0,0,15.36,0l88-48.17a16,16,0,0,0,8.32-14V80.18A16,16,0,0,0,223.68,66.15ZM128,32l80.34,44L128,120,47.66,76ZM40,90l80,43.78v85.79L40,175.82Zm96,129.57V133.82L216,90v85.82Z" fill="currentColor"/></svg>Upmind API</h2>
                    </div>
                    <div class="fp-box-body">
                        <div class="fp-form-grid">
                            <div class="fp-form-group">
                                <label for="upmind_api_token">API Token</label>
                                <input type="password" class="fp-input fp-input--mono" id="upmind_api_token" name="upmind_api_token" 
                                       value="<?php echo esc_attr( $settings['upmind_api_token'] ); ?>" 
                                       placeholder="Token de Admin API">
                            </div>
                            <div class="fp-form-group">
                                <label for="upmind_api_url">API URL</label>
                                <input type="url" class="fp-input" id="upmind_api_url" name="upmind_api_url" 
                                       value="<?php echo esc_attr( $settings['upmind_api_url'] ); ?>" 
                                       placeholder="https://api.upmind.io">
                            </div>
                        </div>
                        <p class="fp-hint">Necesario para sincronizar datos de renovación y clientes. <a href="#" id="fp-docs-upmind">Ver documentación →</a></p>
                    </div>
                </div>
                
                <!-- Email -->
                <div class="fp-box">
                    <div class="fp-box-head">
                        <h2><svg class="ph-icon" style="width:20px;height:20px;margin-right:8px;vertical-align:middle;" viewBox="0 0 256 256"><path d="M224,48H32a8,8,0,0,0-8,8V192a16,16,0,0,0,16,16H216a16,16,0,0,0,16-16V56A8,8,0,0,0,224,48ZM98.71,128,40,181.81V74.19Zm11.84,10.85,12,11.05a8,8,0,0,0,10.82,0l12-11.05,57.69,53.15H52.86ZM157.29,128,216,74.19V181.81Z" fill="currentColor"/></svg>Email de notificación</h2>
                    </div>
                    <div class="fp-box-body">
                        <div class="fp-form-grid">
                            <div class="fp-form-group">
                                <label for="email_from_name">Nombre del remitente</label>
                                <input type="text" class="fp-input" id="email_from_name" name="email_from_name" 
                                       value="<?php echo esc_attr( $settings['email_from_name'] ); ?>">
                            </div>
                            <div class="fp-form-group">
                                <label for="email_from_email">Email del remitente</label>
                                <input type="email" class="fp-input" id="email_from_email" name="email_from_email" 
                                       value="<?php echo esc_attr( $settings['email_from_email'] ); ?>">
                            </div>
                        </div>
                        <div class="fp-form-group">
                            <label for="email_subject">Asunto del email</label>
                            <input type="text" class="fp-input" id="email_subject" name="email_subject" 
                                   value="<?php echo esc_attr( $settings['email_subject'] ); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Alerts -->
                <div class="fp-box">
                    <div class="fp-box-head">
                        <h2><svg class="ph-icon" style="width:20px;height:20px;margin-right:8px;vertical-align:middle;" viewBox="0 0 256 256"><path d="M236.8,188.09,149.35,36.22h0a24.76,24.76,0,0,0-42.7,0L19.2,188.09a23.51,23.51,0,0,0,0,23.72A24.35,24.35,0,0,0,40.55,224h174.9a24.35,24.35,0,0,0,21.33-12.19A23.51,23.51,0,0,0,236.8,188.09ZM120,104a8,8,0,0,1,16,0v40a8,8,0,0,1-16,0Zm8,88a12,12,0,1,1,12-12A12,12,0,0,1,128,192Z" fill="currentColor"/></svg>Alertas</h2>
                    </div>
                    <div class="fp-box-body">
                        <div class="fp-form-grid">
                            <div class="fp-form-group">
                                <label for="alert_credits_min">Alerta cuando créditos &lt;</label>
                                <input type="number" class="fp-input" id="alert_credits_min" name="alert_credits_min" 
                                       value="<?php echo esc_attr( $settings['alert_credits_min'] ); ?>" min="1">
                            </div>
                            <div class="fp-form-group">
                                <label for="alert_email">Email para alertas</label>
                                <input type="email" class="fp-input" id="alert_email" name="alert_email" 
                                       value="<?php echo esc_attr( $settings['alert_email'] ); ?>" 
                                       placeholder="admin@tudominio.com">
                            </div>
                            <div class="fp-form-group">
                                <label for="summary_frequency">Frecuencia del resumen</label>
                                <select class="fp-input" id="summary_frequency" name="summary_frequency">
                                    <?php $sf = $settings['summary_frequency'] ?? 'weekly'; ?>
                                    <option value="disabled" <?php selected( $sf, 'disabled' ); ?>>Desactivado</option>
                                    <option value="daily"    <?php selected( $sf, 'daily' ); ?>>Diario</option>
                                    <option value="weekly"   <?php selected( $sf, 'weekly' ); ?>>Semanal (recomendado)</option>
                                </select>
                                <small style="color:#64748b;font-size:12px;">Email HTML con árboles plantados, fallidas, errores y créditos del periodo.</small>
                            </div>
                            <div class="fp-form-group">
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                    <input type="checkbox" id="enable_logging" name="enable_logging"
                                           <?php checked( ! empty( $settings['enable_logging'] ) ); ?>>
                                    <span>Registrar actividad informativa</span>
                                </label>
                                <small style="color:#64748b;font-size:12px;">Si lo desactivas, sólo se guardarán errores/críticos en la tabla de logs.</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Security / Safety Settings -->
                <div class="fp-box">
                    <div class="fp-box-head">
                        <h2><svg class="ph-icon" style="width:20px;height:20px;margin-right:8px;vertical-align:middle;" viewBox="0 0 256 256"><path d="M208,40H48A16,16,0,0,0,32,56v58.78c0,89.61,75.82,119.34,91,124.39a15.53,15.53,0,0,0,10,0c15.2-5.05,91-34.78,91-124.39V56A16,16,0,0,0,208,40Zm0,74.79c0,78.42-66.35,104.62-80,109.18-13.53-4.51-80-30.69-80-109.18V56H208ZM82.34,141.66a8,8,0,0,1,11.32-11.32L112,148.69l50.34-50.35a8,8,0,0,1,11.32,11.32l-56,56a8,8,0,0,1-11.32,0Z" fill="currentColor"/></svg>Seguridad</h2>
                    </div>
                    <div class="fp-box-body">
                        <div class="fp-form-grid">
                            <div class="fp-form-group">
                                <label for="max_trees_per_day">Máximo árboles/día</label>
                                <input type="number" class="fp-input" id="max_trees_per_day" name="max_trees_per_day" 
                                       value="<?php echo esc_attr( $settings['max_trees_per_day'] ?? 20 ); ?>" min="1" max="100">
                                <small style="color:#64748b;font-size:12px;">Límite de seguridad para evitar gastos excesivos</small>
                            </div>
                            <div class="fp-form-group">
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                    <input type="checkbox" id="dry_run_mode" name="dry_run_mode" 
                                           <?php checked( ! empty( $settings['dry_run_mode'] ) ); ?>>
                                    <span>Modo simulación (dry-run)</span>
                                </label>
                                <small style="color:#64748b;font-size:12px;">Simula plantaciones sin llamar a la API ni gastar créditos</small>
                            </div>
                        </div>
                        <div style="margin-top:16px;padding:12px;background:#fef3c7;border-radius:8px;font-size:13px;color:#92400e;">
                            <strong>⚠️ Protecciones activas:</strong>
                            <ul style="margin:8px 0 0;padding-left:20px;">
                                <li>Verificación de créditos antes de plantar</li>
                                <li>Prevención de duplicados por dominio+año</li>
                                <li>Límite diario configurable</li>
                                <li>Lock de base de datos anti-concurrencia</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Check Credits -->
                <div class="fp-box">
                    <div class="fp-box-head">
                        <h2>💰 Créditos Tree-Nation</h2>
                    </div>
                    <div class="fp-box-body">
                        <div id="credits-info" style="padding:16px;background:#f1f5f9;border-radius:8px;margin-bottom:16px;">
                            <p style="margin:0;color:#64748b;">Haz clic en el botón para verificar créditos disponibles...</p>
                        </div>
                        <button type="button" class="fp-btn" id="btn-check-credits" style="background:#10b981;color:#fff;">
                            🔍 Verificar créditos
                        </button>
                    </div>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="submit" class="fp-btn fp-btn--primary">
                        <span>💾</span> Guardar configuración
                    </button>
                </div>
            </form>
        </div>

        <!-- Toast notification -->
        <div class="fp-toast" id="fp-toast"></div>

        <!-- Edit Domain Modal -->
        <div id="fp-edit-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:99998; align-items:center; justify-content:center;">
            <div style="background:#fff; border-radius:16px; padding:32px; max-width:500px; width:90%;">
                <h3 style="margin:0 0 20px;"><svg style="width:24px;height:24px;vertical-align:middle;margin-right:8px;color:#3b82f6;" viewBox="0 0 256 256"><path d="M227.31,73.37,182.63,28.68a16,16,0,0,0-22.63,0L36.69,152A15.86,15.86,0,0,0,32,163.31V208a16,16,0,0,0,16,16H92.69A15.86,15.86,0,0,0,104,219.31L227.31,96a16,16,0,0,0,0-22.63ZM92.69,208H48V163.31l88-88L180.69,120ZM192,108.68,147.31,64l24-24L216,84.68Z" fill="currentColor"/></svg>Editar dominio</h3>
                <input type="hidden" id="edit-domain-id">
                <div class="fp-form-group">
                    <label>Dominio</label>
                    <input type="text" class="fp-input" id="edit-domain-name" readonly style="background:#f3f4f6;">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div class="fp-form-group">
                        <label>Nombre del cliente</label>
                        <input type="text" class="fp-input" id="edit-client-name" placeholder="Juan Pérez">
                    </div>
                    <div class="fp-form-group">
                        <label>Email del cliente</label>
                        <input type="email" class="fp-input" id="edit-client-email" placeholder="cliente@email.com">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div class="fp-form-group">
                        <label>Producto</label>
                        <select class="fp-select" id="edit-product" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px;">
                            <option value="">— Sin asignar —</option>
                            <option value="roble">Roble</option>
                            <option value="cedro">Cedro</option>
                            <option value="sauce">Sauce</option>
                        </select>
                    </div>
                    <div class="fp-form-group">
                        <label>Ciclo de facturación</label>
                        <select class="fp-select" id="edit-cycle" style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px;">
                            <option value="">— Sin asignar —</option>
                            <option value="monthly">Mensual</option>
                            <option value="annual">Anual</option>
                        </select>
                    </div>
                </div>
                <div class="fp-form-group">
                    <label>Fecha próxima renovación</label>
                    <input type="date" class="fp-input" id="edit-renewal">
                </div>
                <div class="fp-hint" style="margin:16px 0; padding:12px; background:#fef3c7; border-radius:8px; color:#92400e;">
                    <svg style="width:16px;height:16px;vertical-align:middle;margin-right:6px;" viewBox="0 0 256 256"><path d="M236.8,188.09,149.35,36.22h0a24.76,24.76,0,0,0-42.7,0L19.2,188.09a23.51,23.51,0,0,0,0,23.72A24.35,24.35,0,0,0,40.55,224h174.9a24.35,24.35,0,0,0,21.33-12.19A23.51,23.51,0,0,0,236.8,188.09ZM222.93,203.8a8.5,8.5,0,0,1-7.48,4.2H40.55a8.5,8.5,0,0,1-7.48-4.2,7.59,7.59,0,0,1,0-7.72L120.52,44.21a8.75,8.75,0,0,1,15,0l87.45,151.87A7.59,7.59,0,0,1,222.93,203.8ZM120,144V104a8,8,0,0,1,16,0v40a8,8,0,0,1-16,0Zm20,36a12,12,0,1,1-12-12A12,12,0,0,1,140,180Z" fill="currentColor"/></svg>
                    Solo se plantarán árboles para productos <strong>roble</strong>, <strong>cedro</strong> o <strong>sauce</strong> con ciclo <strong>anual</strong>.
                </div>
                <div style="display:flex; gap:12px; margin-top:24px;">
                    <button class="fp-btn fp-btn--ghost" id="fp-edit-cancel" style="flex:1;">Cancelar</button>
                    <button class="fp-btn fp-btn--primary" id="fp-edit-save" style="flex:1;">Guardar cambios</button>
                </div>
            </div>
        </div>

        <!-- Test Plant Modal (simple) -->
        <div id="fp-test-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:99998; align-items:center; justify-content:center;">
            <div style="background:#fff; border-radius:16px; padding:32px; max-width:400px; width:90%;">
                <h3 style="margin:0 0 20px;"><svg style="width:24px;height:24px;vertical-align:middle;margin-right:8px;color:#16a34a;" viewBox="0 0 256 256"><path d="M223.45,40.07a8,8,0,0,0-7.52-7.52C139.8,28.08,78.82,50,52.82,76a87.09,87.09,0,0,0-23.11,43.3,8,8,0,0,0,4.2,8.51,8.2,8.2,0,0,0,3.54.83,8,8,0,0,0,5.6-2.31l71-71C114.05,55.33,187.93,46.87,214.64,44.53,219.38,46.9,221.81,54.36,220.33,62.8c-2.85,16.14-18.26,38.69-44.15,64.58L132,171.56V216a8,8,0,0,0,8,8,8.25,8.25,0,0,0,2-.25,87.67,87.67,0,0,0,51.11-30.48c21.57-25.9,25.93-55.68,12.85-88.65a8,8,0,1,0-14.74,6.26c10.09,25.37,6.93,48.77-9.41,69.36A72.15,72.15,0,0,1,148,200v-16.69l51.32-51.31c25.89-25.9,44.74-54.06,52.91-79A91.1,91.1,0,0,0,223.45,40.07ZM104,144H40a8,8,0,0,0-8,8v8a72,72,0,0,0,72,72h8a8,8,0,0,0,8-8V160A16,16,0,0,0,104,144Z" fill="currentColor"/></svg>Test de plantación</h3>
                <div class="fp-form-group">
                    <label>Nombre del destinatario</label>
                    <input type="text" class="fp-input" id="test-name" value="Test Replanta">
                </div>
                <div class="fp-form-group">
                    <label>Email del destinatario</label>
                    <input type="email" class="fp-input" id="test-email" placeholder="tu@email.com">
                </div>
                <div style="display:flex; gap:12px; margin-top:24px;">
                    <button class="fp-btn fp-btn--ghost" id="fp-test-cancel" style="flex:1;">Cancelar</button>
                    <button class="fp-btn fp-btn--primary" id="fp-test-confirm" style="flex:1;">Plantar árbol</button>
                </div>
                <p class="fp-hint" style="margin-top:16px; text-align:center;">Esto consumirá 1 crédito de tu cuenta Tree-Nation</p>
            </div>
        </div>

        <script>
        (function($) {
            'use strict';
            
            const NONCE = '<?php echo esc_js( $nonce ); ?>';
            const AJAXURL = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
            
            // ═══════════════════════════════════════════════════
            // STATE
            // ═══════════════════════════════════════════════════
            let domainsPage = 1;
            let historyPage = 1;
            let searchTimeout = null;
            
            // ═══════════════════════════════════════════════════
            // INIT
            // ═══════════════════════════════════════════════════
            $(document).ready(function() {
                loadStats();
                loadForecast();
                loadDomains();
                initTabs();
                initForm();
                initToggles();
                initTestModal();
                initEditModal();
                initHistoryActions();
            });
            
            // ═══════════════════════════════════════════════════
            // TABS
            // ═══════════════════════════════════════════════════
            function initTabs() {
                $('.fp-tab').on('click', function() {
                    const tab = $(this).data('tab');
                    $('.fp-tab').removeClass('is-active');
                    $(this).addClass('is-active');
                    $('.fp-tab-panel').removeClass('is-active');
                    $('#panel-' + tab).addClass('is-active');
                    
                    // Load data on first visit
                    if (tab === 'history' && $('#history-table-wrap table').length === 0) {
                        loadHistory();
                    }
                });
            }
            
            // ═══════════════════════════════════════════════════
            // STATS
            // ═══════════════════════════════════════════════════
            function loadStats() {
                $.post(AJAXURL, {
                    action: 'dr_forest_get_stats',
                    _nonce: NONCE
                }, function(res) {
                    if (res.success) {
                        const d = res.data;
                        $('#st-trees').text(d.trees_planted).removeClass('is-loading');
                        $('#st-co2').text(parseFloat(d.co2_total).toFixed(1)).removeClass('is-loading');
                        $('#st-forest').text(d.forest_enabled + '/' + d.eligible).removeClass('is-loading');
                        $('#st-queue').text(d.queue_pending).removeClass('is-loading');
                    }
                });
            }

            // ═══════════════════════════════════════════════════
            // FORECAST
            // ═══════════════════════════════════════════════════
            function loadForecast() {
                $.post(AJAXURL, {
                    action: 'dr_forest_get_forecast',
                    _nonce: NONCE
                }, function(res) {
                    if (!res.success) return;
                    const d = res.data;
                    $('#fc-30').text(d.next_30_days);
                    $('#fc-60').text(d.next_60_days);
                    $('#fc-90').text(d.next_90_days);
                    $('#fc-avg').text(d.avg_per_month + ' /mes');
                    if (d.runway_days !== null) {
                        const months = (d.runway_days / 30).toFixed(1);
                        $('#fc-runway').text('Runway: ~' + d.runway_days + ' días (' + months + ' meses)');
                    } else {
                        $('#fc-runway').text('Runway: sin datos suficientes');
                    }
                    drawForecastChart(d.monthly || []);
                });
            }

            function drawForecastChart(series) {
                const cv = document.getElementById('fc-chart');
                if (!cv || !series.length) return;
                const ctx = cv.getContext('2d');
                const w = cv.width = cv.offsetWidth, h = cv.height;
                ctx.clearRect(0, 0, w, h);
                const max = Math.max.apply(null, series.map(function(s){return parseInt(s.c,10);})) || 1;
                const bw = w / series.length;
                series.forEach(function(s, i) {
                    const c = parseInt(s.c, 10);
                    const bh = (c / max) * (h - 24);
                    ctx.fillStyle = '#16a34a';
                    ctx.fillRect(i * bw + 4, h - bh - 16, bw - 8, bh);
                    ctx.fillStyle = '#64748b';
                    ctx.font = '10px sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText(s.ym.slice(5), i * bw + bw/2, h - 4);
                    ctx.fillStyle = '#0f172a';
                    ctx.fillText(c, i * bw + bw/2, h - bh - 20);
                });
            }

            function initHistoryActions() {
                $(document).on('click', '.fp-resend-email', function() {
                    const $btn = $(this);
                    const treeId = $btn.data('tree-id');
                    if (!treeId) return;
                    if (!confirm('¿Reenviar email para este árbol?')) return;
                    $btn.prop('disabled', true).html('<span class="dashicons dashicons-update fp-spin"></span> Enviando…');
                    $.post(AJAXURL, {
                        action: 'dr_forest_resend_email',
                        _nonce: NONCE,
                        tree_id: treeId
                    }, function(res) {
                        if (res.success) {
                            $btn.html('<span class="dashicons dashicons-yes"></span> Enviado').css({background:'#16a34a',color:'#fff'});
                            setTimeout(function(){ loadHistory(); }, 800);
                        } else {
                            alert(res.data || 'Error al reenviar');
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-email-alt"></span> Reenviar');
                        }
                    });
                });
            }
            
            // ═══════════════════════════════════════════════════
            // DOMAINS
            // ═══════════════════════════════════════════════════
            function loadDomains(page = 1) {
                domainsPage = page;
                const search = $('#domains-search').val();
                const filter = $('#domains-filter').val();
                
                $('#domains-table-wrap').html('<div style="text-align:center;padding:40px;"><div class="fp-spinner" style="margin:0 auto;"></div></div>');
                
                $.post(AJAXURL, {
                    action: 'dr_forest_get_domains',
                    _nonce: NONCE,
                    page: page,
                    search: search,
                    filter: filter
                }, function(res) {
                    if (res.success) {
                        renderDomainsTable(res.data);
                    }
                });
            }
            
            function renderDomainsTable(data) {
                if (!data.domains || data.domains.length === 0) {
                    $('#domains-table-wrap').html(`
                        <div class="fp-empty">
                            <div class="fp-empty-icon"><svg style="width:48px;height:48px;color:#9ca3af;" viewBox="0 0 256 256"><path d="M216,40H40A16,16,0,0,0,24,56V200a16,16,0,0,0,16,16H216a16,16,0,0,0,16-16V56A16,16,0,0,0,216,40Zm0,160H40V56H216V200Zm-96-88a12,12,0,1,1,12-12A12,12,0,0,1,120,112Zm48,0a12,12,0,1,1,12-12A12,12,0,0,1,168,112Z" fill="currentColor"/></svg></div>
                            <h3>No hay dominios</h3>
                            <p>Carga dominios desde cPanel o añade manualmente</p>
                        </div>
                    `);
                    $('#domains-count').text('');
                    $('#domains-pagination').html('');
                    return;
                }
                
                $('#domains-count').text('(' + data.total + ')');
                
                let html = `<table class="fp-table">
                    <thead>
                        <tr>
                            <th>Dominio</th>
                            <th>Cliente</th>
                            <th>Producto</th>
                            <th>Ciclo</th>
                            <th>Renovación</th>
                            <th><svg style="width:16px;height:16px;" viewBox="0 0 256 256"><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm-8-136v96a8,8,0,0,1-16,0V80a8,8,0,0,1,16,0Zm48,0v96a8,8,0,0,1-16,0V80a8,8,0,0,1,16,0Z" fill="currentColor"/></svg></th>
                            <th style="text-align:center;">Forest</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>`;
                
                data.domains.forEach(d => {
                    const isOn = d.forest_enabled == 1;
                    const isEligible = ['roble','cedro','sauce'].includes((d.upmind_product_slug||'').toLowerCase());
                    const isAnnual = ['annual','yearly','annually'].includes((d.billing_cycle||'').toLowerCase());
                    const canEnable = isEligible && isAnnual;
                    
                    let productBadge = '';
                    if (d.upmind_product_slug) {
                        const colors = { roble: 'green', cedro: 'teal', sauce: 'amber' };
                        const c = colors[d.upmind_product_slug.toLowerCase()] || 'gray';
                        productBadge = `<span class="fp-badge fp-badge--${c}">${d.upmind_product_slug}</span>`;
                    } else {
                        productBadge = '<span class="fp-badge fp-badge--gray">—</span>';
                    }
                    
                    html += `<tr>
                        <td>
                            <div class="fp-domain-name">${escHtml(d.domain)}</div>
                            ${d.server ? `<div class="fp-domain-sub">Servidor: ${d.server.toUpperCase()}</div>` : ''}
                        </td>
                        <td>
                            ${d.upmind_client_name ? `<span>${escHtml(d.upmind_client_name)}</span>` : '<span style="color:#9ca3af;">—</span>'}
                            ${d.upmind_client_email ? `<div class="fp-domain-sub">${escHtml(d.upmind_client_email)}</div>` : ''}
                        </td>
                        <td>${productBadge}</td>
                        <td>${d.billing_cycle || '—'}</td>
                        <td>${d.next_renewal_date || '—'}</td>
                        <td><span class="fp-trees-count">${d.trees_planted || 0}</span></td>
                        <td style="text-align:center;">
                            <button class="fp-switch ${isOn ? 'is-on' : ''}"
                                    data-id="${d.id}"
                                    data-enabled="${isOn ? '1' : '0'}">
                            </button>
                        </td>
                        <td>
                            <button class="fp-btn fp-btn--ghost fp-btn--sm fp-edit-btn" 
                                    data-id="${d.id}"
                                    data-domain="${escHtml(d.domain)}"
                                    data-client="${escHtml(d.upmind_client_name || '')}"
                                    data-email="${escHtml(d.upmind_client_email || '')}"
                                    data-product="${escHtml(d.upmind_product_slug || '')}"
                                    data-cycle="${escHtml(d.billing_cycle || '')}"
                                    data-renewal="${escHtml(d.next_renewal_date || '')}"
                                    title="Editar">
                                <svg style="width:14px;height:14px;" viewBox="0 0 256 256"><path d="M227.31,73.37,182.63,28.68a16,16,0,0,0-22.63,0L36.69,152A15.86,15.86,0,0,0,32,163.31V208a16,16,0,0,0,16,16H92.69A15.86,15.86,0,0,0,104,219.31L227.31,96a16,16,0,0,0,0-22.63ZM92.69,208H48V163.31l88-88L180.69,120ZM192,108.68,147.31,64l24-24L216,84.68Z" fill="currentColor"/></svg>
                            </button>
                        </td>
                    </tr>`;
                });
                
                html += '</tbody></table>';
                $('#domains-table-wrap').html(html);
                
                // Pagination
                renderPagination('#domains-pagination', data, loadDomains);
                
                // Toggle handlers
                $('#domains-table-wrap .fp-switch').on('click', function() {
                    const $btn = $(this);
                    if ($btn.prop('disabled')) return;
                    
                    const id = $btn.data('id');
                    const current = $btn.data('enabled') == '1';
                    const newVal = current ? 0 : 1;
                    
                    $btn.prop('disabled', true);
                    
                    $.post(AJAXURL, {
                        action: 'dr_forest_toggle_domain',
                        _nonce: NONCE,
                        domain_id: id,
                        enabled: newVal
                    }, function(res) {
                        $btn.prop('disabled', false);
                        if (res.success) {
                            $btn.toggleClass('is-on', newVal === 1);
                            $btn.data('enabled', newVal);
                            loadStats();
                            toast(newVal ? 'Forest habilitado' : 'Forest deshabilitado', 'success');
                        } else {
                            toast(res.data || 'Error', 'error');
                        }
                    });
                });
            }
            
            // Search/filter handlers
            $('#domains-search').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => loadDomains(1), 300);
            });
            $('#domains-filter').on('change', () => loadDomains(1));
            
            // ═══════════════════════════════════════════════════
            // HISTORY
            // ═══════════════════════════════════════════════════
            function loadHistory(page = 1) {
                historyPage = page;
                
                $('#history-table-wrap').html('<div style="text-align:center;padding:40px;"><div class="fp-spinner" style="margin:0 auto;"></div></div>');
                
                $.post(AJAXURL, {
                    action: 'dr_forest_get_history',
                    _nonce: NONCE,
                    page: page
                }, function(res) {
                    if (res.success) {
                        renderHistoryTable(res.data);
                    }
                });
            }
            
            function renderHistoryTable(data) {
                if (!data.trees || data.trees.length === 0) {
                    $('#history-table-wrap').html(`
                        <div class="fp-empty">
                            <div class="fp-empty-icon"><svg style="width:48px;height:48px;color:#9ca3af;" viewBox="0 0 256 256"><path d="M223.45,40.07a8,8,0,0,0-7.52-7.52C139.8,28.08,78.82,50,52.82,76a87.09,87.09,0,0,0-23.11,43.3,8,8,0,0,0,4.2,8.51,8.2,8.2,0,0,0,3.54.83,8,8,0,0,0,5.6-2.31l71-71C114.05,55.33,187.93,46.87,214.64,44.53,219.38,46.9,221.81,54.36,220.33,62.8c-2.85,16.14-18.26,38.69-44.15,64.58L132,171.56V216a8,8,0,0,0,8,8,8.25,8.25,0,0,0,2-.25,87.67,87.67,0,0,0,51.11-30.48c21.57-25.9,25.93-55.68,12.85-88.65a8,8,0,1,0-14.74,6.26c10.09,25.37,6.93,48.77-9.41,69.36A72.15,72.15,0,0,1,148,200v-16.69l51.32-51.31c25.89-25.9,44.74-54.06,52.91-79A91.1,91.1,0,0,0,223.45,40.07ZM104,144H40a8,8,0,0,0-8,8v8a72,72,0,0,0,72,72h8a8,8,0,0,0,8-8V160A16,16,0,0,0,104,144Z" fill="currentColor"/></svg></div>
                            <h3>Aún no hay árboles</h3>
                            <p>Cuando se planten los primeros árboles aparecerán aquí</p>
                        </div>
                    `);
                    $('#history-count').text('');
                    $('#history-pagination').html('');
                    return;
                }
                
                $('#history-count').text('(' + data.total + ')');
                
                let html = `<table class="fp-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Dominio</th>
                            <th>Especie</th>
                            <th>Proyecto</th>
                            <th>CO₂</th>
                            <th>Email</th>
                            <th>Links</th>
                        </tr>
                    </thead>
                    <tbody>`;
                
                data.trees.forEach(t => {
                    const emailIcon = {
                        sent: '<span class="fp-email-status is-sent"><svg style="width:14px;height:14px;vertical-align:middle;" viewBox="0 0 256 256"><path d="M229.66,77.66l-128,128a8,8,0,0,1-11.32,0l-56-56a8,8,0,0,1,11.32-11.32L96,188.69,218.34,66.34a8,8,0,0,1,11.32,11.32Z" fill="currentColor"/></svg> Enviado</span>',
                        pending: '<span class="fp-email-status is-pending"><svg style="width:14px;height:14px;vertical-align:middle;" viewBox="0 0 256 256"><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm64-88a8,8,0,0,1-8,8H128a8,8,0,0,1-8-8V72a8,8,0,0,1,16,0v48h48A8,8,0,0,1,192,128Z" fill="currentColor"/></svg> Pendiente</span>',
                        failed: '<span class="fp-email-status is-failed"><svg style="width:14px;height:14px;vertical-align:middle;" viewBox="0 0 256 256"><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z" fill="currentColor"/></svg> Falló</span>'
                    };
                    
                    html += `<tr>
                        <td>${t.planted_at ? t.planted_at.split(' ')[0] : '—'}</td>
                        <td><span class="fp-domain-name">${escHtml(t.domain)}</span></td>
                        <td>
                            <div class="fp-tree-species">
                                <span class="fp-tree-emoji"><svg style="width:20px;height:20px;color:#16a34a;" viewBox="0 0 256 256"><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm-8-136v96a8,8,0,0,1-16,0V80a8,8,0,0,1,16,0Zm48,0v96a8,8,0,0,1-16,0V80a8,8,0,0,1,16,0Z" fill="currentColor"/></svg></span>
                                <div class="fp-tree-info">
                                    <div class="fp-tree-name">${escHtml(t.species_name || '—')}</div>
                                    <div class="fp-tree-project">${escHtml(t.country || '')}</div>
                                </div>
                            </div>
                        </td>
                        <td>${escHtml(t.project_name || '—')}</td>
                        <td><span class="fp-co2-val">${t.co2_lifetime || '—'} kg</span></td>
                        <td>${emailIcon[t.email_status] || '—'}</td>
                        <td>
                            ${t.collect_url ? `<a href="${t.collect_url}" target="_blank" class="fp-btn fp-btn--ghost fp-btn--sm">Ver</a>` : ''}
                            ${t.certificate_url ? `<a href="${t.certificate_url}" target="_blank" class="fp-btn fp-btn--ghost fp-btn--sm">Cert</a>` : ''}
                            ${t.client_email ? `<button class="fp-btn fp-btn--ghost fp-btn--sm fp-resend-email" data-tree-id="${t.id}" title="Reenviar email al cliente"><span class="dashicons dashicons-email-alt"></span> Reenviar</button>` : ''}
                        </td>
                    </tr>`;
                });
                
                html += '</tbody></table>';
                $('#history-table-wrap').html(html);
                
                renderPagination('#history-pagination', data, loadHistory);
            }
            
            // ═══════════════════════════════════════════════════
            // PAGINATION
            // ═══════════════════════════════════════════════════
            function renderPagination(selector, data, loadFn) {
                if (data.pages <= 1) {
                    $(selector).html('');
                    return;
                }
                
                let html = '';
                html += `<button class="fp-page-btn" ${data.page <= 1 ? 'disabled' : ''} data-page="${data.page - 1}">←</button>`;
                html += `<span class="fp-page-info">Página ${data.page} de ${data.pages}</span>`;
                html += `<button class="fp-page-btn" ${data.page >= data.pages ? 'disabled' : ''} data-page="${data.page + 1}">→</button>`;
                
                $(selector).html(html);
                $(selector + ' .fp-page-btn').on('click', function() {
                    const p = $(this).data('page');
                    if (p) loadFn(p);
                });
            }
            
            // ═══════════════════════════════════════════════════
            // SETTINGS FORM
            // ═══════════════════════════════════════════════════
            function initForm() {
                $('#fp-settings-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    const $btn = $(this).find('[type="submit"]');
                    $btn.prop('disabled', true).html('<span class="fp-spinner" style="width:16px;height:16px;border-width:2px;"></span> Guardando...');
                    
                    $.post(AJAXURL, {
                        action: 'dr_forest_save_settings',
                        _nonce: NONCE,
                        ...getFormData()
                    }, function(res) {
                        $btn.prop('disabled', false).html('<span>💾</span> Guardar configuración');
                        if (res.success) {
                            toast('Configuración guardada', 'success');
                        } else {
                            toast(res.data || 'Error al guardar', 'error');
                        }
                    });
                });
            }
            
            function getFormData() {
                return {
                    tn_api_token: $('#tn_api_token').val(),
                    tn_forest_id: $('#tn_forest_id').val(),
                    tn_message: $('#tn_message').val(),
                    tn_species_id: $('#tn_species_id').val(),
                    tn_sandbox_mode: $('#tn_sandbox_mode').val(),
                    upmind_api_token: $('#upmind_api_token').val(),
                    upmind_api_url: $('#upmind_api_url').val(),
                    email_from_name: $('#email_from_name').val(),
                    email_from_email: $('#email_from_email').val(),
                    email_subject: $('#email_subject').val(),
                    alert_credits_min: $('#alert_credits_min').val(),
                    alert_email: $('#alert_email').val(),
                    summary_frequency: $('#summary_frequency').val(),
                    enable_logging: $('#enable_logging').is(':checked') ? '1' : '',
                    max_trees_per_day: $('#max_trees_per_day').val(),
                    dry_run_mode: $('#dry_run_mode').is(':checked') ? '1' : ''
                };
            }
            
            // ═══════════════════════════════════════════════════
            // CHECK CREDITS
            // ═══════════════════════════════════════════════════
            $('#btn-check-credits').on('click', function() {
                const $btn = $(this);
                const $info = $('#credits-info');
                
                $btn.prop('disabled', true).html('<span class="fp-spinner" style="width:14px;height:14px;border-width:2px;display:inline-block;vertical-align:middle;"></span> Verificando...');
                
                $.post(AJAXURL, {
                    action: 'dr_forest_check_credits',
                    _nonce: NONCE
                }, function(res) {
                    $btn.prop('disabled', false).html('🔍 Verificar créditos');
                    
                    if (res.success) {
                        const d = res.data;
                        const color = d.credits < 10 ? '#ef4444' : (d.credits < 50 ? '#f59e0b' : '#10b981');
                        $info.html(`
                            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;text-align:center;">
                                <div>
                                    <div style="font-size:32px;font-weight:700;color:${color};">${d.credits}</div>
                                    <div style="font-size:12px;color:#64748b;">Créditos disponibles</div>
                                </div>
                                <div>
                                    <div style="font-size:32px;font-weight:700;color:#3b82f6;">${d.trees_planted}</div>
                                    <div style="font-size:12px;color:#64748b;">Árboles plantados</div>
                                </div>
                                <div>
                                    <div style="font-size:32px;font-weight:700;color:#10b981;">${d.co2_tons.toFixed(2)}</div>
                                    <div style="font-size:12px;color:#64748b;">Toneladas CO₂</div>
                                </div>
                            </div>
                            <div style="margin-top:12px;font-size:12px;color:#64748b;text-align:center;">
                                Bosque: ${d.forest_name || 'N/A'}
                            </div>
                        `);
                    } else {
                        $info.html(`<p style="color:#ef4444;margin:0;">❌ ${res.data || 'Error al verificar'}</p>`);
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).html('🔍 Verificar créditos');
                    $info.html(`<p style="color:#ef4444;margin:0;">❌ Error de conexión</p>`);
                });
            });
            
            // ═══════════════════════════════════════════════════
            // LOGS & FAILED ITEMS
            // ═══════════════════════════════════════════════════
            function loadLogs(page = 1) {
                const level = $('#logs-filter').val() || 'all';
                
                $.post(AJAXURL, {
                    action: 'dr_forest_get_logs',
                    _nonce: NONCE,
                    page: page,
                    level: level
                }, function(res) {
                    if (!res.success) {
                        $('#logs-table-wrap').html('<p style="color:#ef4444;">Error al cargar logs</p>');
                        return;
                    }
                    
                    const logs = res.data.logs || [];
                    $('#logs-count').text(`(${res.data.total})`);
                    
                    if (logs.length === 0) {
                        $('#logs-table-wrap').html('<p style="color:#64748b;">No hay logs registrados</p>');
                        return;
                    }
                    
                    let html = '<table class="fp-table"><thead><tr><th>Fecha</th><th>Nivel</th><th>Mensaje</th><th>Dominio</th></tr></thead><tbody>';
                    
                    logs.forEach(function(log) {
                        const levelColors = {
                            'info': '#3b82f6',
                            'warning': '#f59e0b', 
                            'error': '#ef4444',
                            'critical': '#dc2626'
                        };
                        const levelColor = levelColors[log.level] || '#64748b';
                        
                        html += `<tr>
                            <td style="font-size:12px;color:#64748b;white-space:nowrap;">${log.created_at}</td>
                            <td><span style="background:${levelColor};color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;text-transform:uppercase;">${log.level}</span></td>
                            <td style="font-size:13px;">${esc(log.message)}</td>
                            <td style="font-size:12px;color:#64748b;">${log.domain_id || '—'}</td>
                        </tr>`;
                    });
                    
                    html += '</tbody></table>';
                    $('#logs-table-wrap').html(html);
                    
                    renderPagination('#logs-pagination', res.data, loadLogs);
                });
            }
            
            function loadFailed() {
                $.post(AJAXURL, {
                    action: 'dr_forest_get_failed',
                    _nonce: NONCE
                }, function(res) {
                    if (!res.success) {
                        $('#failed-table-wrap').html('<p style="color:#ef4444;">Error al cargar items fallidos</p>');
                        return;
                    }
                    
                    const failed = res.data.failed || [];
                    $('#failed-count').text(`(${failed.length})`);
                    
                    if (failed.length === 0) {
                        $('#failed-table-wrap').html('<p style="color:#10b981;">✓ No hay items fallidos</p>');
                        return;
                    }
                    
                    let html = '<table class="fp-table"><thead><tr><th>Dominio</th><th>Email</th><th>Error</th><th>Intentos</th><th>Fecha</th><th>Acción</th></tr></thead><tbody>';
                    
                    failed.forEach(function(item) {
                        html += `<tr>
                            <td><strong>${esc(item.domain)}</strong></td>
                            <td style="font-size:13px;">${esc(item.client_email || '—')}</td>
                            <td style="font-size:12px;color:#ef4444;max-width:200px;overflow:hidden;text-overflow:ellipsis;" title="${esc(item.last_error)}">${esc(item.last_error || 'Sin error')}</td>
                            <td style="text-align:center;">${item.attempts}</td>
                            <td style="font-size:12px;color:#64748b;">${item.created_at}</td>
                            <td>
                                <button class="fp-btn fp-btn--sm btn-retry-failed" data-id="${item.id}" style="background:#f59e0b;color:#fff;">
                                    🔄 Reintentar
                                </button>
                            </td>
                        </tr>`;
                    });
                    
                    html += '</tbody></table>';
                    $('#failed-table-wrap').html(html);
                    
                    // Bind retry buttons
                    $('.btn-retry-failed').on('click', function() {
                        const $btn = $(this);
                        const queueId = $btn.data('id');
                        
                        $btn.prop('disabled', true).text('⏳...');
                        
                        $.post(AJAXURL, {
                            action: 'dr_forest_retry_failed',
                            _nonce: NONCE,
                            queue_id: queueId
                        }, function(res) {
                            if (res.success) {
                                toast('Item reintentado', 'success');
                                loadFailed();
                            } else {
                                toast(res.data || 'Error', 'error');
                                $btn.prop('disabled', false).text('🔄 Reintentar');
                            }
                        });
                    });
                });
            }
            
            // Init logs/failed when tab is shown
            $('.fp-tab[data-tab="logs"]').on('click', function() {
                loadLogs();
                loadFailed();
            });
            
            $('#logs-filter').on('change', function() {
                loadLogs(1);
            });
            
            $('#btn-refresh-logs').on('click', function() {
                loadLogs();
                loadFailed();
            });
            
            // ═══════════════════════════════════════════════════
            // TOGGLES
            // ═══════════════════════════════════════════════════
            function initToggles() {
                $('#tn_sandbox_toggle').on('click', function() {
                    const $t = $(this);
                    const isActive = $t.hasClass('is-active');
                    $t.toggleClass('is-active', !isActive);
                    $('#tn_sandbox_mode').val(isActive ? '0' : '1');
                });
            }
            
            // ═══════════════════════════════════════════════════
            // SYNC UPMIND
            // ═══════════════════════════════════════════════════
            $('#fp-sync-upmind').on('click', function() {
                const $btn = $(this);
                $btn.prop('disabled', true).html('<span class="fp-spinner" style="width:14px;height:14px;border-width:2px;display:inline-block;vertical-align:middle;"></span> Sincronizando...');
                
                $.post(AJAXURL, {
                    action: 'dr_forest_sync_upmind',
                    _nonce: NONCE
                }, function(res) {
                    $btn.prop('disabled', false).html('<span>🔄</span> Sync Upmind');
                    if (res.success) {
                        toast(res.data.message || 'Sincronización completada', 'success');
                        loadDomains();
                        loadStats();
                    } else {
                        toast(res.data || 'Error de sincronización', 'error');
                    }
                });
            });
            
            // ═══════════════════════════════════════════════════
            // TEST PLANT MODAL
            // ═══════════════════════════════════════════════════
            function initTestModal() {
                const $modal = $('#fp-test-modal');
                
                $('#fp-test-plant').on('click', () => $modal.css('display', 'flex'));
                $('#fp-test-cancel').on('click', () => $modal.hide());
                $modal.on('click', function(e) {
                    if (e.target === this) $modal.hide();
                });
                
                $('#fp-test-confirm').on('click', function() {
                    const $btn = $(this);
                    const name = $('#test-name').val();
                    const email = $('#test-email').val();
                    
                    if (!email) {
                        toast('Introduce un email', 'error');
                        return;
                    }
                    
                    $btn.prop('disabled', true).text('Plantando...');
                    
                    $.post(AJAXURL, {
                        action: 'dr_forest_test_plant',
                        _nonce: NONCE,
                        name: name,
                        email: email
                    }, function(res) {
                        $btn.prop('disabled', false).text('Plantar árbol');
                        if (res.success) {
                            $modal.hide();
                            toast('¡Árbol plantado! Revisa el email', 'success');
                            console.log('Tree-Nation response:', res.data);
                        } else {
                            toast(res.data || 'Error al plantar', 'error');
                        }
                    });
                });
            }
            
            // ═══════════════════════════════════════════════════
            // EDIT DOMAIN MODAL
            // ═══════════════════════════════════════════════════
            function initEditModal() {
                const $modal = $('#fp-edit-modal');
                
                // Open modal on edit button click
                $(document).on('click', '.fp-edit-btn', function() {
                    const $btn = $(this);
                    $('#edit-domain-id').val($btn.data('id'));
                    $('#edit-domain-name').val($btn.data('domain'));
                    $('#edit-client-name').val($btn.data('client'));
                    $('#edit-client-email').val($btn.data('email'));
                    $('#edit-product').val(($btn.data('product') || '').toLowerCase());
                    $('#edit-cycle').val(($btn.data('cycle') || '').toLowerCase());
                    $('#edit-renewal').val($btn.data('renewal'));
                    $modal.css('display', 'flex');
                });
                
                // Close modal
                $('#fp-edit-cancel').on('click', () => $modal.hide());
                $modal.on('click', function(e) {
                    if (e.target === this) $modal.hide();
                });
                
                // Save changes
                $('#fp-edit-save').on('click', function() {
                    const $btn = $(this);
                    const domainId = $('#edit-domain-id').val();
                    
                    $btn.prop('disabled', true).text('Guardando...');
                    
                    $.post(AJAXURL, {
                        action: 'dr_forest_save_domain',
                        _nonce: NONCE,
                        domain_id: domainId,
                        client_name: $('#edit-client-name').val(),
                        client_email: $('#edit-client-email').val(),
                        product: $('#edit-product').val(),
                        cycle: $('#edit-cycle').val(),
                        renewal: $('#edit-renewal').val()
                    }, function(res) {
                        $btn.prop('disabled', false).text('Guardar cambios');
                        if (res.success) {
                            $modal.hide();
                            toast('Dominio actualizado', 'success');
                            loadDomains(domainsPage);
                            loadStats();
                        } else {
                            toast(res.data || 'Error al guardar', 'error');
                        }
                    });
                });
            }
            
            // ═══════════════════════════════════════════════════
            // TOAST
            // ═══════════════════════════════════════════════════
            function toast(msg, type = 'success') {
                const $t = $('#fp-toast');
                $t.text(msg)
                  .removeClass('fp-toast--success fp-toast--error')
                  .addClass('fp-toast--' + type)
                  .addClass('is-visible');
                
                setTimeout(() => $t.removeClass('is-visible'), 3000);
            }
            
            // ═══════════════════════════════════════════════════
            // UTILS
            // ═══════════════════════════════════════════════════
            function escHtml(str) {
                if (!str) return '';
                return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }
            
        })(jQuery);
        </script>
        </div>
        <?php
    }
}

// Initialize
Dominios_Reseller_Forest_Admin::init();
