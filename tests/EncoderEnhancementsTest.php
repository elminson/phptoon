<?php

namespace PhpToon\Tests;

use PHPUnit\Framework\TestCase;
use PhpToon\ToonEncoder;

enum Status: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

enum Priority: int
{
    case LOW = 1;
    case HIGH = 10;
}

enum SimpleEnum
{
    case FIRST;
    case SECOND;
}

class EncoderEnhancementsTest extends TestCase
{
    public function test_encode_datetime(): void
    {
        $date = new \DateTime('2024-01-15 12:30:00');
        $result = ToonEncoder::encode($date);

        $this->assertIsString($result);
        $this->assertStringContainsString('2024-01-15', $result);
    }

    public function test_encode_datetime_immutable(): void
    {
        $date = new \DateTimeImmutable('2024-01-15 12:30:00');
        $result = ToonEncoder::encode($date);

        $this->assertIsString($result);
        $this->assertStringContainsString('2024-01-15', $result);
    }

    public function test_encode_backed_enum_string(): void
    {
        $result = ToonEncoder::encode(Status::ACTIVE);
        $this->assertEquals('active', $result);
    }

    public function test_encode_backed_enum_int(): void
    {
        $result = ToonEncoder::encode(Priority::HIGH);
        $this->assertEquals('10', $result);
    }

    public function test_encode_unit_enum(): void
    {
        $result = ToonEncoder::encode(SimpleEnum::FIRST);
        $this->assertEquals('FIRST', $result);
    }

    public function test_encode_object_with_enums(): void
    {
        $data = [
            'status' => Status::ACTIVE,
            'priority' => Priority::HIGH,
        ];

        $result = ToonEncoder::encode($data);

        $this->assertStringContainsString('status: active', $result);
        $this->assertStringContainsString('priority: 10', $result);
    }

    public function test_encode_nan_float(): void
    {
        $result = ToonEncoder::encode(NAN);
        $this->assertEquals('null', $result);
    }

    public function test_encode_infinite_float(): void
    {
        $result = ToonEncoder::encode(INF);
        $this->assertEquals('null', $result);
    }

    public function test_encode_negative_infinite_float(): void
    {
        $result = ToonEncoder::encode(-INF);
        $this->assertEquals('null', $result);
    }

    public function test_encode_normal_float(): void
    {
        $result = ToonEncoder::encode(3.14159);
        $this->assertEquals('3.14159', $result);
    }

    public function test_encode_complex_structure_with_dates_and_enums(): void
    {
        $data = [
            'user' => 'John',
            'status' => Status::ACTIVE,
            'created' => new \DateTime('2024-01-15'),
            'priority' => Priority::LOW,
        ];

        $result = ToonEncoder::encode($data);

        $this->assertStringContainsString('user: John', $result);
        $this->assertStringContainsString('status: active', $result);
        $this->assertStringContainsString('2024-01-15', $result);
        $this->assertStringContainsString('priority: 1', $result);
    }
}
