<?php

namespace PhpToon\Utilities;

/**
 * Flattens nested data structures for optimal TOON encoding
 */
class DataFlattener
{
    /**
     * Flatten nested arrays into tabular structure when possible
     */
    public static function flatten(array $data, string $separator = '.'): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value) && !self::isSequentialArray($value)) {
                $flattened = self::flattenRecursive($value, $key, $separator);
                $result = array_merge($result, $flattened);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Optimize data for TOON by detecting and restructuring for tabular encoding
     */
    public static function optimizeForToon(array $data): array
    {
        if (self::isTabularCandidate($data)) {
            return $data; // Already optimal
        }

        // Try to convert nested structures to tabular
        $optimized = [];

        foreach ($data as $key => $value) {
            if (is_array($value) && self::isTabularCandidate($value)) {
                $optimized[$key] = $value;
            } elseif (is_array($value)) {
                $optimized[$key] = self::optimizeForToon($value);
            } else {
                $optimized[$key] = $value;
            }
        }

        return $optimized;
    }

    /**
     * Analyze data structure and suggest optimizations
     */
    public static function analyze(mixed $data): array
    {
        $stats = [
            'total_fields' => 0,
            'nested_objects' => 0,
            'arrays' => 0,
            'tabular_arrays' => 0,
            'primitives' => 0,
            'optimization_potential' => 0,
        ];

        self::analyzeRecursive($data, $stats);

        // Calculate optimization potential (0-100)
        if ($stats['arrays'] > 0) {
            $stats['optimization_potential'] = (int) (($stats['tabular_arrays'] / $stats['arrays']) * 100);
        }

        $stats['suggestions'] = self::generateSuggestions($stats);

        return $stats;
    }

    private static function flattenRecursive(array $array, string $prefix, string $separator): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix . $separator . $key;

            if (is_array($value) && !self::isSequentialArray($value)) {
                $result = array_merge($result, self::flattenRecursive($value, $newKey, $separator));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    private static function isSequentialArray(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }

    private static function isTabularCandidate(array $data): bool
    {
        if (!self::isSequentialArray($data) || empty($data)) {
            return false;
        }

        $first = reset($data);
        if (!is_array($first)) {
            return false;
        }

        $fields = array_keys($first);
        foreach ($data as $item) {
            if (!is_array($item) || array_keys($item) !== $fields) {
                return false;
            }
        }

        return true;
    }

    private static function analyzeRecursive(mixed $value, array &$stats): void
    {
        if (is_array($value)) {
            if (self::isSequentialArray($value)) {
                $stats['arrays']++;
                if (self::isTabularCandidate($value)) {
                    $stats['tabular_arrays']++;
                }

                foreach ($value as $item) {
                    self::analyzeRecursive($item, $stats);
                }
            } else {
                $stats['nested_objects']++;
                $stats['total_fields'] += count($value);

                foreach ($value as $item) {
                    self::analyzeRecursive($item, $stats);
                }
            }
        } else {
            $stats['primitives']++;
        }
    }

    private static function generateSuggestions(array $stats): array
    {
        $suggestions = [];

        if ($stats['optimization_potential'] < 50 && $stats['arrays'] > 0) {
            $suggestions[] = "Consider restructuring data to have more uniform arrays";
        }

        if ($stats['nested_objects'] > 10) {
            $suggestions[] = "High nesting detected. Consider flattening some structures";
        }

        if ($stats['tabular_arrays'] === 0 && $stats['arrays'] > 0) {
            $suggestions[] = "No tabular arrays detected. TOON savings may be minimal";
        }

        if ($stats['tabular_arrays'] > 0) {
            $suggestions[] = "Good! {$stats['tabular_arrays']} tabular arrays will benefit from TOON encoding";
        }

        return $suggestions;
    }
}
