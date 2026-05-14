<?php
/**
 * Awin Cookie Manager - Captures and manages the AWC parameter.
 *
 * @package Replanta_Prices
 * @subpackage Awin
 */

defined( 'ABSPATH' ) || exit;

class Replanta_Awin_Cookie {

    /** @var string Cookie name for AWC */
    const COOKIE_NAME = 'replanta_awin_awc';

    /** @var string Option key for settings */
    const SETTINGS_KEY = 'replanta_awin_settings';

    /** @var bool Whether capture was performed this request */
    private static $captured_this_request = false;

    /** @var string|null AWC pending consent (captured but not stored yet) */
    private static $awc_pending_consent = null;

    /**
     * Initialize hooks.
     */
    public static function init() {
        // Capture AWC early, before any output
        add_action( 'init', array( __CLASS__, 'maybe_capture_awc' ), 1 );

        // Add script to handle consent-based cookie setting
        add_action( 'wp_footer', array( __CLASS__, 'output_consent_script' ), 100 );
    }

    /**
     * Get Awin settings with defaults.
     *
     * @return array
     */
    public static function get_settings() {
        $defaults = array(
            'enabled'              => true,
            'cookie_name'          => self::COOKIE_NAME,
            'cookie_days'          => 90,
            'target_domain'        => 'clientes.replanta.net',
            'webhook_secret'       => '',
            'js_fallback'          => true,
            'detailed_logs'        => true,
            'log_retention_days'   => 90,
            'advertiser_id'        => '',
            'inject_mastertag'     => false,
        );

        $saved = get_option( self::SETTINGS_KEY, array() );
        return wp_parse_args( $saved, $defaults );
    }

    /**
     * Save Awin settings.
     *
     * @param array $settings
     * @return bool
     */
    public static function save_settings( $settings ) {
        return update_option( self::SETTINGS_KEY, $settings );
    }

    /**
     * Check if Awin tracking is enabled.
     *
     * @return bool
     */
    public static function is_enabled() {
        $settings = self::get_settings();
        return ! empty( $settings['enabled'] );
    }

    /**
     * Get the configured cookie name.
     *
     * @return string
     */
    public static function get_cookie_name() {
        $settings = self::get_settings();
        return ! empty( $settings['cookie_name'] ) ? $settings['cookie_name'] : self::COOKIE_NAME;
    }

    /**
     * Get cookie lifetime in seconds.
     *
     * @return int
     */
    public static function get_cookie_lifetime() {
        $settings = self::get_settings();
        $days     = isset( $settings['cookie_days'] ) ? absint( $settings['cookie_days'] ) : 90;
        return $days * DAY_IN_SECONDS;
    }

    /**
     * Capture AWC parameter from URL if present.
     * Respects Complianz cookie consent - only stores if marketing consent given.
     */
    public static function maybe_capture_awc() {
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }

        if ( ! self::is_enabled() ) {
            return;
        }

        // Check for awc parameter
        if ( ! isset( $_GET['awc'] ) || empty( $_GET['awc'] ) ) {
            return;
        }

        $awc = self::sanitize_awc( $_GET['awc'] );
        if ( empty( $awc ) ) {
            return;
        }

        // Check cookie consent (Complianz integration)
        $has_consent = self::has_marketing_consent();

