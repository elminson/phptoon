<?php

namespace PhpToon\Tests;

use PHPUnit\Framework\TestCase;
use PhpToon\ToonDecoder;
use PhpToon\Exceptions\ToonDecodeException;

class ToonDecoderTest extends TestCase
{
    public function test_decode_primitives(): void
    {
        $this->assertNull(ToonDecoder::decode('null'));
        $this->assertTrue(ToonDecoder::decode('true'));
        $this->assertFalse(ToonDecoder::decode('false'));
        $this->assertEquals(42, ToonDecoder::decode('42'));
        $this->assertEquals(3.14, ToonDecoder::decode('3.14'));
        $this->assertEquals('hello', ToonDecoder::decode('hello'));
    }

    public function test_decode_quoted_strings(): void
    {
        $this->assertEquals('', ToonDecoder::decode('""'));
        $this->assertEquals('hello world', ToonDecoder::decode('"hello world"'));
        $this->assertEquals('true', ToonDecoder::decode('"true"'));
        $this->assertEquals('123', ToonDecoder::decode('"123"'));
    }

    public function test_decode_empty_array(): void
    {
        $this->assertEquals([], ToonDecoder::decode('[0]'));
    }

    public function test_decode_simple_array(): void
    {
        $toon = "[3]:\n  1\n  2\n  3";
        $this->assertEquals([1, 2, 3], ToonDecoder::decode($toon));
    }

    public function test_decode_empty_object(): void
    {
        $this->assertEquals([], ToonDecoder::decode('{}'));
    }

    public function test_decode_simple_object(): void
    {
        $toon = "{\n  name: John\n  age: 30\n}";
        $result = ToonDecoder::decode($toon);

        $this->assertIsArray($result);
        $this->assertEquals('John', $result['name']);
        $this->assertEquals(30, $result['age']);
    }

    public function test_decode_tabular_array(): void
    {
        $toon = "[2]{sku,qty,price}:\n  A1,2,9.99\n  B2,1,14.5";
        $result = ToonDecoder::decode($toon);

        $this->assertCount(2, $result);
        $this->assertEquals('A1', $result[0]['sku']);
        $this->assertEquals(2, $result[0]['qty']);
        $this->assertEquals(9.99, $result[0]['price']);
        $this->assertEquals('B2', $result[1]['sku']);
    }

    public function test_decode_nested_structure(): void
    {
        $toon = <<<'TOON'
{
  company: Acme Corp
  employees[2]{name,role}:
    Alice,Engineer
    Bob,Designer
}
TOON;

        $result = ToonDecoder::decode($toon);

        $this->assertEquals('Acme Corp', $result['company']);
        $this->assertCount(2, $result['employees']);
        $this->assertEquals('Alice', $result['employees'][0]['name']);
        $this->assertEquals('Engineer', $result['employees'][0]['role']);
    }

    public function test_decode_with_quoted_values_in_tabular(): void
    {
        $toon = "[2]{name,description}:\n  \"Product A\",\"A great product\"\n  \"Product B\",\"Another product\"";
        $result = ToonDecoder::decode($toon);

        $this->assertEquals('Product A', $result[0]['name']);
        $this->assertEquals('A great product', $result[0]['description']);
    }

    public function test_round_trip_encoding_decoding(): void
    {
        $data = [
            'name' => 'Test',
            'age' => 25,
            'items' => [
                ['id' => 1, 'name' => 'Item 1'],
                ['id' => 2, 'name' => 'Item 2'],
            ],
        ];

        $encoded = \PhpToon\ToonEncoder::encode($data);
        $decoded = ToonDecoder::decode($encoded);

        $this->assertEquals($data, $decoded);
    }

    public function test_decode_throws_exception_on_empty_input(): void
    {
        $this->expectException(ToonDecodeException::class);
        ToonDecoder::decode('');
    }

    public function test_decode_throws_exception_on_unterminated_string(): void
    {
        $this->expectException(ToonDecodeException::class);
        ToonDecoder::decode('"unterminated');
    }

    public function test_decode_throws_exception_on_invalid_structure(): void
    {
        $this->expectException(ToonDecodeException::class);
        ToonDecoder::decode('{invalid');
    }
}
