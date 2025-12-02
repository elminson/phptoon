<?php

namespace PhpToon\Tests;

use PHPUnit\Framework\TestCase;
use PhpToon\Utilities\TokenEstimator;
use PhpToon\Utilities\ToonComparison;

class UtilitiesTest extends TestCase
{
    public function test_token_estimator(): void
    {
        $text = 'Hello World';
        $tokens = TokenEstimator::estimate($text);

        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
        // "Hello World" is 11 chars, should be ~3 tokens
        $this->assertEquals(3, $tokens);
    }

    public function test_token_estimator_detailed(): void
    {
        $text = 'Test';
        $result = TokenEstimator::estimateDetailed($text);

        $this->assertArrayHasKey('tokens', $result);
        $this->assertArrayHasKey('characters', $result);
        $this->assertArrayHasKey('ratio', $result);
        $this->assertEquals(4, $result['characters']);
        $this->assertEquals(1, $result['tokens']);
    }

    public function test_token_estimator_compare(): void
    {
        $text1 = 'Hello World';
        $text2 = 'Hi';

        $result = TokenEstimator::compare($text1, $text2);

        $this->assertArrayHasKey('text1_tokens', $result);
        $this->assertArrayHasKey('text2_tokens', $result);
        $this->assertArrayHasKey('difference', $result);
        $this->assertArrayHasKey('savings_percent', $result);
        $this->assertGreaterThan($result['text2_tokens'], $result['text1_tokens']);
    }

    public function test_toon_comparison(): void
    {
        $data = [
            'name' => 'John',
            'age' => 30,
            'items' => [
                ['id' => 1, 'value' => 100],
                ['id' => 2, 'value' => 200],
            ],
        ];

        $result = ToonComparison::compare($data);

        $this->assertArrayHasKey('json', $result);
        $this->assertArrayHasKey('toon', $result);
        $this->assertArrayHasKey('json_tokens', $result);
        $this->assertArrayHasKey('toon_tokens', $result);
        $this->assertArrayHasKey('savings_percent', $result);
        $this->assertArrayHasKey('savings_tokens', $result);

        // TOON should use fewer tokens
        $this->assertLessThan($result['json_tokens'], $result['toon_tokens']);
    }

    public function test_toon_comparison_report(): void
    {
        $data = ['test' => 'value'];
        $report = ToonComparison::report($data);

        $this->assertIsString($report);
        $this->assertStringContainsString('TOON vs JSON Comparison', $report);
        $this->assertStringContainsString('Character Count:', $report);
        $this->assertStringContainsString('Estimated Token Count:', $report);
    }

    public function test_toon_comparison_summary(): void
    {
        $data = ['key' => 'value'];
        $summary = ToonComparison::summary($data);

        $this->assertArrayHasKey('savings_percent', $summary);
        $this->assertArrayHasKey('savings_tokens', $summary);
        $this->assertArrayHasKey('json_tokens', $summary);
        $this->assertArrayHasKey('toon_tokens', $summary);
    }
}
