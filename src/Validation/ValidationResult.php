<?php

namespace PhpToon\Validation;

class ValidationResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly array $errors = []
    ) {}

    public function hasErrors(): bool
    {
        return !$this->isValid;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getErrorsAsString(): string
    {
        return implode("\n", $this->errors);
    }
}
