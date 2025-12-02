<?php

namespace PhpToon;

use PhpToon\Support\EncodeOptions;
use Generator;

/**
 * Streaming TOON encoder using PHP generators
 * Encodes large datasets without loading everything into memory
 */
class ToonStreamEncoder
{
    private EncodeOptions $options;

    public function __construct(?EncodeOptions $options = null)
    {
        $this->options = $options ?? new EncodeOptions();
    }

    /**
     * Stream encode iterable data to TOON format
     * Yields TOON chunks that can be written to file/output
     *
     * @param iterable $data
     * @param EncodeOptions|null $options
     * @return Generator<string>
     */
    public static function stream(iterable $data, ?EncodeOptions $options = null): Generator
    {
        $encoder = new self($options);
        yield from $encoder->streamEncode($data, 0);
    }

    /**
     * Stream encode array to TOON file
     *
     * @param iterable $data
     * @param string $filepath
     * @param EncodeOptions|null $options
     * @return int Bytes written
     */
    public static function streamToFile(iterable $data, string $filepath, ?EncodeOptions $options = null): int
    {
        $handle = fopen($filepath, 'w');
        if ($handle === false) {
            throw new \RuntimeException("Failed to open file: {$filepath}");
        }

        $bytesWritten = 0;
        foreach (self::stream($data, $options) as $chunk) {
            $bytes = fwrite($handle, $chunk);
            if ($bytes === false) {
                fclose($handle);
                throw new \RuntimeException("Failed to write to file: {$filepath}");
            }
            $bytesWritten += $bytes;
        }

        fclose($handle);
        return $bytesWritten;
    }

    /**
     * Stream encode iterable data
     *
     * @param iterable $data
     * @param int $depth
     * @return Generator<string>
     */
    private function streamEncode(iterable $data, int $depth): Generator
    {
        // Check if it's an array or traversable
        if (is_array($data)) {
            yield from $this->streamArray($data, $depth);
        } else {
            yield from $this->streamIterable($data, $depth);
        }
    }

    /**
     * Stream encode array
     */
    private function streamArray(array $array, int $depth): Generator
    {
        if (empty($array)) {
            yield '[0]';
            return;
        }

        // Check if associative
        if (!$this->isSequentialArray($array)) {
            yield from $this->streamObject($array, $depth);
            return;
        }

        // Check if tabular
        if ($this->isTabularArray($array)) {
            yield from $this->streamTabularArray($array, $depth);
            return;
        }

        // Regular array
        yield from $this->streamRegularArray($array, $depth);
    }

    /**
     * Stream encode regular array
     */
    private function streamRegularArray(array $array, int $depth): Generator
    {
        $count = count($array);

        if ($this->options->lengthMarker) {
            yield "[{$count}]:\n";
        } else {
            yield "[{$count}]\n";
        }

        $indent = str_repeat($this->options->indent, $depth + 1);

        foreach ($array as $value) {
            $encoded = $this->encodeValue($value, $depth + 1);
            if (str_contains($encoded, "\n")) {
                yield $this->indentLines($encoded, $depth + 1) . "\n";
            } else {
                yield $indent . $encoded . "\n";
            }
        }
    }

    /**
     * Stream encode tabular array
     */
    private function streamTabularArray(array $array, int $depth): Generator
    {
        $count = count($array);
        $fields = $this->getTabularFields($array);

        $fieldsStr = implode($this->options->delimiter, $fields);
        yield "[{$count}]{{$fieldsStr}}:\n";

        $indent = str_repeat($this->options->indent, $depth + 1);

        foreach ($array as $item) {
            $values = [];
            foreach ($fields as $field) {
                $value = is_array($item) ? ($item[$field] ?? null) : ($item->$field ?? null);
                $values[] = $this->encodeValue($value, 0);
            }
            yield $indent . implode($this->options->delimiter, $values) . "\n";
        }
    }

    /**
     * Stream encode iterable (Generator, Iterator, etc.)
     */
    private function streamIterable(iterable $iterable, int $depth): Generator
    {
        // Buffer first item to check type
        $buffer = [];
        $count = 0;

        foreach ($iterable as $item) {
            $buffer[] = $item;
            $count++;
            if ($count >= 2) {
                break; // Only need 2 items to check if tabular
            }
        }

        // Check if tabular
        $isTabular = $count > 0 && $this->isTabularIterable($buffer);

        if ($isTabular) {
            $fields = $this->getFields($buffer[0]);
            $fieldsStr = implode($this->options->delimiter, $fields);

            // Start with placeholder length, will need to be updated
            yield "[-]{{$fieldsStr}}:\n";

            $indent = str_repeat($this->options->indent, $depth + 1);

            // Write buffered items
            foreach ($buffer as $item) {
                $values = [];
                foreach ($fields as $field) {
                    $value = is_array($item) ? ($item[$field] ?? null) : ($item->$field ?? null);
                    $values[] = $this->encodeValue($value, 0);
                }
                yield $indent . implode($this->options->delimiter, $values) . "\n";
            }

            // Continue with remaining items
            foreach ($iterable as $item) {
                $values = [];
                foreach ($fields as $field) {
                    $value = is_array($item) ? ($item[$field] ?? null) : ($item->$field ?? null);
                    $values[] = $this->encodeValue($value, 0);
                }
                yield $indent . implode($this->options->delimiter, $values) . "\n";
            }
        } else {
            // Regular array streaming
            yield "[-]:\n";

            $indent = str_repeat($this->options->indent, $depth + 1);

            // Write buffered items
            foreach ($buffer as $value) {
                $encoded = $this->encodeValue($value, $depth + 1);
                if (str_contains($encoded, "\n")) {
                    yield $this->indentLines($encoded, $depth + 1) . "\n";
                } else {
                    yield $indent . $encoded . "\n";
                }
            }

            // Continue with remaining items
            foreach ($iterable as $value) {
                $encoded = $this->encodeValue($value, $depth + 1);
                if (str_contains($encoded, "\n")) {
                    yield $this->indentLines($encoded, $depth + 1) . "\n";
                } else {
                    yield $indent . $encoded . "\n";
                }
            }
        }
    }

