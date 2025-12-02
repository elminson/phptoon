<?php

namespace PhpToon\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string encode(mixed $data, \PhpToon\Support\EncodeOptions|null $options = null)
 *
 * @see \PhpToon\ToonEncoder
 */
class Toon extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'toon';
    }
}
