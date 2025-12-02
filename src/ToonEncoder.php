<?php

namespace PhpToon;

use PhpToon\Support\EncodeOptions;

class ToonEncoder
{
    private EncodeOptions $options;

    public function __construct(?EncodeOptions $options = null)
    {
        $this->options = $options ?? new EncodeOptions();
    }

    /**
     * Encode data to TOON format
     *
     * @param mixed $data
     * @param EncodeOptions|null $options
     * @return string
     */
    public static function encode(mixed $data, ?EncodeOptions $options = null): string
    {
        $encoder = new self($options);
        return $encoder->encodeValue($data, 0);
    }

    /**
     * Encode a value based on its type
     */
    private function encodeValue(mixed $value, int $depth): string
    {
        return match (true) {
            is_null($value) => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) || is_float($value) => (string) $value,
            is_string($value) => $this->encodeString($value),
            is_array($value) => $this->encodeArray($value, $depth),
            is_object($value) => $this->encodeObject($value, $depth),
            default => throw new \InvalidArgumentException('Unsupported type: ' . get_debug_type($value)),
        };
    }

    /**
     * Encode a string with proper quoting rules
     */
    private function encodeString(string $value): string
    {
        // Quote if empty, contains delimiter, control chars, or is ambiguous
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
     * Encode an array
     */
    private function encodeArray(array $array, int $depth): string
    {
        if (empty($array)) {
            return '[0]';
        }

        // Check if it's an associative array (object)
        if (!$this->isSequentialArray($array)) {
            return $this->encodeObject((object) $array, $depth);
        }

        // Check if it's a tabular array (uniform objects)
        if ($this->isTabularArray($array)) {
            return $this->encodeTabularArray($array, $depth);
        }

        // Regular array
        return $this->encodeRegularArray($array, $depth);
    }

    /**
     * Encode an object
     */
    private function encodeObject(object|array $object, int $depth): string
    {
        $data = $this->normalizeObject($object);

        if (empty($data)) {
            return '{}';
        }

        // Sort keys for deterministic output
        ksort($data);

        $indent = str_repeat($this->options->indent, $depth);
        $nextIndent = str_repeat($this->options->indent, $depth + 1);

        $lines = [];
        foreach ($data as $key => $value) {
            $encodedValue = $this->encodeValue($value, $depth + 1);

            // If value is multiline, put it on next line
            if (str_contains($encodedValue, "\n")) {
                $lines[] = $nextIndent . $key . ':';
                $lines[] = $this->indentLines($encodedValue, $depth + 1);
            } else {
                $lines[] = $nextIndent . $key . ': ' . $encodedValue;
            }
        }

        return "{\n" . implode("\n", $lines) . "\n" . $indent . '}';
    }

    /**
     * Encode a regular (non-tabular) array
     */
    private function encodeRegularArray(array $array, int $depth): string
    {
        $count = count($array);

        if ($this->options->lengthMarker) {
            $result = "[{$count}]:\n";
        } else {
            $result = "[{$count}]\n";
        }

        $indent = str_repeat($this->options->indent, $depth + 1);

        foreach ($array as $value) {
            $encoded = $this->encodeValue($value, $depth + 1);
            if (str_contains($encoded, "\n")) {
                $result .= $this->indentLines($encoded, $depth + 1) . "\n";
            } else {
                $result .= $indent . $encoded . "\n";
            }
        }

        return rtrim($result);
    }

    /**
     * Encode a tabular array (array of uniform objects)
     */
    private function encodeTabularArray(array $array, int $depth): string
    {
        $count = count($array);
        $fields = $this->getTabularFields($array);

        $fieldsStr = implode($this->options->delimiter, $fields);
        $result = "[{$count}]{{$fieldsStr}}:\n";

        $indent = str_repeat($this->options->indent, $depth + 1);

        foreach ($array as $item) {
            $values = [];
            foreach ($fields as $field) {
                $value = is_array($item) ? ($item[$field] ?? null) : ($item->$field ?? null);
                $values[] = $this->encodeValue($value, 0);
            }
            $result .= $indent . implode($this->options->delimiter, $values) . "\n";
        }

        return rtrim($result);
    }

    /**
     * Check if array is sequential (numeric keys starting from 0)
     */
    private function isSequentialArray(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Check if array is tabular (uniform array of objects with primitive values)
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

        // Check all items have same fields and only primitive values
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
     * Get field names from an object or array
     */
    private function getFields(object|array $item): array
    {
        if (is_array($item)) {
            $keys = array_keys($item);
            sort($keys);
            return $keys;
        }

        $data = $this->normalizeObject($item);
        $keys = array_keys($data);
        sort($keys);
        return $keys;
    }

    /**
     * Get fields for tabular array
     */
    private function getTabularFields(array $array): array
    {
        if (empty($array)) {
            return [];
        }

        return $this->getFields(reset($array));
    }

    /**
     * Normalize object to associative array
     */
    private function normalizeObject(object|array $object): array
    {
        if (is_array($object)) {
            return $object;
        }

        // Handle stdClass
        if ($object instanceof \stdClass) {
            return (array) $object;
        }

        // Use reflection for other objects
        $result = [];
        $reflection = new \ReflectionClass($object);

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();

            // Check for json attribute (PHP 8+)
            if (method_exists($property, 'getAttributes')) {
                foreach ($property->getAttributes() as $attribute) {
                    if ($attribute->getName() === 'JsonProperty') {
                        $args = $attribute->getArguments();
                        $name = $args[0] ?? $name;
                        break;
                    }
                }
            }

            $result[$name] = $property->getValue($object);
        }

        return $result;
    }

    /**
     * Indent all lines in a string
     */
    private function indentLines(string $text, int $depth): string
    {
        $indent = str_repeat($this->options->indent, $depth);
        $lines = explode("\n", $text);
        return implode("\n", array_map(fn($line) => $indent . $line, $lines));
    }
}