    /**
     * Stream encode object
     */
    private function streamObject(array $object, int $depth): Generator
    {
        if (empty($object)) {
            yield '{}';
            return;
        }

        // Sort keys for deterministic output
        ksort($object);

        $indent = str_repeat($this->options->indent, $depth);
        $nextIndent = str_repeat($this->options->indent, $depth + 1);

        yield "{\n";

        foreach ($object as $key => $value) {
            $encodedValue = $this->encodeValue($value, $depth + 1);

            if (str_contains($encodedValue, "\n")) {
                yield $nextIndent . $key . ":\n";
                yield $this->indentLines($encodedValue, $depth + 1) . "\n";
            } else {
                yield $nextIndent . $key . ': ' . $encodedValue . "\n";
            }
        }

        yield $indent . '}';
    }

    /**
     * Encode a single value (non-streaming)
     */
    private function encodeValue(mixed $value, int $depth): string
    {
        // Use regular encoder for simple values
        return match (true) {
            is_null($value) => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) => (string) $value,
            is_float($value) => $this->encodeFloat($value),
            is_string($value) => $this->encodeString($value),
            is_array($value) => ToonEncoder::encode($value, $this->options),
            $value instanceof \DateTimeInterface => $this->encodeString($value->format(\DateTime::ATOM)),
            $value instanceof \BackedEnum => is_string($value->value) ? $this->encodeString($value->value) : (string) $value->value,
            $value instanceof \UnitEnum => $this->encodeString($value->name),
            is_object($value) => ToonEncoder::encode($value, $this->options),
            default => throw new \InvalidArgumentException('Unsupported type: ' . get_debug_type($value)),
        };
    }

    /**
     * Encode float
     */
    private function encodeFloat(float $value): string
    {
        if (is_nan($value) || is_infinite($value)) {
            return 'null';
        }
        return (string) $value;
    }

    /**
     * Encode string
     */
    private function encodeString(string $value): string
    {
        if (
            $value === '' ||
            str_contains($value, $this->options->delimiter) ||
            preg_match('/[\x00-\x1F\x7F]/', $value) ||
            in_array($value, ['true', 'false', 'null']) ||
            is_numeric($value)
        ) {
            return '"' . addcslashes($value, "\"\\\n\r\t") . '"';
        }

        return $value;
    }

    /**
     * Check if array is sequential
     */
    private function isSequentialArray(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Check if array is tabular
     */
    private function isTabularArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        $firstItem = reset($array);
        if (!is_array($firstItem) && !is_object($firstItem)) {
            return false;
        }

        $fields = $this->getFields($firstItem);
        if (empty($fields)) {
            return false;
        }

        foreach ($array as $item) {
            $itemFields = $this->getFields($item);
            if ($itemFields !== $fields) {
                return false;
            }

            foreach ($fields as $field) {
                $value = is_array($item) ? ($item[$field] ?? null) : ($item->$field ?? null);
                if (is_array($value) || is_object($value)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if iterable contains tabular data
     */
    private function isTabularIterable(array $buffer): bool
    {
        if (empty($buffer)) {
            return false;
        }

        $firstItem = $buffer[0];
        if (!is_array($firstItem) && !is_object($firstItem)) {
            return false;
        }

        $fields = $this->getFields($firstItem);
        if (empty($fields)) {
            return false;
        }

        foreach ($buffer as $item) {
            $itemFields = $this->getFields($item);
            if ($itemFields !== $fields) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get fields from item
     */
    private function getFields(mixed $item): array
    {
        if (is_array($item)) {
            $keys = array_keys($item);
            sort($keys);
            return $keys;
        }

        if (is_object($item) && $item instanceof \stdClass) {
            $keys = array_keys((array) $item);
            sort($keys);
            return $keys;
        }

        return [];
    }

    /**
     * Get tabular fields
     */
    private function getTabularFields(array $array): array
    {
        if (empty($array)) {
            return [];
        }

        return $this->getFields(reset($array));
    }

    /**
     * Indent lines
     */
    private function indentLines(string $text, int $depth): string
    {
        $indent = str_repeat($this->options->indent, $depth);
        $lines = explode("\n", $text);
        return implode("\n", array_map(fn($line) => $indent . $line, $lines));
    }
}
