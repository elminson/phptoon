<?php

use PhpToon\ToonEncoder;
use PhpToon\ToonDecoder;
use PhpToon\Support\EncodeOptions;
use PhpToon\Utilities\ToonComparison;
use PhpToon\Utilities\TokenEstimator;

if (!function_exists('toon')) {
    /**
     * Encode data to TOON format
     *
     * @param mixed $data
     * @param EncodeOptions|null $options
     * @return string
     */
    function toon(mixed $data, ?EncodeOptions $options = null): string
    {
        return ToonEncoder::encode($data, $options);
    }
}

if (!function_exists('toon_decode')) {
    /**
     * Decode TOON format to PHP data
     *
     * @param string $toon
     * @return mixed
     */
    function toon_decode(string $toon): mixed
    {
        return ToonDecoder::decode($toon);
    }
}

if (!function_exists('toon_compact')) {
    /**
     * Encode data to compact TOON format (no indentation)
     *
     * @param mixed $data
     * @return string
     */
    function toon_compact(mixed $data): string
    {
        return ToonEncoder::encode($data, new EncodeOptions(indent: ''));
    }
}

if (!function_exists('toon_readable')) {
    /**
     * Encode data to readable TOON format (4-space indentation)
     *
     * @param mixed $data
     * @return string
     */
    function toon_readable(mixed $data): string
    {
        return ToonEncoder::encode($data, new EncodeOptions(indent: '    '));
    }
}

if (!function_exists('toon_tabular')) {
    /**
     * Encode data to TOON format with tab delimiter
     *
     * @param mixed $data
     * @return string
     */
    function toon_tabular(mixed $data): string
    {
        return ToonEncoder::encode($data, new EncodeOptions(delimiter: "\t"));
    }
}

if (!function_exists('toon_compare')) {
    /**
     * Compare TOON vs JSON encoding for given data
     *
     * @param mixed $data
     * @return array{json: string, toon: string, json_length: int, toon_length: int, json_tokens: int, toon_tokens: int, savings_percent: float, savings_tokens: int}
     */
    function toon_compare(mixed $data): array
    {
        return ToonComparison::compare($data);
    }
}

if (!function_exists('toon_estimate_tokens')) {
    /**
     * Estimate token count for text using 4-char-per-token heuristic
     *
     * @param string $text
     * @return int
     */
    function toon_estimate_tokens(string $text): int
    {
        return TokenEstimator::estimate($text);
    }
}

if (!function_exists('toon_savings')) {
    /**
     * Calculate token savings between TOON and JSON
     *
     * @param mixed $data
     * @return array{percent: float, tokens: int}
     */
    function toon_savings(mixed $data): array
    {
        $comparison = ToonComparison::compare($data);
        return [
            'percent' => $comparison['savings_percent'],
            'tokens' => $comparison['savings_tokens'],
        ];
    }
}
