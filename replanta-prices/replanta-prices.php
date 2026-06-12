<?php
/**
 * Plugin Name: Replanta Prices
 * Plugin URI:  https://github.com/replantadev/replantaprices
 * Description: Precios dinámicos de hosting, mantenimiento y SAP WooCommerce sincronizados con Upmind. Shortcodes con detección geográfica multi-divisa.
 * Version:     1.1.2
 * Author:      Replanta
 * Author URI:  https://replanta.net
 * License:     GPL-2.0-or-later
 * Text Domain: replanta-prices
 * Requires at least: 6.0
 * Tested up to: 7.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

/* ─── Constants ────────────────────────────────────────────────────── */
define( 'REPLANTA_PRICES_VERSION', '1.1.2' );
define( 'REPLANTA_PRICES_FILE',    __FILE__ );
define( 'REPLANTA_PRICES_DIR',     plugin_dir_path( __FILE__ ) );
define( 'REPLANTA_PRICES_URL',     plugin_dir_url( __FILE__ ) );
define( 'REPLANTA_PRICES_SLUG',    'replanta-prices' );

/* ─── Autoload ─────────────────────────────────────────────────────── */
require_once REPLANTA_PRICES_DIR . 'includes/class-geo.php';
require_once REPLANTA_PRICES_DIR . 'includes/class-upmind-api.php';
require_once REPLANTA_PRICES_DIR . 'includes/class-price-cache.php';
require_once REPLANTA_PRICES_DIR . 'includes/class-awin-analytics.php';
require_once REPLANTA_PRICES_DIR . 'includes/class-awin-dashboard.php';
require_once REPLANTA_PRICES_DIR . 'includes/class-product-feed.php';
require_once REPLANTA_PRICES_DIR . 'includes/awin/class-awin-logger.php';
require_once REPLANTA_PRICES_DIR . 'includes/awin/class-awin-logs-cleanup.php';
require_once REPLANTA_PRICES_DIR . 'includes/class-admin-settings.php';
require_once REPLANTA_PRICES_DIR . 'includes/class-shortcodes.php';

/* ─── Init ─────────────────────────────────────────────────────────── */
add_action( 'plugins_loaded', 'replanta_prices_init' );
add_action( 'init', 'replanta_prices_ensure_crons' );

