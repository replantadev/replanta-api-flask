<?php
namespace TEW\Tests;

use TEW\Reporting\Scorecard_Builder;

class ScorecardBuilderTest extends TestCase {

    public function test_build_returns_expected_global_score() {
        $builder = new Scorecard_Builder();

        $metrics = [
            'pagespeed' => [
                'mobile'  => [
                    'score'            => 78,
                    'lcp_ms'           => 3200,
                    'tbt_ms'           => 180,
                    'inp_ms'           => 210,
                    'cls'              => 0.08,
                    'total_byte_weight'=> 2400000,
                ],
                'desktop' => [
                    'score'            => 92,
                    'lcp_ms'           => 1800,
                    'tbt_ms'           => 90,
                    'inp_ms'           => 60,
                    'cls'              => 0.04,
                    'total_byte_weight'=> 2100000,
                ],
            ],
            'websitecarbon' => [
                'cleaner_than' => 54,
                'co2_per_view' => 0.72,
                'rating'       => 'C',
            ],
            'greenweb' => [
                'is_green'  => false,
                'hosted_by' => 'Generic Host',
            ],
        ];

        $scorecard = $builder->build( 'https://example.org', $metrics );

        $this->assertEquals( 72.4, $scorecard['global_score'] );
        $this->assertCount( 4, $scorecard['components'] );
        $this->assertEquals( 0.72, $scorecard['carbon']['co2_per_1000_views_kg'] );
        $this->assertEquals( 7.2, $scorecard['carbon']['co2_per_10000_views_kg'] );
        $this->assertEqualsWithDelta( 0.34, $scorecard['carbon']['trees_for_10000_views'], 0.01 );
    $this->assertSame( 'good', $scorecard['status'] );
    }

    public function test_build_handles_minimum_inputs() {
        $builder = new Scorecard_Builder();

        $metrics = [
            'pagespeed' => [
                'mobile' => [
                    'score'  => 80,
                    'lcp_ms' => 2500,
                ],
            ],
        ];

        $scorecard = $builder->build( 'https://example.org', $metrics );

        $this->assertEquals( 80.0, $scorecard['global_score'] );
        $this->assertCount( 1, $scorecard['components'] );
        $this->assertSame( 'good', $scorecard['status'] );
    }
}
