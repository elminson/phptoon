# PhpToon

PHP implementation of [TOON (Token-Oriented Object Notation)](https://github.com/toon-format/toon) - a compact, LLM-optimized encoding format that reduces tokens by ~40% while maintaining better accuracy than JSON.

## Features

- **Token Efficient**: Reduces token usage by approximately 40% compared to JSON
- **LLM Optimized**: Designed specifically for large language model interactions
- **Lossless**: Deterministic round-trips with JSON data
- **Laravel Integration**: First-class support for Laravel 11 & 12
- **Composer Ready**: Easy installation via Composer
- **Flexible Configuration**: Customizable indentation, delimiters, and formatting

## Installation

Install via Composer:

```bash
composer require phptoon/phptoon
```

### Laravel

The package will auto-register the service provider. Optionally publish the config:

```bash
php artisan vendor:publish --tag=toon-config
```

## Usage

### Standalone PHP

```php
use PhpToon\ToonEncoder;
use PhpToon\Support\EncodeOptions;

// Basic encoding
$data = [
    'name' => 'John Doe',
    'age' => 30,
    'items' => [
        ['sku' => 'A1', 'qty' => 2, 'price' => 9.99],
        ['sku' => 'B2', 'qty' => 1, 'price' => 14.50],
    ]
];

$toon = ToonEncoder::encode($data);
echo $toon;
```

Output:
```
{
  age: 30
  items[2]{price,qty,sku}:
    9.99,2,A1
    14.5,1,B2
  name: John Doe
}
```

### With Custom Options

```php
$options = new EncodeOptions(
    indent: '    ',      // 4 spaces
    delimiter: '|',      // pipe delimiter
    lengthMarker: true   // include array lengths
);

$toon = ToonEncoder::encode($data, $options);

// Or use fluent methods
$options = EncodeOptions::withIndent("\t")
    ->setDelimiter(';')
    ->setLengthMarker(false);
```

### Laravel Usage

#### Using the Facade

```php
use PhpToon\Laravel\Facades\Toon;

$toon = Toon::encode($data);
```

#### Using Dependency Injection

```php
use PhpToon\ToonEncoder;

class MyController extends Controller
{
    public function __construct(
        private ToonEncoder $encoder
    ) {}

    public function show()
    {
        $data = ['key' => 'value'];
        return $this->encoder->encode($data);
    }
}
```

#### Response Macro

```php
Route::get('/api/data', function () {
    $data = User::all();
    return response()->toon($data);
});
```

This automatically sets the `Content-Type: text/toon` header.

#### Collection Macro

```php
$users = User::all();
$toon = $users->toToon();
```

### Configuration

Edit `config/toon.php`:

```php
return [
    'indent' => '  ',           // Indentation string
    'delimiter' => ',',         // Value delimiter
    'length_marker' => true,    // Include array lengths
];
```

Or use environment variables:

```env
TOON_INDENT="  "
TOON_DELIMITER=","
TOON_LENGTH_MARKER=true
```

## TOON Format Overview

TOON combines YAML's indentation with CSV-style tabular layouts for optimal token efficiency:

### Objects
```
{
  name: John
  age: 30
}
```

### Arrays
```
[3]:
  item1
  item2
  item3
```

### Tabular Arrays (Uniform Objects)
```
users[2]{age,name}:
  30,John
  25,Jane
```

### Nested Structures
```
{
  company: Acme Corp
  employees[2]{name,role}:
    Alice,Engineer
    Bob,Designer
}
```

## Why TOON?

When working with LLMs:
- **73.9%** accuracy vs JSON's **69.7%**
- **~40%** fewer tokens used
- **26.9** accuracy per 1k tokens vs JSON's **15.3**

Perfect for:
- LLM API requests/responses
- Reducing token costs
- Improving model comprehension
- Data-heavy LLM interactions

## Requirements

- PHP 8.1 or higher
- ext-mbstring
- Laravel 11.x or 12.x (for Laravel features)

## Testing

```bash
composer test
```

## License

MIT License

## Credits

Based on the [TOON specification](https://github.com/toon-format/toon) by the TOON Format project.

Inspired by [gotoon](https://github.com/alpkeskin/gotoon) (Go implementation).

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
