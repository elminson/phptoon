<?php

namespace PhpToon;

use PhpToon\Exceptions\ToonDecodeException;

class ToonDecoder
{
    private string $input;
    private int $position = 0;
    private int $length = 0;
    private int $line = 1;
    private int $column = 1;

    /**
     * Decode TOON format to PHP data structure
     *
     * @param string $toon
     * @return mixed
     * @throws ToonDecodeException
     */
    public static function decode(string $toon): mixed
    {
        $decoder = new self();
        return $decoder->decodeString($toon);
    }

    /**
     * Decode TOON string
     */
    private function decodeString(string $input): mixed
    {
        $this->input = $input;
        $this->length = strlen($input);
        $this->position = 0;
        $this->line = 1;
        $this->column = 1;

        $this->skipWhitespace();

        if ($this->position >= $this->length) {
            throw new ToonDecodeException('Empty input', $this->line, $this->column);
        }

        $result = $this->decodeValue();
        $this->skipWhitespace();

        if ($this->position < $this->length) {
            throw new ToonDecodeException('Unexpected content after value', $this->line, $this->column);
        }

        return $result;
    }

    /**
     * Decode a value
     */
    private function decodeValue(): mixed
    {
        $this->skipWhitespace();

        if ($this->position >= $this->length) {
            throw new ToonDecodeException('Unexpected end of input', $this->line, $this->column);
        }

        $char = $this->input[$this->position];

        return match ($char) {
            '{' => $this->decodeObject(),
            '[' => $this->decodeArray(),
            '"' => $this->decodeQuotedString(),
            default => $this->decodeUnquotedValue(),
        };
    }

    /**
     * Decode an object
     */
    private function decodeObject(): array
    {
        $result = [];
        $this->consume('{');
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

            // Read key
            $key = $this->readKey();
            $this->skipWhitespace();
            $this->consume(':');
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
        }

        return $result;
    }

    /**
     * Decode an array
     */
    private function decodeArray(): array
    {
        $this->consume('[');
        $length = $this->readInteger();
        $this->consume(']');

        $this->skipWhitespace();

        // Check for tabular format
        if ($this->peek() === '{') {
            return $this->decodeTabularArray($length);
        }

        // Check for colon
        if ($this->peek() === ':') {
            $this->consume(':');
        }

        $this->skipWhitespace();

        $result = [];
        for ($i = 0; $i < $length; $i++) {
            $this->skipWhitespace();
            $result[] = $this->decodeValue();
            $this->skipWhitespace();
        }

        return $result;
    }

    /**
     * Decode tabular array
     */
    private function decodeTabularArray(int $length): array
    {
        $this->consume('{');
        $fields = $this->readFields();
        $this->consume('}');
        $this->skipWhitespace();

        if ($this->peek() === ':') {
            $this->consume(':');
        }

        $this->skipWhitespace();

        $result = [];
        for ($i = 0; $i < $length; $i++) {
            $this->skipWhitespace();
            $values = $this->readTabularRow(count($fields));

            $row = [];
            foreach ($fields as $index => $field) {
                $row[$field] = $values[$index] ?? null;
            }
            $result[] = $row;

            $this->skipWhitespace();
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
                $fields[] = trim($buffer);
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
        $fieldIndex = 0;

        while ($this->position < $this->length && $fieldIndex < $fieldCount) {
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
                $fieldIndex++;
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
     * Decode quoted string
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

            $result .= $char;
            $this->advance();
        }

        throw new ToonDecodeException('Unterminated string', $this->line, $this->column);
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
        if ($value === 'null') {
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

            if ($char === ':') {
                break;
            }

            if ($char === "\n" || $char === "\r") {
                break;
            }

            $buffer .= $char;
            $this->advance();
        }

        return trim($buffer);
    }

    /**
     * Read an integer
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
            throw new ToonDecodeException('Expected integer', $this->line, $this->column);
        }

        return (int) $buffer;
    }

    /**
     * Skip whitespace (spaces and tabs only, not newlines for structure)
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
     * Consume expected character
     */
    private function consume(string $expected): void
    {
        if ($this->position >= $this->length) {
            throw new ToonDecodeException(
                "Expected '{$expected}' but reached end of input",
                $this->line,
                $this->column
            );
        }

        $char = $this->input[$this->position];

        if ($char !== $expected) {
            throw new ToonDecodeException(
                "Expected '{$expected}' but got '{$char}'",
                $this->line,
                $this->column
            );
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
}
