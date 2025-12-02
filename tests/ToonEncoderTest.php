<?php

namespace PhpToon\Tests;

use PHPUnit\Framework\TestCase;
use PhpToon\ToonEncoder;
use PhpToon\Support\EncodeOptions;

class ToonEncoderTest extends TestCase
{
    public function test_encode_primitives(): void
    {
        $this->assertEquals('null', ToonEncoder::encode(null));
        $this->assertEquals('true', ToonEncoder::encode(true));
        $this->assertEquals('false', ToonEncoder::encode(false));
        $this->assertEquals('42', ToonEncoder::encode(42));
        $this->assertEquals('3.14', ToonEncoder::encode(3.14));
        $this->assertEquals('hello', ToonEncoder::encode('hello'));
    }

    public function test_encode_string_quoting(): void
    {
        // Empty string needs quotes
        $this->assertEquals('""', ToonEncoder::encode(''));

        // Ambiguous values need quotes
        $this->assertEquals('"true"', ToonEncoder::encode('true'));
        $this->assertEquals('"false"', ToonEncoder::encode('false'));
        $this->assertEquals('"null"', ToonEncoder::encode('null'));
        $this->assertEquals('"123"', ToonEncoder::encode('123'));

        // Strings with delimiters need quotes
        $this->assertEquals('"hello,world"', ToonEncoder::encode('hello,world'));

        // Normal strings don't need quotes
        $this->assertEquals('hello', ToonEncoder::encode('hello'));
    }

    public function test_encode_empty_array(): void
    {
        $this->assertEquals('[0]', ToonEncoder::encode([]));
    }

    public function test_encode_simple_array(): void
    {
        $data = [1, 2, 3];
        $expected = "[3]:\n  1\n  2\n  3";
        $this->assertEquals($expected, ToonEncoder::encode($data));
    }

    public function test_encode_empty_object(): void
    {
        $this->assertEquals('{}', ToonEncoder::encode(new \stdClass()));
    }

    public function test_encode_simple_object(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        $result = ToonEncoder::encode($data);

        // Keys should be sorted alphabetically
        $this->assertStringContainsString('age: 30', $result);
        $this->assertStringContainsString('name: John', $result);
    }

    public function test_encode_tabular_array(): void
    {
        $data = [
            ['sku' => 'A1', 'qty' => 2, 'price' => 9.99],
            ['sku' => 'B2', 'qty' => 1, 'price' => 14.50],
        ];

        $result = ToonEncoder::encode($data);

        // Should use tabular format
        $this->assertStringContainsString('[2]{', $result);
        $this->assertStringContainsString('9.99,2,A1', $result);
        $this->assertStringContainsString('14.5,1,B2', $result);
    }

    public function test_encode_nested_structure(): void
    {
        $data = [
            'company' => 'Acme Corp',
            'employees' => [
                ['name' => 'Alice', 'role' => 'Engineer'],
                ['name' => 'Bob', 'role' => 'Designer'],
            ],
        ];

        $result = ToonEncoder::encode($data);

        $this->assertStringContainsString('company: Acme Corp', $result);
        $this->assertStringContainsString('[2]{name,role}:', $result);
        $this->assertStringContainsString('Alice,Engineer', $result);
        $this->assertStringContainsString('Bob,Designer', $result);
    }

    public function test_custom_options(): void
    {
        $data = [1, 2, 3];
        $options = new EncodeOptions(
            indent: '    ',
            delimiter: '|',
            lengthMarker: true
        );

        $result = ToonEncoder::encode($data, $options);
        $this->assertStringContainsString('[3]:', $result);
        $this->assertStringContainsString('    1', $result);
    }

    public function test_deterministic_output(): void
    {
        $data = [
            'zebra' => 1,
            'apple' => 2,
            'banana' => 3,
        ];

        $result1 = ToonEncoder::encode($data);
        $result2 = ToonEncoder::encode($data);

        // Should produce identical output
        $this->assertEquals($result1, $result2);

        // Keys should be alphabetically sorted
        $this->assertMatchesRegularExpression(
            '/apple:.*banana:.*zebra:/s',
            $result1
        );
    }

    public function test_encode_with_objects(): void
    {
        $obj = new \stdClass();
        $obj->name = 'Test';
        $obj->value = 123;

        $result = ToonEncoder::encode($obj);

        $this->assertStringContainsString('name: Test', $result);
        $this->assertStringContainsString('value: 123', $result);
    }
}
