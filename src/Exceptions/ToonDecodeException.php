<?php

namespace PhpToon\Exceptions;

class ToonDecodeException extends ToonException
{
    public function __construct(string $message, int $line = 0, int $column = 0, ?\Throwable $previous = null)
    {
        $location = $line > 0 ? " at line {$line}, column {$column}" : '';
        parent::__construct("Decoding error: {$message}{$location}", 0, $previous);
    }
}