add_action( 'init', function () {
    load_plugin_textdomain( 'replanta-prices', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}, 1 );

function replanta_prices_init() {
    Replanta_Prices_Geo::init();
    Replanta_Prices_Cache::init();
    Replanta_Prices_Awin_Analytics::init();
    Replanta_Prices_Admin::init();
    Replanta_Prices_Product_Feed::init();
    Replanta_Prices_Shortcodes::init();
}

function replanta_prices_ensure_crons() {
    if ( ! wp_next_scheduled( 'replanta_prices_sync_cron' ) ) {
        wp_schedule_event( time(), 'replanta_prices_6h', 'replanta_prices_sync_cron' );
    }
    if ( ! wp_next_scheduled( 'replanta_prices_awin_s2s_cron' ) ) {
        wp_schedule_event( time() + MINUTE_IN_SECONDS, 'replanta_prices_5m', 'replanta_prices_awin_s2s_cron' );
    }
}

/* ─── Awin MasterTag injection ─────────────────────────────────────── */
add_action( 'wp_footer', 'replanta_prices_inject_awin_mastertag', 99 );

function replanta_prices_inject_awin_mastertag() {
    $settings = get_option( 'replanta_prices_settings', array() );
    if ( empty( $settings['awin_mastertag'] ) ) {
        return;
    }
    $mid = isset( $settings['awin_s2s_merchant_id'] ) ? absint( $settings['awin_s2s_merchant_id'] ) : 125596;
  echo '<script src="https://www.dwin1.com/' . esc_attr( $mid ) . '.js" type="text/javascript" defer="defer" data-no-defer></script>' . "\n";
}

/* ─── Awin Landing Script injection ────────────────────────────────── */
add_action( 'wp_head', 'replanta_prices_inject_awin_landing_script', 2 );

function replanta_prices_inject_awin_landing_script() {
    $settings = get_option( 'replanta_prices_settings', array() );
    if ( empty( $settings['awin_landing_script'] ) ) {
        return;
    }
    $mid      = isset( $settings['awin_s2s_merchant_id'] ) ? absint( $settings['awin_s2s_merchant_id'] ) : 125596;
    $endpoint = rest_url( 'replanta-prices/v1/awin-event' );
    ?>
<script data-no-defer data-replanta-awin>
(function() {
  var AWIN_ENDPOINT = <?php echo wp_json_encode( $endpoint ); ?>;
  var AWIN_MID = '<?php echo esc_js( $mid ); ?>';

  function isValidAwc(value) {
    return typeof value === 'string' && /^[a-zA-Z0-9_-]+$/.test(value);
  }

  function getSharedCookieFlags(expires) {
    return ';domain=.replanta.net'
      + ';path=/'
      + ';expires=' + expires.toUTCString()
      + ';SameSite=Lax'
      + (window.location.protocol === 'https:' ? ';Secure' : '');
  }

  function postAwinEvent(evt) {
    if (!evt || !evt.event || !AWIN_ENDPOINT) return;
    var payload = JSON.stringify(evt);
    if (navigator.sendBeacon) {
      try {
        var blob = new Blob([payload], { type: 'application/json' });
        if (navigator.sendBeacon(AWIN_ENDPOINT, blob)) return;
      } catch (e) {}
    }
    try {
      fetch(AWIN_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: payload,
        keepalive: true,
        credentials: 'omit'
      });
    } catch (e) {}
  }

  /* ── MasterTag fallback loader ── */
  (function loadMasterTag() {
    var urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.get('sn') && !urlParams.get('awc')) return;
    setTimeout(function() {
      if (document.querySelector('script[src*="dwin1.com/' + AWIN_MID + '"]')) {
        console.log('[Replanta] MasterTag already loaded (via GTM)');
        return;
      }
      console.log('[Replanta] Loading MasterTag as fallback');
      var s = document.createElement('script');
      s.src = 'https://www.dwin1.com/' + AWIN_MID + '.js';
      s.async = true;
      s.type = 'text/javascript';
      document.body.appendChild(s);
    }, 1500);
  })();

  /* ── Cookie helpers ── */
  function saveAwcCookie(awc) {
    var expires = new Date();
    expires.setDate(expires.getDate() + 90);
    document.cookie = 'replanta_awin_awc=' + encodeURIComponent(awc)
      + getSharedCookieFlags(expires);
  }

  function getAwinMasterTagCookie() {
    var cookies = document.cookie.split(';');
    var prefixes = ['_aw_m_' + AWIN_MID + '=', '_aw_sn_' + AWIN_MID + '=', 'aw' + AWIN_MID + '='];
    for (var i = 0; i < cookies.length; i++) {
      var c = cookies[i].trim();
      for (var j = 0; j < prefixes.length; j++) {
        if (c.indexOf(prefixes[j]) === 0) {
          return decodeURIComponent(c.substring(prefixes[j].length));
        }
      }
    }
    return null;
  }

  function findAwcAnywhere() {
    var fromCookie = getAwinMasterTagCookie();
    if (fromCookie) return { source: 'cookie', awc: fromCookie };
    var cookies = document.cookie.split(';');
    for (var i = 0; i < cookies.length; i++) {
      var c = cookies[i].trim();
      var name = c.split('=')[0];
      if (name && /aw/i.test(name)) {
        var val = decodeURIComponent(c.substring(name.length + 1));
        if (val && /^\d+_\d+_[a-f0-9]+$/i.test(val)) {
          return { source: 'cookie-' + name, awc: val };
        }
      }
    }
    if (window.AWIN && window.AWIN.Tracking) {
      var t = window.AWIN.Tracking;
      if (t.awc) return { source: 'AWIN.Tracking.awc', awc: t.awc };
      if (t.Sale && t.Sale.cookie) return { source: 'AWIN.Tracking.Sale.cookie', awc: t.Sale.cookie };
    }
    if (window.awin && window.awin.awc) {
      return { source: 'awin.awc', awc: window.awin.awc };
    }
    return null;
  }

  /* ── Process AWC ── */
  var _awcAlreadyCaptured = false;
  function processFoundAwc(result) {
    if (_awcAlreadyCaptured) return;
    _awcAlreadyCaptured = true;
    saveAwcCookie(result.awc);
    console.log('[Replanta] AWC captured from ' + result.source + ':', result.awc);
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ event: 'awin_awc_captured', awin_awc: result.awc });
    postAwinEvent({ event: 'arrival', awc: result.awc, url: window.location.href });
  }

  /* ── Capture AWC ── */
  (function captureAWC() {
    var urlParams = new URLSearchParams(window.location.search);
    var isSn1 = urlParams.get('sn') === '1';
    var awc = urlParams.get('awc');
    if (isValidAwc(awc)) { processFoundAwc({ source: 'URL', awc: awc }); return; }
    if (!isSn1) return;
    console.log('[Replanta] sn=1 detected, waiting for MasterTag...');

    var _sn1FallbackSent = false;
    function sendSn1Fallback() {
      if (_sn1FallbackSent || _awcAlreadyCaptured) return;
      _sn1FallbackSent = true;
      var expires = new Date(); expires.setDate(expires.getDate() + 30);
      document.cookie = 'replanta_awin_sn1=1' + getSharedCookieFlags(expires);
      console.warn('[Replanta] sn=1 AWC unresolved. Sending fallback arrival.');
      postAwinEvent({ event: 'arrival_unresolved', awc: null, sn: '1', url: window.location.href, referrer: document.referrer || null, ua: navigator.userAgent });
    }

    function pollForAwc(label, maxMs, isFinal) {
      var elapsed = 0, interval = 250;
      var poll = setInterval(function() {
        elapsed += interval;
        var result = findAwcAnywhere();
        if (result) { clearInterval(poll); processFoundAwc(result); }
        else if (elapsed >= maxMs) {
          clearInterval(poll);
          console.warn('[Replanta] ' + label + ': AWC not found after ' + (maxMs/1000) + 's');
          if (isFinal) sendSn1Fallback();
        }
      }, interval);
      return poll;
    }
    pollForAwc('Initial poll', 10000, false);

    /* ── Consent reload ── */
    function reloadMasterTag(callback) {
      var scripts = document.querySelectorAll('script[src*="dwin1.com"]');
      for (var i = 0; i < scripts.length; i++) scripts[i].parentNode.removeChild(scripts[i]);
      var iframes = document.querySelectorAll('iframe[src*="dwin1.com"], iframe[src*="awin1.com"]');
      for (var j = 0; j < iframes.length; j++) iframes[j].parentNode.removeChild(iframes[j]);
      try { delete window.AWIN; } catch(e) { window.AWIN = undefined; }
      try { delete window.awin; } catch(e) { window.awin = undefined; }
      console.log('[Replanta] Reloading MasterTag after consent...');
      var s = document.createElement('script');
      s.src = 'https://www.dwin1.com/' + AWIN_MID + '.js';
      s.async = true; s.type = 'text/javascript';
      s.onload = function() { console.log('[Replanta] MasterTag reloaded'); if (callback) callback(); };
      s.onerror = function() { console.warn('[Replanta] MasterTag reload failed'); if (callback) callback(); };
      document.body.appendChild(s);
    }

    function onConsentGranted() {
      if (_awcAlreadyCaptured) return;
      console.log('[Replanta] Consent granted, reloading MasterTag...');
      reloadMasterTag(function() {
        setTimeout(function() { if (!_awcAlreadyCaptured) pollForAwc('Post-consent poll', 15000, true); }, 2000);
      });
    }

    document.addEventListener('cmplz_fire_categories', function(e) {
      if (e.detail && e.detail.categories && e.detail.categories.indexOf('marketing') !== -1) onConsentGranted();
    });
    document.addEventListener('cmplz_status_change', function(e) {
      if (e.detail && e.detail.marketing && e.detail.marketing === 'allow') onConsentGranted();
    });
    if (window.dataLayer) {
      var origPush = window.dataLayer.push;
      window.dataLayer.push = function() {
        var result = origPush.apply(this, arguments);
        for (var i = 0; i < arguments.length; i++) {
          var arg = arguments[i];
          if (arg && ((arg[0] === 'consent' && arg[1] === 'update') || (arg.event === 'consent_update'))) {
            if (!_awcAlreadyCaptured && isSn1) onConsentGranted();
          }
        }
        return result;
      };
    }
  })();

  /* ── PID → Plan mapping ── */
  var PID_TO_PLAN = {
    '6d530876-8251-d485-d80a-147e390921e6': 'sauce',
    'e2e071d9-31d5-e460-555a-646028758396': 'cedro',
    '280d1639-e237-d439-6dea-54610589e572': 'roble',
    '2e071d93-1d5e-468e-935c-646028758396': 'sapwoo-setup',
    '61e50989-73d2-4753-988c-e45e610832d7': 'sapwoo-monthly'
  };

  function getAwcFromCookie() {
    var cookies = document.cookie.split(';');
    for (var i = 0; i < cookies.length; i++) {
      var cookie = cookies[i].trim();
      if (cookie.indexOf('replanta_awin_awc=') === 0) return decodeURIComponent(cookie.substring('replanta_awin_awc='.length));
    }
    return null;
  }

  function parseUrl(href) { try { return new URL(href, location.origin); } catch(e) { return null; } }
  function getQuery(u, key) { if (!u) return null; var v = u.searchParams.get(key); return v === null ? null : v; }
  function getPid(u) { return getQuery(u, 'pid'); }
  function getBilling() { var y = document.getElementById('bill-y'); return (y && y.checked) ? 'yearly' : 'monthly'; }
  function getBcm(u) { var bcm = getQuery(u, 'bcm'); return bcm ? String(bcm) : null; }
  function getBillingFromBcm(u) { var bcm = getBcm(u); if (bcm === '12') return 'yearly'; if (bcm === '1') return 'monthly'; return null; }
  function getCurrency(u) { var cur = getQuery(u, 'currency'); return cur ? cur.toUpperCase() : null; }
  function getCoupon(u) { return getQuery(u, 'coupons'); }
  function getPriceFromCard(btn, billing) {
    var card = btn.closest('.replanta-pricing-card');
    if (!card) return null;
    var priceBox = card.querySelector('.price');
    if (!priceBox) return null;
    var amountEl = priceBox.querySelector(billing === 'yearly' ? '.amount--y' : '.amount--m');
    if (!amountEl) return null;
    var raw = amountEl.textContent.trim();
    var numeric = raw.replace(/[^\d,.\-]/g, '').replace(/\.(?=\d{3}\b)/g, '').replace(',', '.');
    var value = parseFloat(numeric);
    return { raw: raw, value: isNaN(value) ? null : value };
  }
  function planFromPid(pid) { return PID_TO_PLAN[pid] || 'unknown'; }

  function pushEvents(payload) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
      event: 'upmind_begin_checkout',
      ecommerce: { currency: payload.currency || undefined, value: payload.value || undefined, items: [{ item_name: payload.plan, item_id: payload.pid, price: payload.value || undefined }] },
      upmind: { plan: payload.plan, pid: payload.pid, url: payload.url, currency: payload.currency || null, billing: payload.billing, bcm: payload.bcm || null, coupon: payload.coupon || null, price_label: payload.priceLabel },
      awin_awc: payload.awc || null
    });
    if (typeof window.gtag === 'function') {
      window.gtag('event', 'begin_checkout', {
        currency: payload.currency || undefined, value: payload.value || undefined, coupon: payload.coupon || undefined,
        items: [{ item_name: payload.plan, item_id: payload.pid, price: payload.value || undefined }],
        upmind_pid: payload.pid, upmind_plan: payload.plan, upmind_billing: payload.billing, upmind_bcm: payload.bcm || undefined, awin_awc: payload.awc || undefined
      });
    }
    if (typeof window.fbq === 'function') {
      window.fbq('trackCustom', 'UpmindBeginCheckout', {
        plan: payload.plan, pid: payload.pid, currency: payload.currency || undefined, value: payload.value || undefined,
        billing: payload.billing, bcm: payload.bcm || undefined, coupon: payload.coupon || undefined
      });
    }
    postAwinEvent({ event: 'begin_checkout', awc: payload.awc || null, pid: payload.pid || null, value: payload.value || null, currency: payload.currency || null, bcm: payload.bcm || null, url: payload.url || null });
  }

  function handleClick(ev) {
    var a = ev.currentTarget;
    var u = parseUrl(a.getAttribute('href') || '');
    if (!u) return;
    var host = u.host || '', path = u.pathname || '';
    if (!/clientes\.replanta\.net$/i.test(host)) return;
    if (!/\/order\/product/i.test(path)) return;
    var pid = getPid(u), plan = planFromPid(pid), billing = getBillingFromBcm(u) || getBilling();
    var bcm = getBcm(u) || (billing === 'yearly' ? '12' : '1');
    var currency = (getCurrency(u) || '').toUpperCase() || null, coupon = getCoupon(u);
    var priceObj = getPriceFromCard(a, billing), awc = getAwcFromCookie();
    pushEvents({ plan: plan, pid: pid, url: u.toString(), billing: billing, bcm: bcm, currency: currency, coupon: coupon, priceLabel: priceObj ? priceObj.raw : null, value: priceObj ? priceObj.value : null, awc: awc });
  }

  function bindAll() {
    document.querySelectorAll('a.plan-card-cta').forEach(function(a) {
      a.removeEventListener('click', handleClick);
      a.addEventListener('click', handleClick, { passive: true });
    });
  }
  bindAll();
  var mo = new MutationObserver(function() { bindAll(); });
  mo.observe(document.documentElement, { childList: true, subtree: true });

  window.replantaTrackAwinPurchase = function(data) {
    data = data || {};

    // Accept multiple key names used by GTM snippets and thank-you templates.
    var orderId = data.order_id || data.orderId || data.orderRef || data.order_ref || null;
    var valueRaw = (typeof data.value !== 'undefined' && data.value !== null) ? data.value : (data.amount || data.total || 0);
    var valueNum = (typeof valueRaw === 'number' ? valueRaw : parseFloat(String(valueRaw).replace(',', '.'))) || 0;
    var voucher = data.voucher || data.coupon || data.vc || null;

    postAwinEvent({
      event: 'purchase', awc: data.awc || getAwcFromCookie() || null, pid: data.pid || null,
      order_id: orderId,
      value: valueNum,
      currency: data.currency || null,
      voucher: voucher,
      url: data.url || window.location.href
    });
  };
})();
</script>
<?php
}

