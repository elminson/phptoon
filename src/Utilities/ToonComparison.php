<?php

namespace PhpToon\Utilities;

use PhpToon\ToonEncoder;

class ToonComparison
{
    /**
     * Compare TOON vs JSON encoding
     *
     * @param mixed $data
     * @return array{json: string, toon: string, json_length: int, toon_length: int, json_tokens: int, toon_tokens: int, savings_percent: float, savings_tokens: int}
     */
    public static function compare(mixed $data): array
    {
        $json = json_encode($data, JSON_PRETTY_PRINT);
        $toon = ToonEncoder::encode($data);

        $jsonLength = strlen($json);
        $toonLength = strlen($toon);

        $jsonTokens = TokenEstimator::estimate($json);
        $toonTokens = TokenEstimator::estimate($toon);

        $savingsTokens = $jsonTokens - $toonTokens;
        $savingsPercent = $jsonTokens > 0 ? (($savingsTokens / $jsonTokens) * 100) : 0;

        return [
            'json' => $json,
            'toon' => $toon,
            'json_length' => $jsonLength,
            'toon_length' => $toonLength,
            'json_tokens' => $jsonTokens,
            'toon_tokens' => $toonTokens,
            'savings_percent' => round($savingsPercent, 2),
            'savings_tokens' => $savingsTokens,
        ];
    }

    /**
     * Generate a comparison report
     *
     * @param mixed $data
     * @return string
     */
    public static function report(mixed $data): string
    {
        $comparison = self::compare($data);

        $report = "TOON vs JSON Comparison\n";
        $report .= str_repeat('=', 50) . "\n\n";

        $report .= "Character Count:\n";
        $report .= "  JSON:  {$comparison['json_length']} characters\n";
        $report .= "  TOON:  {$comparison['toon_length']} characters\n";
        $report .= "  Saved: " . ($comparison['json_length'] - $comparison['toon_length']) . " characters\n\n";

        $report .= "Estimated Token Count:\n";
        $report .= "  JSON:  {$comparison['json_tokens']} tokens\n";
        $report .= "  TOON:  {$comparison['toon_tokens']} tokens\n";
        $report .= "  Saved: {$comparison['savings_tokens']} tokens ({$comparison['savings_percent']}%)\n\n";

        $report .= "JSON Output:\n";
        $report .= str_repeat('-', 50) . "\n";
        $report .= $comparison['json'] . "\n\n";

        $report .= "TOON Output:\n";
        $report .= str_repeat('-', 50) . "\n";
        $report .= $comparison['toon'] . "\n";

        return $report;
    }

    /**
     * Get summary statistics only
     *
     * @param mixed $data
     * @return array{savings_percent: float, savings_tokens: int, json_tokens: int, toon_tokens: int}
     */
    public static function summary(mixed $data): array
    {
        $comparison = self::compare($data);

        return [
            'savings_percent' => $comparison['savings_percent'],
            'savings_tokens' => $comparison['savings_tokens'],
            'json_tokens' => $comparison['json_tokens'],
            'toon_tokens' => $comparison['toon_tokens'],
        ];
    }
}
