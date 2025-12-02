# PhpToon

[![Tests](https://github.com/elminson/phptoon/workflows/Tests/badge.svg)](https://github.com/elminson/phptoon/actions)
[![PHPStan](https://github.com/elminson/phptoon/workflows/PHPStan/badge.svg)](https://github.com/elminson/phptoon/actions)
[![Latest Version](https://img.shields.io/packagist/v/phptoon/phptoon.svg)](https://packagist.org/packages/phptoon/phptoon)
[![License](https://img.shields.io/packagist/l/phptoon/phptoon.svg)](https://packagist.org/packages/phptoon/phptoon)

The most advanced PHP implementation of [TOON (Token-Oriented Object Notation)](https://github.com/toon-format/toon) - a compact, LLM-optimized encoding format that reduces tokens by ~40% while maintaining better accuracy than JSON.

## Why PhpToon is Better

- **Full Bidirectional Support**: Both encoding AND decoding (not just one-way)
- **Advanced Type Support**: DateTime, Enums (Backed & Unit), NaN/INF handling
- **Laravel-First Design**: Middleware, Response/Collection macros, auto-discovery
- **Production Ready**: PHPStan Level 8, GitHub Actions CI/CD, comprehensive tests
- **Developer Experience**: Helper functions, token estimation, comparison utilities
- **Well-Architected**: Clean separation of concerns, extensive error handling

## Features

### Core Functionality
- ✅ **Encode & Decode**: Full bidirectional TOON ↔ PHP conversion
- ✅ **Token Efficient**: ~40% reduction vs JSON, better LLM accuracy
- ✅ **Type Support**: Primitives, Arrays, Objects, DateTime, Enums
- ✅ **Special Values**: NaN/INF handling, quoted strings, escaping
- ✅ **Tabular Arrays**: Automatic detection and optimization

### Laravel Integration
- ✅ **Auto-Discovery**: Service provider automatically registered
- ✅ **Facade Support**: Clean `Toon::encode()` API
- ✅ **Response Macro**: `response()->toon($data)`
- ✅ **Collection Macro**: `$collection->toToon()`
- ✅ **Middleware**: Auto-convert TOON requests/responses
- ✅ **Config Publishing**: Customizable settings

### Developer Tools
- ✅ **Helper Functions**: `toon()`, `toon_decode()`, `toon_compare()`
- ✅ **Token Estimation**: Built-in token counting
- ✅ **Comparison Utility**: Analyze TOON vs JSON savings
- ✅ **Quality Tools**: PHPStan Level 8, comprehensive tests
- ✅ **CI/CD**: GitHub Actions workflows included

## Installation

```bash
composer require phptoon/phptoon
```

### Laravel Setup

The package auto-registers. Optionally publish config:

```bash
php artisan vendor:publish --tag=toon-config
```

## Usage

### Basic Encoding/Decoding

```php
use PhpToon\ToonEncoder;
use PhpToon\ToonDecoder;

// Encoding
$data = [
    'name' => 'John Doe',
    'age' => 30,
    'active' => true
];

$toon = ToonEncoder::encode($data);
// Output:
// {
//   active: true
//   age: 30
//   name: John Doe
// }

// Decoding
$decoded = ToonDecoder::decode($toon);
// Returns original PHP array
```

### Helper Functions

```php
// Quick encoding
$toon = toon($data);

// Decoding
$data = toon_decode($toon);

// Compact format (no indentation)
$compact = toon_compact($data);

// Readable format (4-space indent)
$readable = toon_readable($data);

// Tabular format (tab delimiter)
$tabular = toon_tabular($data);

// Compare TOON vs JSON
$comparison = toon_compare($data);
echo "Savings: {$comparison['savings_percent']}%";
echo "Tokens saved: {$comparison['savings_tokens']}";

// Estimate tokens
$tokens = toon_estimate_tokens($text);

// Get savings info
$savings = toon_savings($data);
// ['percent' => 42.5, 'tokens' => 150]
```

### Advanced Types

```php
// DateTime Support
$data = [
    'created' => new DateTime('2024-01-15 12:30:00'),
    'updated' => new DateTimeImmutable('now')
];
$toon = toon($data);
// Encodes to ISO 8601 format

// Enum Support (PHP 8.1+)
enum Status: string {
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

$data = ['status' => Status::ACTIVE];
$toon = toon($data);
// {
//   status: active
// }

// Special Float Values
$data = [
    'nan' => NAN,
    'inf' => INF,
    'value' => 3.14
];
$toon = toon($data);
// {
//   inf: null
//   nan: null
//   value: 3.14
// }
```

### Custom Options

```php
use PhpToon\Support\EncodeOptions;

$options = new EncodeOptions(
    indent: '    ',      // 4 spaces
    delimiter: '|',      // pipe delimiter
    lengthMarker: true   // include array lengths
);

$toon = ToonEncoder::encode($data, $options);

// Or fluent API
$options = EncodeOptions::withIndent("\t")
    ->setDelimiter(';')
    ->setLengthMarker(false);
```

### Laravel Usage

#### Facade

```php
use PhpToon\Laravel\Facades\Toon;

$toon = Toon::encode($data);
$data = Toon::decode($toon);
```

#### Dependency Injection

```php
use PhpToon\ToonEncoder;

class DataController extends Controller
{
    public function __construct(
        private ToonEncoder $encoder
    ) {}

    public function export()
    {
        $data = User::all();
        return $this->encoder->encode($data);
    }
}
```

#### Response Macro

```php
Route::get('/api/users', function () {
    return response()->toon(User::all());
});
// Automatically sets Content-Type: text/toon
```

#### Collection Macro

```php
$users = User::where('active', true)->get();
$toon = $users->toToon();

// Save to file
Storage::put('users.toon', $users->toToon());
```

#### Middleware

Convert incoming TOON requests and outgoing responses:

```php
// app/Http/Kernel.php
protected $routeMiddleware = [
    'toon.request' => \PhpToon\Laravel\Middleware\ConvertToonRequests::class,
    'toon.response' => \PhpToon\Laravel\Middleware\ConvertToonResponses::class,
];

// In routes
Route::middleware(['toon.request', 'toon.response'])
    ->post('/api/data', [DataController::class, 'store']);

// Or globally in middleware groups
```

Auto-conversion based on headers:
- Request: `Content-Type: text/toon`
- Response: `Accept: text/toon` or `?format=toon`

### Token Analysis

```php
use PhpToon\Utilities\ToonComparison;

$data = [
    'users' => [
        ['id' => 1, 'name' => 'Alice', 'role' => 'Engineer'],
        ['id' => 2, 'name' => 'Bob', 'role' => 'Designer'],
        ['id' => 3, 'name' => 'Charlie', 'role' => 'Manager'],
    ]
];

// Get comparison
$result = ToonComparison::compare($data);
print_r($result);
/*
Array (
    [json] => "...JSON string..."
    [toon] => "...TOON string..."
    [json_length] => 245
    [toon_length] => 156
    [json_tokens] => 62
    [toon_tokens] => 39
    [savings_percent] => 37.1
    [savings_tokens] => 23
)
*/

// Generate report
echo ToonComparison::report($data);
/*
TOON vs JSON Comparison
==================================================

Character Count:
  JSON:  245 characters
  TOON:  156 characters
  Saved: 89 characters

Estimated Token Count:
  JSON:  62 tokens
  TOON:  39 tokens
  Saved: 23 tokens (37.1%)
...
*/

// Get summary only
$summary = ToonComparison::summary($data);
// ['savings_percent' => 37.1, 'savings_tokens' => 23, ...]
```

## TOON Format Examples

### Primitives
```
null
true
false
42
3.14
hello
"quoted string"
```

### Objects
```
{
  name: John
  age: 30
  active: true
}
```

### Arrays
```
[3]:
  item1
  item2
  item3
```

### Tabular Arrays (The Magic!)
```
users[3]{id,name,role}:
  1,Alice,Engineer
  2,Bob,Designer
  3,Charlie,Manager
```

### Complex Nested Structures
```
{
  company: Acme Corp
  founded: 2020
  employees[2]{name,role,salary}:
    Alice,Engineer,120000
    Bob,Designer,95000
  metadata: {
    version: 1.0
    updated: 2024-01-15T12:30:00+00:00
  }
}
```

## Configuration

Edit `config/toon.php`:

```php
return [
    'indent' => env('TOON_INDENT', '  '),           // Indentation string
    'delimiter' => env('TOON_DELIMITER', ','),      // Value delimiter
    'length_marker' => env('TOON_LENGTH_MARKER', true),  // Include array lengths
];
```

Environment variables:
```env
TOON_INDENT="  "
TOON_DELIMITER=","
TOON_LENGTH_MARKER=true
```

## Performance Benefits

When working with LLMs:
- **73.9%** accuracy vs JSON's **69.7%**
- **~40%** fewer tokens used
- **26.9** accuracy per 1k tokens vs JSON's **15.3**
- **Significant cost savings** on API usage

Perfect for:
- LLM API requests/responses (OpenAI, Anthropic, etc.)
- Reducing token costs in AI applications
- Improving model comprehension
- RAG systems and vector databases
- Data-heavy LLM interactions
- Agent-to-agent communication

## Testing

```bash
# Run tests
composer test

# With coverage
composer test-coverage

# Run PHPStan
vendor/bin/phpstan analyse
```

## Requirements

- PHP 8.1 or higher
- ext-mbstring
- Laravel 11.x or 12.x (for Laravel features)

## Quality Assurance

- ✅ PHPStan Level 8 (strictest static analysis)
- ✅ Comprehensive test suite (100+ tests)
- ✅ GitHub Actions CI/CD
- ✅ Automated testing on PHP 8.1, 8.2, 8.3
- ✅ Laravel 11 & 12 compatibility testing

## Comparison with Other Packages

| Feature | PhpToon | HelgeSverre/toon-php |
|---------|---------|----------------------|
| Encoding | ✅ | ✅ |
| Decoding | ✅ | ✅ |
| DateTime Support | ✅ | ✅ |
| Enum Support | ✅ | ✅ |
| Helper Functions | ✅ | ✅ |
| Laravel Middleware | ✅ | ❌ |
| Response/Collection Macros | ✅ | ❌ |
| Token Estimation | ✅ | ✅ |
| PHPStan Level 8 | ✅ | ❌ |
| GitHub Actions CI/CD | ✅ | ✅ |
| Auto-Discovery | ✅ | ❌ |
| Comprehensive Tests | ✅ | ✅ |

## Examples

See the [examples directory](examples/) for more:
- LLM integration examples (OpenAI, Anthropic)
- Laravel API examples
- Token optimization strategies
- RAG system integration

## Contributing

Contributions are welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Add tests for new features
4. Ensure PHPStan passes
5. Submit a pull request

## License

MIT License - see [LICENSE](LICENSE) file

## Credits

- Based on the [TOON specification](https://github.com/toon-format/toon)
- Inspired by [gotoon](https://github.com/alpkeskin/gotoon) (Go implementation)
- Built with ❤️ for the PHP & Laravel community

## Support

- 📫 Issues: [GitHub Issues](https://github.com/elminson/phptoon/issues)
- 💬 Discussions: [GitHub Discussions](https://github.com/elminson/phptoon/discussions)
- 📖 Documentation: [Full docs](https://github.com/elminson/phptoon)

---

**Made with 🚀 by the PhpToon Team**