/* ─── Auto-enqueue upmind-dac on any page that uses it ─────────────── */
// Fires earlier than wp_enqueue_scripts so the script is always in <head>.
// Detects both [replanta_domains] shortcode and raw <upm-dac> in Elementor HTML widgets.
add_action( 'wp_enqueue_scripts', 'replanta_prices_maybe_enqueue_dac', 5 );

function replanta_prices_maybe_enqueue_dac() {
    global $post;
    if ( ! $post instanceof WP_Post ) {
        return;
    }
    $content = $post->post_content;
    if ( has_shortcode( $content, 'replanta_domains' )
        || false !== strpos( $content, 'upm-dac' ) ) {
        wp_enqueue_script(
            'upmind-dac',
            'https://widgets.upmind.app/dac/upm-dac.min.js',
            array(),
            null,
            false  // must be in <head> for web components
        );
    }
}

/* ─── Activation ───────────────────────────────────────────────────── */
register_activation_hook( __FILE__, 'replanta_prices_activate' );

function replanta_prices_activate() {
    // Schedule cron sync
    if ( ! wp_next_scheduled( 'replanta_prices_sync_cron' ) ) {
        wp_schedule_event( time(), 'replanta_prices_6h', 'replanta_prices_sync_cron' );
    }
    // Schedule AWIN S2S retry queue processing.
    if ( ! wp_next_scheduled( 'replanta_prices_awin_s2s_cron' ) ) {
        wp_schedule_event( time() + MINUTE_IN_SECONDS, 'replanta_prices_5m', 'replanta_prices_awin_s2s_cron' );
    }
    // Seed default product data if empty
    Replanta_Prices_Cache::maybe_seed_defaults();
}

