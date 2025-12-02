<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Indentation
    |--------------------------------------------------------------------------
    |
    | The string used for indentation in nested structures.
    | Default is two spaces.
    |
    */
    'indent' => env('TOON_INDENT', '  '),

    /*
    |--------------------------------------------------------------------------
    | Delimiter
    |--------------------------------------------------------------------------
    |
    | The delimiter used to separate values in tabular arrays.
    | Default is comma (,).
    |
    */
    'delimiter' => env('TOON_DELIMITER', ','),

    /*
    |--------------------------------------------------------------------------
    | Length Marker
    |--------------------------------------------------------------------------
    |
    | Whether to include length markers (e.g., [3]) in array output.
    | Recommended to keep enabled for LLM optimization.
    |
    */
    'length_marker' => env('TOON_LENGTH_MARKER', true),
];
