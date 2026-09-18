<?php

declare(strict_types=1);

namespace Tests\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\SerializationFailed;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use MinVWS\TypeArray\Doctrine\DBAL\Types\TypeArrayType;
use MinVWS\TypeArray\TypeArray;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class TypeArrayTypeTest extends TestCase
{
    private TypeArrayType $type;

    private SQLitePlatform $platform;

    protected function setUp(): void
    {
        $this->type = new TypeArrayType();
        $this->platform = new SQLitePlatform();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRegistersTheDocumentedTypeNameIdempotently(): void
    {
        if (! Type::hasType(TypeArrayType::TYPE)) {
            Type::addType(TypeArrayType::TYPE, TypeArrayType::class);
        }

        $registeredType = Type::getType('type_array');

        $this->assertInstanceOf(TypeArrayType::class, $registeredType);
        $this->assertSame('type_array', $registeredType->getName());

        if (! Type::hasType(TypeArrayType::TYPE)) {
            Type::addType(TypeArrayType::TYPE, TypeArrayType::class);
        }

        $this->assertSame($registeredType, Type::getType('type_array'));
    }

    public function testConvertsTypeArrayToJsonPreservingZeroFractions(): void
    {
        $value = new TypeArray([
            'whole' => 1,
            'fraction' => 1.0,
        ]);

        $this->assertSame(
            '{"whole":1,"fraction":1.0}',
            $this->type->convertToDatabaseValue($value, $this->platform),
        );
    }

    public function testConvertsJsonObjectStringToTypeArray(): void
    {
        $result = $this->type->convertToPHPValue('{"enabled":true,"count":2}', $this->platform);

        $this->assertInstanceOf(TypeArray::class, $result);
        $this->assertSame(['enabled' => true, 'count' => 2], $result->toArray());
    }

    public function testConvertsJsonStringToTypeArray(): void
    {
        $result = $this->type->convertToPHPValue('"ready"', $this->platform);

        $this->assertInstanceOf(TypeArray::class, $result);
        $this->assertSame([0 => 'ready'], $result->toArray());
    }

    public function testConvertsJsonResourceToTypeArray(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        fwrite($resource, '{"source":"stream"}');
        rewind($resource);

        try {
            $result = $this->type->convertToPHPValue($resource, $this->platform);
        } finally {
            fclose($resource);
        }

        $this->assertInstanceOf(TypeArray::class, $result);
        $this->assertSame(['source' => 'stream'], $result->toArray());
    }

    #[DataProvider('nullAndEmptyDatabaseValues')]
    public function testConvertsNullAndEmptyDatabaseValuesToNull(mixed $value): void
    {
        $this->assertNull($this->type->convertToPHPValue($value, $this->platform));
    }

    /**
     * @return array<string, array{null|string}>
     */
    public static function nullAndEmptyDatabaseValues(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
        ];
    }

    public function testConvertsNullToANullDatabaseValue(): void
    {
        $this->assertNull($this->type->convertToDatabaseValue(null, $this->platform));
    }

    public function testRejectsNonTypeArrayDatabaseValues(): void
    {
        $this->expectException(InvalidType::class);

        $this->type->convertToDatabaseValue(['not' => 'a TypeArray'], $this->platform);
    }

    public function testWrapsJsonEncodingFailuresInDoctrineException(): void
    {
        $this->expectException(SerializationFailed::class);

        $this->type->convertToDatabaseValue(new TypeArray(['invalid' => INF]), $this->platform);
    }

    public function testWrapsMalformedJsonInDoctrineException(): void
    {
        $this->expectException(ValueNotConvertible::class);

        $this->type->convertToPHPValue('{invalid}', $this->platform);
    }
}