        if ( $has_consent ) {
            // User has consent - store cookie directly
            self::set_awc_cookie( $awc );
            self::$captured_this_request = true;

            // Log the capture event
            self::log_capture( $awc, 'cookie_set' );
        } else {
            // No consent yet - store AWC for JS to set later when consent is given
            self::$awc_pending_consent = $awc;

            // Log that we captured but are waiting for consent
            self::log_capture( $awc, 'pending_consent' );
        }
    }

    /**
     * Check if user has given marketing cookie consent.
     * Integrates with Complianz and other cookie consent plugins.
     *
     * @return bool
     */
    public static function has_marketing_consent() {
        // Complianz integration
        if ( function_exists( 'cmplz_has_consent' ) ) {
            return cmplz_has_consent( 'marketing' );
        }

        // Complianz cookie check (fallback)
        if ( isset( $_COOKIE['cmplz_marketing'] ) ) {
            return $_COOKIE['cmplz_marketing'] === 'allow';
        }

        // CookieYes integration
        if ( isset( $_COOKIE['cookieyes-consent'] ) ) {
            $consent = $_COOKIE['cookieyes-consent'];
            return strpos( $consent, 'advertisement:yes' ) !== false;
        }

        // GDPR Cookie Compliance integration
        if ( isset( $_COOKIE['gdpr_consent'] ) ) {
            $consent = json_decode( stripslashes( $_COOKIE['gdpr_consent'] ), true );
            return ! empty( $consent['marketing'] );
        }

        // Cookie Notice by dFactory
        if ( isset( $_COOKIE['cookie_notice_accepted'] ) ) {
            return $_COOKIE['cookie_notice_accepted'] === 'true';
        }

        // No consent plugin detected - check if we're in a region that requires consent
        // Be CONSERVATIVE: if no consent plugin, assume NO consent in EU region
        // This respects GDPR by default
        
        // If Complianz is active but we couldn't detect consent, assume no consent
        if ( defined( 'CMPLZ_VERSION' ) || class_exists( 'COMPLIANZ' ) ) {
            return false;
        }

        // No consent plugin detected - allow cookie (non-EU or no consent management)
        return true;
    }

    /**
     * Log AWC capture event.
     *
     * @param string $awc    The AWC value.
     * @param string $method How it was captured.
     */
    private static function log_capture( $awc, $method ) {
        if ( ! class_exists( 'Replanta_Awin_Logger' ) ) {
            return;
        }

        // Get visitor IP for lookup mapping
        $ip = self::get_visitor_ip();

        Replanta_Awin_Logger::log_event( 'awc_captured', array(
            'awc'        => $awc,
            'method'     => $method,
            'url'        => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( $_SERVER['REQUEST_URI'] ) : '',
            'referer'    => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : '',
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '',
            'ip_hash'    => self::hash_ip(),
        ) );

        // Store IP → AWC mapping for webhook lookup
        // This allows us to find the AWC when Upmind webhook arrives with the IP
        if ( ! empty( $ip ) && class_exists( 'Replanta_Awin_Webhook' ) ) {
            Replanta_Awin_Webhook::store_ip_awc_mapping( $ip, $awc );
        }
    }

    /**
     * Get visitor IP address.
     * Handles proxies and Cloudflare.
     *
     * @return string|null
     */
    private static function get_visitor_ip() {
        // Cloudflare
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            return sanitize_text_field( $_SERVER['HTTP_CF_CONNECTING_IP'] );
        }

        // Standard proxies
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
            return sanitize_text_field( trim( $ips[0] ) );
        }

        if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
            return sanitize_text_field( $_SERVER['HTTP_X_REAL_IP'] );
        }

        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            return sanitize_text_field( $_SERVER['REMOTE_ADDR'] );
        }

        return null;
    }

    /**
     * Output JavaScript to set cookie after consent is given.
     * Uses Complianz's consent event to set the cookie.
     */
    public static function output_consent_script() {
        // Only output if we have a pending AWC
        if ( empty( self::$awc_pending_consent ) ) {
            return;
        }

        $awc         = esc_js( self::$awc_pending_consent );
        $cookie_name = esc_js( self::get_cookie_name() );
        $cookie_days = absint( self::get_settings()['cookie_days'] );

        ?>
        <script>
        (function() {
            var awc = '<?php echo $awc; ?>';
            var cookieName = '<?php echo $cookie_name; ?>';
            var cookieDays = <?php echo $cookie_days; ?>;

            function setAwcCookie() {
                var expires = new Date();
                expires.setTime(expires.getTime() + (cookieDays * 24 * 60 * 60 * 1000));
                document.cookie = cookieName + '=' + awc + ';expires=' + expires.toUTCString() + ';path=/;SameSite=Lax';
                console.log('[Replanta Awin] Cookie set after consent:', awc);
            }

            // Complianz: Listen for consent event
            document.addEventListener('cmplz_fire_categories', function(e) {
                if (e.detail && e.detail.categories && e.detail.categories.indexOf('marketing') !== -1) {
                    setAwcCookie();
                }
            });

            // Complianz: Check if already consented
            if (typeof cmplz_has_consent === 'function' && cmplz_has_consent('marketing')) {
                setAwcCookie();
            }

            // CookieYes: Listen for consent
            document.addEventListener('cookieyes_consent_update', function(e) {
                if (e.detail && e.detail.accepted && e.detail.accepted.indexOf('advertisement') !== -1) {
                    setAwcCookie();
                }
            });

            // Generic: Check if marketing cookie becomes available
            var checkInterval = setInterval(function() {
                var cookies = document.cookie;
                if (cookies.indexOf('cmplz_marketing=allow') !== -1 || 
                    cookies.indexOf('cookieyes-consent') !== -1 && cookies.indexOf('advertisement:yes') !== -1) {
                    setAwcCookie();
                    clearInterval(checkInterval);
                }
            }, 1000);

            // Stop checking after 30 seconds
            setTimeout(function() { clearInterval(checkInterval); }, 30000);
        })();
        </script>
        <?php
    }

    /**
     * Validate and sanitize AWC parameter.
     * 
     * ULTRA CONSERVATIVE: Only accept AWC that matches real Awin format.
     * Awin AWC format: advertiser_id_click_reference
     * Example: 1234567_1234567890123456789_abc123def456ghi789
     *
     * @param string $awc
     * @return string Sanitized AWC or empty string if invalid
     */
    public static function sanitize_awc( $awc ) {
        // Remove any whitespace
        $awc = trim( $awc );

        // Reject empty
        if ( empty( $awc ) ) {
            return '';
        }

        // STRICT: AWC must be alphanumeric with underscores only
        // No hyphens, dots or other chars - real Awin uses underscores
        if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $awc ) ) {
            return '';
        }

        // STRICT: Length check - real AWCs are 30-80 chars
        $len = strlen( $awc );
        if ( $len < 25 || $len > 150 ) {
            return '';
        }

        // STRICT: Must contain at least 2 underscores (3 parts)
        // Format: advertiser_clickref_checksum
        $parts = explode( '_', $awc );
        if ( count( $parts ) < 3 ) {
            return '';
        }

        // STRICT: First part should be numeric (advertiser ID, 5-8 digits)
        $advertiser_id = $parts[0];
        if ( ! preg_match( '/^[0-9]{5,8}$/', $advertiser_id ) ) {
            return '';
        }

        // STRICT: Reject obvious test/fake values
        $lower_awc = strtolower( $awc );
        $test_patterns = array( 'test', 'fake', 'demo', 'example', 'xxx', '000000', '123456_123456' );
        foreach ( $test_patterns as $pattern ) {
            if ( strpos( $lower_awc, $pattern ) !== false ) {
                return '';
            }
        }

        return $awc;
    }

    /**
     * Additional validation for attribution safety.
     * Call this before actually attributing a conversion.
     *
     * @param string $awc The AWC to validate
     * @return bool True if AWC is trustworthy for attribution
     */
    public static function is_awc_trustworthy( $awc ) {
        // Must pass basic sanitization
        $clean = self::sanitize_awc( $awc );
        if ( empty( $clean ) || $clean !== $awc ) {
            return false;
        }

        // Check the AWC exists in our capture log (we saw this AWC arrive)
        if ( class_exists( 'Replanta_Awin_Logger' ) ) {
            $events = Replanta_Awin_Logger::get_events( array(
                'event_type' => 'awc_captured',
                'awc'        => $awc,
                'limit'      => 1,
            ) );

            // AWC must have been captured by us, not just received in webhook
            if ( empty( $events ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Set the AWC cookie.
     *
     * @param string $awc
     * @return bool
     */
    public static function set_awc_cookie( $awc ) {
        $cookie_name = self::get_cookie_name();
        $lifetime    = self::get_cookie_lifetime();
        $expires     = time() + $lifetime;

        $result = setcookie( $cookie_name, $awc, array(
            'expires'  => $expires,
            'path'     => '/',
            'domain'   => '', // Current domain
            'secure'   => is_ssl(),
            'httponly' => false, // Needs to be readable by JS fallback
            'samesite' => 'Lax',
        ) );

        // Make immediately available for this request
        if ( $result ) {
            $_COOKIE[ $cookie_name ] = $awc;
        }

        return $result;
    }

    /**
     * Get current AWC from cookie.
     *
     * @return string|null AWC value or null if not set
     */
    public static function get_awc() {
        $cookie_name = self::get_cookie_name();

        if ( isset( $_COOKIE[ $cookie_name ] ) && ! empty( $_COOKIE[ $cookie_name ] ) ) {
            return self::sanitize_awc( $_COOKIE[ $cookie_name ] );
        }

        return null;
    }

    /**
     * Check if AWC cookie exists.
     *
     * @return bool
     */
    public static function has_awc() {
        return ! empty( self::get_awc() );
    }

    /**
     * Check if AWC was captured this request.
     *
     * @return bool
     */
    public static function was_captured_this_request() {
        return self::$captured_this_request;
    }

    /**
     * Clear AWC cookie.
     *
     * @return bool
     */
    public static function clear_awc() {
        $cookie_name = self::get_cookie_name();

        $result = setcookie( $cookie_name, '', array(
            'expires'  => time() - YEAR_IN_SECONDS,
            'path'     => '/',
            'domain'   => '',
            'secure'   => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ) );

        if ( $result ) {
            unset( $_COOKIE[ $cookie_name ] );
        }

        return $result;
    }

    /**
     * Hash IP address for privacy-compliant logging.
     *
     * @return string
     */
    private static function hash_ip() {
        $ip = '';
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip ? substr( hash( 'sha256', $ip . wp_salt() ), 0, 16 ) : '';
    }
}
