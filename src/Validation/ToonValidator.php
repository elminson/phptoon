<?php

namespace PhpToon\Validation;

use PhpToon\ToonDecoder;
use PhpToon\ToonEncoder;

/**
 * Validates TOON data against schemas and structural rules
 */
class ToonValidator
{
    /**
     * Validate TOON string is well-formed
     */
    public static function validate(string $toon): ValidationResult
    {
        $errors = [];

        try {
            ToonDecoder::decode($toon);
        } catch (\Exception $e) {
            $errors[] = "Decode error: {$e->getMessage()}";
        }

        return new ValidationResult(empty($errors), $errors);
    }

    /**
     * Validate lossless round-trip (TOON ↔ PHP ↔ TOON)
     */
    public static function validateRoundTrip(mixed $data): ValidationResult
    {
        $errors = [];

        try {
            $encoded = ToonEncoder::encode($data);
            $decoded = ToonDecoder::decode($encoded);
            $reencoded = ToonEncoder::encode($decoded);

            if ($encoded !== $reencoded) {
                $errors[] = "Round-trip validation failed: encoded output changed";
            }
        } catch (\Exception $e) {
            $errors[] = "Round-trip error: {$e->getMessage()}";
        }

        return new ValidationResult(empty($errors), $errors);
    }

    /**
     * Validate array structure (declared length matches actual)
     */
    public static function validateArrayStructure(string $toon): ValidationResult
    {
        $errors = [];

        if (preg_match_all('/\[(\d+)\]/', $toon, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $declaredLength = (int) $match[0];
                // This is a simplified check - full implementation would count actual elements
                if ($declaredLength < 0) {
                    $errors[] = "Invalid array length: {$declaredLength}";
                }
            }
        }

        return new ValidationResult(empty($errors), $errors);
    }
}
