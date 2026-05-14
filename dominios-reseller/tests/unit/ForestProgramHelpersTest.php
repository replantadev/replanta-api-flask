<?php
/**
 * Unit tests for pure-logic helpers used by the Forest Program.
 *
 * These tests intentionally re-implement the helpers verbatim so they can run
 * without bootstrapping all of WordPress. Keep them in sync with the real
 * implementations in includes/class-forest-program.php.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ForestProgramHelpersTest extends TestCase {

    /**
     * Pure copy of `Dominios_Reseller_Forest_Program::add_utm()`.
     */
    private function addUtm( string $url, string $campaign, string $content = '' ): string {
        if ( empty( $url ) || $url === '#' ) {
            return $url;
        }
        $params = [
            'utm_source'   => 'replanta',
            'utm_medium'   => 'email',
            'utm_campaign' => $campaign,
        ];
        if ( $content !== '' ) {
            $params['utm_content'] = $content;
        }
        return add_query_arg( $params, $url );
    }

    /**
     * Pure copy of `Dominios_Reseller_Forest_Program::anonymize_domain()`.
     */
    private function anonymizeDomain( string $domain ): string {
        $parts = explode( '.', $domain, 2 );
        $name  = $parts[0] ?? $domain;
        $tld   = $parts[1] ?? '';
        $len   = mb_strlen( $name );
        if ( $len <= 2 ) {
            return $domain;
        }
        $masked = mb_substr( $name, 0, 1 ) . str_repeat( '*', max( 1, $len - 2 ) ) . mb_substr( $name, -1 );
        return $tld ? $masked . '.' . $tld : $masked;
    }

    /* ───────────── add_utm ───────────── */

    public function testAddUtmAppendsAllParamsOnPlainUrl(): void {
        $out = $this->addUtm( 'https://tree-nation.com/collect/abc', 'tree_email_collect', '12345' );

        $this->assertStringContainsString( 'utm_source=replanta', $out );
        $this->assertStringContainsString( 'utm_medium=email', $out );
        $this->assertStringContainsString( 'utm_campaign=tree_email_collect', $out );
        $this->assertStringContainsString( 'utm_content=12345', $out );
    }

    public function testAddUtmPreservesExistingQueryString(): void {
        $out = $this->addUtm( 'https://x.test/path?ref=foo', 'cert', '' );

        $this->assertStringContainsString( 'ref=foo', $out );
        $this->assertStringContainsString( 'utm_campaign=cert', $out );
        $this->assertStringNotContainsString( 'utm_content', $out );
    }

    public function testAddUtmReturnsUntouchedFallback(): void {
        $this->assertSame( '#', $this->addUtm( '#', 'x' ) );
        $this->assertSame( '', $this->addUtm( '', 'x' ) );
    }

    /* ───────────── anonymize_domain ───────────── */

    public function testAnonymizeDomainStandardCase(): void {
        $this->assertSame( 'm******o.com', $this->anonymizeDomain( 'midominio.com' ) );
    }

    public function testAnonymizeDomainKeepsTld(): void {
        $out = $this->anonymizeDomain( 'replanta.eu' );
        $this->assertStringEndsWith( '.eu', $out );
        $this->assertStringStartsWith( 'r', $out );
    }

    public function testAnonymizeDomainShortNameNotMasked(): void {
        $this->assertSame( 'ab.com', $this->anonymizeDomain( 'ab.com' ) );
    }

    public function testAnonymizeDomainNoTld(): void {
        $this->assertSame( 'l*****t', $this->anonymizeDomain( 'localhost' ) );
    }

    /* ───────────── backoff schedule ───────────── */

    /**
     * Verify the exponential backoff used by process_queue_item():
     *   delay_minutes = min(240, 15 * 4^(attempt-1))
     * Attempts: 1 → 15min, 2 → 60min, 3 → 240min (capped).
     *
     * @dataProvider backoffProvider
     */
    public function testBackoffSchedule( int $attempt, int $expected ): void {
        $delay = (int) min( 240, 15 * pow( 4, $attempt - 1 ) );
        $this->assertSame( $expected, $delay );
    }

    public static function backoffProvider(): array {
        return [
            'first retry'  => [ 1, 15 ],
            'second retry' => [ 2, 60 ],
            'third capped' => [ 3, 240 ],
            'beyond cap'   => [ 4, 240 ],
        ];
    }
}
