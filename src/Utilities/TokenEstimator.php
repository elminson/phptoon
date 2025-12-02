<?php

namespace PhpToon\Utilities;

class TokenEstimator
{
    /**
     * Estimate token count using 4-character-per-token heuristic
     * This is a rough approximation commonly used for LLM token estimation
     *
     * @param string $text
     * @return int
     */
    public static function estimate(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Estimate tokens with detailed breakdown
     *
     * @param string $text
     * @return array{tokens: int, characters: int, ratio: float}
     */
    public static function estimateDetailed(string $text): array
    {
        $characters = mb_strlen($text);
        $tokens = self::estimate($text);

        return [
            'tokens' => $tokens,
            'characters' => $characters,
            'ratio' => $characters > 0 ? $characters / $tokens : 0,
        ];
    }

    /**
     * Compare token counts between two texts
     *
     * @param string $text1
     * @param string $text2
     * @return array{text1_tokens: int, text2_tokens: int, difference: int, savings_percent: float}
     */
    public static function compare(string $text1, string $text2): array
    {
        $tokens1 = self::estimate($text1);
        $tokens2 = self::estimate($text2);
        $difference = $tokens1 - $tokens2;
        $savingsPercent = $tokens1 > 0 ? (($difference / $tokens1) * 100) : 0;

        return [
            'text1_tokens' => $tokens1,
            'text2_tokens' => $tokens2,
            'difference' => $difference,
            'savings_percent' => round($savingsPercent, 2),
        ];
    }
}
