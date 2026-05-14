<?php
namespace TEW\Tests;

use TEW\Reporting\Report_Storage;

class ReportStorageTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['tew_stub_posts']      = [];
        $GLOBALS['tew_stub_post_meta']  = [];
        $GLOBALS['tew_stub_transients'] = [];
    }

    public function test_save_persists_report_and_history(): void {
        $storage = new Report_Storage();

        $base_report = [
            'url'      => 'https://example.org',
            'summary'  => [ 'score' => 82 ],
            'metrics'  => [],
            'errors'   => [],
            'snapshots'=> [],
            'metadata' => [ 'generated_at' => '2024-10-01 10:00:00' ],
        ];

        $first = $storage->save( $base_report );
        $this->assertIsArray( $first );
        $this->assertArrayHasKey( 'id', $first );
        $this->assertArrayHasKey( 'permalink', $first );

    $GLOBALS['tew_stub_posts'][ $first['id'] ]['post_date_gmt'] = '2024-10-01 10:00:00';

        $second_report           = $base_report;
        $second_report['summary'] = [ 'score' => 91 ];
        $second_report['metadata']['generated_at'] = '2024-10-02 09:15:00';

        $second = $storage->save( $second_report );
        $this->assertNotSame( $first['id'], $second['id'] );

    $GLOBALS['tew_stub_posts'][ $second['id'] ]['post_date_gmt'] = '2024-10-02 09:15:00';

        $history = $storage->recent_for_url( $base_report['url'], 5 );
        $this->assertCount( 2, $history );
        $this->assertSame( $second['id'], $history[0]['id'] );
        $this->assertSame( '2024-10-02 09:15:00', $history[0]['generated'] );

        $found = $storage->find( $second['id'] );
        $this->assertIsArray( $found );
        $this->assertSame( $second['id'], $found['metadata']['report_id'] );
        $this->assertSame( $second['permalink'], $found['metadata']['share_url'] );
        $this->assertCount( 2, $found['metadata']['history'] );
    }
}
