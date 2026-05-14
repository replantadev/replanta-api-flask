<?php
namespace TEW\Tests;

use TEW\Utils;

class UtilsTest extends TestCase {

    public function test_normalize_url_appends_scheme() {
        $normalized = Utils::normalize_url( 'example.org' );
        $this->assertSame( 'https://example.org', $normalized );
    }

    public function test_normalize_url_rejects_invalid() {
        $this->assertFalse( Utils::normalize_url( 'notaurl' ) );
    }

    public function test_get_domain_from_url() {
        $this->assertSame( 'example.org', Utils::get_domain( 'https://example.org/path' ) );
    }
}