/* ─── Deactivation ─────────────────────────────────────────────────── */
register_deactivation_hook( __FILE__, 'replanta_prices_deactivate' );

function replanta_prices_deactivate() {
    wp_clear_scheduled_hook( 'replanta_prices_sync_cron' );
    wp_clear_scheduled_hook( 'replanta_prices_awin_s2s_cron' );
}

/* ─── Custom Cron Interval (every 6 hours) ─────────────────────────── */
add_filter( 'cron_schedules', 'replanta_prices_cron_schedules' );

function replanta_prices_cron_schedules( $schedules ) {
    $schedules['replanta_prices_5m'] = array(
        'interval' => 5 * MINUTE_IN_SECONDS,
        'display'  => __( 'Cada 5 minutos (Replanta Prices)', 'replanta-prices' ),
    );
    $schedules['replanta_prices_6h'] = array(
        'interval' => 6 * HOUR_IN_SECONDS,
        'display'  => __( 'Cada 6 horas (Replanta Prices)', 'replanta-prices' ),
    );
    return $schedules;
}

/* ─── Auto-updates via PUC ─────────────────────────────────────────── */
if ( file_exists( REPLANTA_PRICES_DIR . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php' ) ) {
    require_once REPLANTA_PRICES_DIR . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';
    if ( class_exists( 'YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
        $replanta_prices_updater = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/replantadev/replantaprices/',
            __FILE__,
            'replanta-prices'
        );
        $replanta_prices_updater->setBranch( 'main' );
    }
}
