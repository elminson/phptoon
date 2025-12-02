<?php

namespace PhpToon;

use Generator;

/**
 * Streaming TOON decoder using PHP generators
 * Decodes large TOON files without loading everything into memory
 */
class ToonStreamDecoder
{
    /**
     * Stream decode TOON file line by line
     * Yields decoded values as they are parsed
     *
     * @param string $filepath
     * @return Generator<mixed>
     */
    public static function streamFromFile(string $filepath): Generator
    {
        if (!file_exists($filepath)) {
            throw new \RuntimeException("File not found: {$filepath}");
        }

        $handle = fopen($filepath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Failed to open file: {$filepath}");
        }

        try {
            yield from self::streamFromHandle($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Stream decode from file handle
     *
     * @param resource $handle
     * @return Generator<mixed>
     */
    public static function streamFromHandle($handle): Generator
    {
        $buffer = '';
        $lineNumber = 0;

        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line === false) {
                break;
            }

            $lineNumber++;
            $buffer .= $line;

            // Try to parse complete values from buffer
            $parsed = self::tryParseValue($buffer);
            if ($parsed !== null) {
                yield $parsed['value'];
                $buffer = $parsed['remaining'];
            }
        }

        // Parse any remaining buffer
        if (trim($buffer) !== '') {
            $parsed = self::tryParseValue($buffer, true);
            if ($parsed !== null) {
                yield $parsed['value'];
            }
        }
    }

    /**
     * Stream decode tabular array from file
     * Optimized for reading large tabular datasets
     *
     * @param string $filepath
     * @return Generator<array>
     */
    public static function streamTabularFromFile(string $filepath): Generator
    {
        if (!file_exists($filepath)) {
            throw new \RuntimeException("File not found: {$filepath}");
        }

        $handle = fopen($filepath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Failed to open file: {$filepath}");
        }

        try {
            // Read first line to get array declaration and fields
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                return;
            }

            // Parse array declaration: [N]{field1,field2,...}:
            if (!preg_match('/\[(\d+|\-)\]\{([^}]+)\}:/', $firstLine, $matches)) {
                fclose($handle);
                throw new \RuntimeException("Not a valid tabular TOON format");
            }

            $declaredLength = $matches[1] === '-' ? null : (int) $matches[1];
            $fields = array_map('trim', explode(',', $matches[2]));

            $rowCount = 0;

            // Stream each row
            while (!feof($handle)) {
                $line = fgets($handle);
                if ($line === false || trim($line) === '') {
                    break;
                }

                $values = self::parseTabularRow($line, count($fields));

                $row = [];
                foreach ($fields as $index => $field) {
                    $row[$field] = $values[$index] ?? null;
                }

                yield $row;
                $rowCount++;

                if ($declaredLength !== null && $rowCount >= $declaredLength) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Stream decode array items (one per line)
     *
     * @param string $filepath
     * @return Generator<mixed>
     */
    public static function streamArrayFromFile(string $filepath): Generator
    {
        if (!file_exists($filepath)) {
            throw new \RuntimeException("File not found: {$filepath}");
        }

        $handle = fopen($filepath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Failed to open file: {$filepath}");
        }

        try {
            // Skip first line (array declaration)
            $firstLine = fgets($handle);

            // Stream each element
            while (!feof($handle)) {
                $line = fgets($handle);
                if ($line === false || trim($line) === '') {
                    break;
                }

                $value = self::parseSimpleValue(trim($line));
                yield $value;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Try to parse a complete value from buffer
     *
     * @param string $buffer
     * @param bool $force Force parsing even if incomplete
     * @return array|null ['value' => mixed, 'remaining' => string] or null
     */
    private static function tryParseValue(string $buffer, bool $force = false): ?array
    {
        $trimmed = trim($buffer);

        if ($trimmed === '') {
            return null;
        }

        // Try to detect complete value
        $char = $trimmed[0];

        if ($char === '{') {
            // Object - check for closing brace
            $depth = 0;
            $pos = 0;
            $inString = false;

            for ($i = 0; $i < strlen($trimmed); $i++) {
                $c = $trimmed[$i];

                if ($c === '"' && ($i === 0 || $trimmed[$i - 1] !== '\\')) {
                    $inString = !$inString;
                }

                if (!$inString) {
                    if ($c === '{') {
                        $depth++;
                    } elseif ($c === '}') {
                        $depth--;
                        if ($depth === 0) {
                            $pos = $i + 1;
                            break;
                        }
                    }
                }
            }

            if ($pos > 0 || $force) {
                $valuePart = substr($trimmed, 0, $pos);
                $remaining = substr($trimmed, $pos);

                try {
                    $value = ToonDecoder::decode($valuePart);
                    return ['value' => $value, 'remaining' => $remaining];
                } catch (\Exception $e) {
                    return null;
                }
            }
        } elseif ($char === '[') {
            // Array - check for complete array
            // This is complex, so we'll use a simpler heuristic
            // If we see double newline or end of buffer, consider it complete
            if ($force || str_contains($buffer, "\n\n")) {
                try {
                    $value = ToonDecoder::decode($trimmed);
                    return ['value' => $value, 'remaining' => ''];
                } catch (\Exception $e) {
                    return null;
                }
            }
        } else {
            // Simple value - one line
            $pos = strpos($trimmed, "\n");
            if ($pos !== false || $force) {
                $valuePart = $pos !== false ? substr($trimmed, 0, $pos) : $trimmed;
                $remaining = $pos !== false ? substr($trimmed, $pos + 1) : '';

                $value = self::parseSimpleValue($valuePart);
                return ['value' => $value, 'remaining' => $remaining];
            }
        }

        return null;
    }

    /**
     * Parse a simple value (primitive)
     */
    private static function parseSimpleValue(string $value): mixed
    {
        $value = trim($value);

        if ($value === 'null') {
            return null;
        }

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        if ($value[0] === '"' && $value[strlen($value) - 1] === '"') {
            return stripcslashes(substr($value, 1, -1));
        }

        if (is_numeric($value)) {
            if (str_contains($value, '.')) {
                return (float) $value;
            }
            return (int) $value;
        }

        return $value;
    }

    /**
     * Parse a tabular row
     */
    private static function parseTabularRow(string $line, int $fieldCount): array
    {
        $values = [];
        $buffer = '';
        $inQuote = false;
        $escapeNext = false;

        $line = trim($line);

        for ($i = 0; $i < strlen($line); $i++) {
            $char = $line[$i];

            if ($escapeNext) {
                $buffer .= self::unescapeChar($char);
                $escapeNext = false;
                continue;
            }

            if ($char === '\\') {
                $escapeNext = true;
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                continue;
            }

            if (!$inQuote && $char === ',') {
                $values[] = self::parseSimpleValue(trim($buffer));
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if ($buffer !== '') {
            $values[] = self::parseSimpleValue(trim($buffer));
        }

        return $values;
    }

    /**
     * Unescape character
     */
    private static function unescapeChar(string $char): string
    {
        return match ($char) {
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            '\\' => '\\',
            '"' => '"',
            default => $char,
        };
    }
}
