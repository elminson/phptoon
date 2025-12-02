<?php

namespace PhpToon\Exceptions;

class ToonEncodeException extends ToonException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct("Encoding error: {$message}", 0, $previous);
    }
}
