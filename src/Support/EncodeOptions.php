<?php

namespace PhpToon\Support;

class EncodeOptions
{
    public string $indent = '  ';
    public string $delimiter = ',';
    public bool $lengthMarker = true;

    public function __construct(
        ?string $indent = null,
        ?string $delimiter = null,
        ?bool $lengthMarker = null
    ) {
        if ($indent !== null) {
            $this->indent = $indent;
        }
        if ($delimiter !== null) {
            $this->delimiter = $delimiter;
        }
        if ($lengthMarker !== null) {
            $this->lengthMarker = $lengthMarker;
        }
    }

    /**
     * Create options with custom indent
     */
    public static function withIndent(string $indent): self
    {
        return new self(indent: $indent);
    }

    /**
     * Create options with custom delimiter
     */
    public static function withDelimiter(string $delimiter): self
    {
        return new self(delimiter: $delimiter);
    }

    /**
     * Create options without length markers
     */
    public static function withoutLengthMarker(): self
    {
        return new self(lengthMarker: false);
    }

    /**
     * Fluent setter for indent
     */
    public function setIndent(string $indent): self
    {
        $this->indent = $indent;
        return $this;
    }

    /**
     * Fluent setter for delimiter
     */
    public function setDelimiter(string $delimiter): self
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    /**
     * Fluent setter for length marker
     */
    public function setLengthMarker(bool $lengthMarker): self
    {
        $this->lengthMarker = $lengthMarker;
        return $this;
    }
}
