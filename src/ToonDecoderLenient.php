<?php

namespace PhpToon;

use PhpToon\Exceptions\ToonDecodeException;

/**
 * Lenient TOON decoder with error recovery
 * Attempts to parse malformed TOON input by making reasonable assumptions
 */
class ToonDecoderLenient
{
    private string $input;
    private int $position = 0;
    private int $length = 0;
    private int $line = 1;
    private int $column = 1;
    private array $errors = [];

    /**
     * Decode TOON format with lenient parsing
     * Returns partial results even if some parts fail to parse
     *
     * @param string $toon
     * @param array &$errors Output parameter for collected errors
     * @return mixed
     */
    public static function decode(string $toon, ?array &$errors = null): mixed
    {
        $decoder = new self();
        $result = $decoder->decodeString($toon);
        $errors = $decoder->errors;
        return $result;
    }

    /**
     * Decode TOON string with error recovery
     */
    private function decodeString(string $input): mixed
    {
        $this->input = $input;
        $this->length = strlen($input);
        $this->position = 0;
        $this->line = 1;
        $this->column = 1;
        $this->errors = [];

        $this->skipWhitespace();

        if ($this->position >= $this->length) {
            $this->addError('Empty input, returning null');
            return null;
        }

        try {
            return $this->decodeValue();
        } catch (\Exception $e) {
            $this->addError("Failed to decode: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Decode a value with error recovery
     */
    private function decodeValue(): mixed
    {
        $this->skipWhitespace();

        if ($this->position >= $this->length) {
            return null;
        }

        $char = $this->input[$this->position];

        try {
            return match ($char) {
                '{' => $this->decodeObject(),
                '[' => $this->decodeArray(),
                '"' => $this->decodeQuotedString(),
                default => $this->decodeUnquotedValue(),
            };
        } catch (\Exception $e) {
            $this->addError("Error at line {$this->line}: {$e->getMessage()}");
            $this->skipToNextValue();
            return null;
        }
    }

    /**
     * Decode an object with error recovery
     */
    private function decodeObject(): array
    {
        $result = [];

        try {
            $this->consume('{');
        } catch (\Exception $e) {
            $this->addError("Missing opening brace, attempting recovery");
        }

        $this->skipWhitespace();

        if ($this->peek() === '}') {
            $this->consume('}');
            return $result;
        }

        while ($this->position < $this->length) {
            $this->skipWhitespace();

            if ($this->peek() === '}') {
                $this->consume('}');
                break;
            }

            if ($this->position >= $this->length) {
                $this->addError("Unexpected end of object, closing implicitly");
                break;
            }

            try {
                // Read key
                $key = $this->readKey();
                $this->skipWhitespace();

                // Colon might be missing
                if ($this->peek() === ':') {
                    $this->consume(':');
                } else {
                    $this->addError("Missing colon after key '{$key}', assuming it's there");
                }

                $this->skipWhitespace();

                // Check if value is on next line
                if ($this->peek() === "\n") {
                    $this->advance();
                    $this->skipWhitespace();
                }

                // Read value
                $value = $this->decodeValue();
                $result[$key] = $value;

                $this->skipWhitespace();
            } catch (\Exception $e) {
                $this->addError("Error parsing object field: {$e->getMessage()}");
                $this->skipToNextField();
            }
        }

        return $result;
    }

    /**
     * Decode an array with error recovery
     */
    private function decodeArray(): array
    {
        try {
            $this->consume('[');
        } catch (\Exception $e) {
            $this->addError("Missing opening bracket");
            return [];
        }

        $declaredLength = null;
        try {
            $declaredLength = $this->readInteger();
            $this->consume(']');
        } catch (\Exception $e) {
            $this->addError("Invalid array length declaration, continuing anyway");
            // Try to find closing bracket
            while ($this->position < $this->length && $this->peek() !== ']') {
                $this->advance();
            }
            if ($this->peek() === ']') {
                $this->advance();
            }
        }

        $this->skipWhitespace();

        // Check for tabular format
        if ($this->peek() === '{') {
            return $this->decodeTabularArray($declaredLength);
        }

        // Check for colon
        if ($this->peek() === ':') {
            $this->consume(':');
        }

        $this->skipWhitespace();

        $result = [];
        $actualLength = 0;

        while ($this->position < $this->length && $actualLength < ($declaredLength ?? PHP_INT_MAX)) {
            $this->skipWhitespace();

            // Check for array-like structures that might indicate we're done
            if (in_array($this->peek(), ['{', '[', '}', ']', null])) {
                break;
            }

            try {
                $value = $this->decodeValue();
                $result[] = $value;
                $actualLength++;
            } catch (\Exception $e) {
                $this->addError("Error parsing array element: {$e->getMessage()}");
                $this->skipToNextValue();
                break;
            }

            $this->skipWhitespace();
        }

        // Validate length
        if ($declaredLength !== null && $actualLength !== $declaredLength) {
            $this->addError("Array length mismatch: declared {$declaredLength}, got {$actualLength}");
        }

        return $result;
    }

    /**
     * Decode tabular array with error recovery
     */
    private function decodeTabularArray(?int $declaredLength): array
    {
        try {
            $this->consume('{');
        } catch (\Exception $e) {
            $this->addError("Missing opening brace for fields");
        }

        $fields = [];
        try {
            $fields = $this->readFields();
            $this->consume('}');
        } catch (\Exception $e) {
            $this->addError("Error reading field names: {$e->getMessage()}");
        }

        $this->skipWhitespace();

        if ($this->peek() === ':') {
            $this->consume(':');
        }

        $this->skipWhitespace();

        $result = [];
        $rowCount = 0;

        while ($this->position < $this->length && $rowCount < ($declaredLength ?? PHP_INT_MAX)) {
            $this->skipWhitespace();

            if (in_array($this->peek(), ['{', '}', null])) {
                break;
            }

            try {
                $values = $this->readTabularRow(count($fields));

                $row = [];
                foreach ($fields as $index => $field) {
                    $row[$field] = $values[$index] ?? null;
                }

                // Warn if field count mismatch
                if (count($values) !== count($fields)) {
                    $this->addError("Row {$rowCount}: expected " . count($fields) . " values, got " . count($values));
                }

                $result[] = $row;
                $rowCount++;
            } catch (\Exception $e) {
                $this->addError("Error parsing row {$rowCount}: {$e->getMessage()}");
                $this->skipToNextLine();
            }

            $this->skipWhitespace();
        }

        if ($declaredLength !== null && $rowCount !== $declaredLength) {
            $this->addError("Tabular array length mismatch: declared {$declaredLength}, got {$rowCount}");
        }

        return $result;
    }

    /**
     * Read fields from tabular header
     */
    private function readFields(): array
    {
        $fields = [];
        $buffer = '';

        while ($this->position < $this->length) {
            $char = $this->peek();

            if ($char === '}') {
                if ($buffer !== '') {
                    $fields[] = trim($buffer);
                }
                break;
            }

            if ($char === ',') {
                if ($buffer !== '') {
                    $fields[] = trim($buffer);
                }
                $buffer = '';
                $this->advance();
                continue;
            }

            $buffer .= $char;
            $this->advance();
        }

        return $fields;
    }

    /**
     * Read tabular row values
     */
    private function readTabularRow(int $fieldCount): array
    {
        $values = [];
        $buffer = '';
        $inQuote = false;
        $escapeNext = false;

        while ($this->position < $this->length) {
            $char = $this->peek();

            if ($escapeNext) {
                $buffer .= $this->unescapeChar($char);
                $escapeNext = false;
                $this->advance();
                continue;
            }

            if ($char === '\\') {
                $escapeNext = true;
                $this->advance();
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                $this->advance();
                continue;
            }

            if (!$inQuote && $char === ',') {
                $values[] = $this->parseUnquotedValue(trim($buffer));
                $buffer = '';
                $this->advance();
                continue;
            }

            if (!$inQuote && ($char === "\n" || $char === "\r")) {
                if ($buffer !== '') {
                    $values[] = $this->parseUnquotedValue(trim($buffer));
                }
                break;
            }

            $buffer .= $char;
            $this->advance();
        }

        if ($buffer !== '') {
            $values[] = $this->parseUnquotedValue(trim($buffer));
        }

        return $values;
    }

    /**
     * Decode quoted string (lenient - allows unclosed quotes)
     */
    private function decodeQuotedString(): string
    {
        $this->consume('"');
        $result = '';
        $escapeNext = false;

        while ($this->position < $this->length) {
            $char = $this->peek();

            if ($escapeNext) {
                $result .= $this->unescapeChar($char);
                $escapeNext = false;
                $this->advance();
                continue;
            }

            if ($char === '\\') {
                $escapeNext = true;
                $this->advance();
                continue;
            }

            if ($char === '"') {
                $this->advance();
                return $result;
            }

            if ($char === "\n") {
                $this->addError("Unterminated string at line {$this->line}, closing implicitly");
                return $result;
            }

            $result .= $char;
            $this->advance();
        }

        $this->addError("Unterminated string, reached end of input");
        return $result;
    }

    /**
     * Decode unquoted value
     */
    private function decodeUnquotedValue(): mixed
    {
        $buffer = '';

        while ($this->position < $this->length) {
            $char = $this->peek();

            if (in_array($char, ["\n", "\r", ',', ':', '}', ']'])) {
                break;
            }

            $buffer .= $char;
            $this->advance();
        }

        return $this->parseUnquotedValue(trim($buffer));
    }

    /**
     * Parse unquoted value to appropriate type
     */
    private function parseUnquotedValue(string $value): mixed
    {
        if ($value === '' || $value === 'null') {
            return null;
        }

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        if (is_numeric($value)) {
            if (str_contains($value, '.') || str_contains($value, 'e') || str_contains($value, 'E')) {
                return (float) $value;
            }
            return (int) $value;
        }

        return $value;
    }

    /**
     * Read a key
     */
    private function readKey(): string
    {
        $buffer = '';

        while ($this->position < $this->length) {
            $char = $this->peek();

            if ($char === ':' || $char === "\n" || $char === "\r") {
                break;
            }

            $buffer .= $char;
            $this->advance();
        }

        return trim($buffer);
    }

    /**
     * Read an integer (lenient)
     */
    private function readInteger(): int
    {
        $buffer = '';

        while ($this->position < $this->length) {
            $char = $this->peek();

            if (!ctype_digit($char)) {
                break;
            }

            $buffer .= $char;
            $this->advance();
        }

        if ($buffer === '') {
            return 0; // Default to 0 if no number found
        }

        return (int) $buffer;
    }

    /**
     * Skip to next value
     */
    private function skipToNextValue(): void
    {
        while ($this->position < $this->length) {
            $char = $this->peek();
            if ($char === "\n" || in_array($char, ['{', '[', ','])) {
                if ($char === "\n") {
                    $this->advance();
                }
                break;
            }
            $this->advance();
        }
    }

    /**
     * Skip to next field
     */
    private function skipToNextField(): void
    {
        while ($this->position < $this->length) {
            $char = $this->peek();
            if ($char === "\n") {
                $this->advance();
                break;
            }
            $this->advance();
        }
    }

    /**
     * Skip to next line
     */
    private function skipToNextLine(): void
    {
        while ($this->position < $this->length) {
            $char = $this->peek();
            if ($char === "\n") {
                $this->advance();
                break;
            }
            $this->advance();
        }
    }

    /**
     * Skip whitespace
     */
    private function skipWhitespace(): void
    {
        while ($this->position < $this->length) {
            $char = $this->input[$this->position];

            if ($char === ' ' || $char === "\t" || $char === "\n" || $char === "\r") {
                if ($char === "\n") {
                    $this->line++;
                    $this->column = 1;
                } else {
                    $this->column++;
                }
                $this->position++;
            } else {
                break;
            }
        }
    }

    /**
     * Peek at current character
     */
    private function peek(): ?string
    {
        if ($this->position >= $this->length) {
            return null;
        }

        return $this->input[$this->position];
    }

    /**
     * Consume expected character (lenient - doesn't throw)
     */
    private function consume(string $expected): void
    {
        if ($this->position >= $this->length) {
            throw new \RuntimeException("Expected '{$expected}' but reached end of input");
        }

        $char = $this->input[$this->position];

        if ($char !== $expected) {
            throw new \RuntimeException("Expected '{$expected}' but got '{$char}'");
        }

        $this->advance();
    }

    /**
     * Advance position
     */
    private function advance(): void
    {
        if ($this->position < $this->length) {
            if ($this->input[$this->position] === "\n") {
                $this->line++;
                $this->column = 1;
            } else {
                $this->column++;
            }
            $this->position++;
        }
    }

    /**
     * Unescape character
     */
    private function unescapeChar(string $char): string
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

    /**
     * Add error to collection
     */
    private function addError(string $message): void
    {
        $this->errors[] = [
            'message' => $message,
            'line' => $this->line,
            'column' => $this->column,
        ];
    }
}
