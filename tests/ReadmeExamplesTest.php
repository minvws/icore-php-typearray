<?php

declare(strict_types=1);

namespace Tests;

use MinVWS\TypeArray\Exception\IncorrectDataTypeException;
use MinVWS\TypeArray\Exception\InvalidIndexException;
use MinVWS\TypeArray\TypeArray;
use PHPUnit\Framework\TestCase;

class ReadmeExamplesTest extends TestCase
{
    public function testQuickStartReadsTypedValuesAndNestedPaths(): void
    {
        $data = new TypeArray([
            'user' => [
                'name' => 'Ada',
                'age' => 37,
                'active' => true,
                'address' => ['city' => 'Amsterdam'],
                'preferences' => ['locale' => 'nl'],
            ],
        ]);

        $this->assertSame('Ada', $data->getString('[user][name]'));
        $this->assertSame(37, $data->getInt('[user][age]'));
        $this->assertSame(true, $data->getBool('[user][active]'));
        $this->assertSame('Amsterdam', $data->getString('[user][address][city]'));
        $this->assertSame('nl', $data->getTypeArray('[user][preferences]')->getString('[locale]'));
    }

    public function testDefaultsAreReturnedForMissingAndNullValues(): void
    {
        $data = new TypeArray([
            'description' => null,
            'retries' => null,
            'ratio' => null,
            'settings' => null,
        ]);
        $defaultSettings = new TypeArray(['timezone' => 'Europe/Amsterdam']);

        $this->assertSame('production', $data->getString('[environment]', 'production'));
        $this->assertSame('No description', $data->getString('[description]', 'No description'));
        $this->assertSame(3, $data->getInt('[missing_retries]', 3));
        $this->assertSame(3, $data->getInt('[retries]', 3));
        $this->assertSame(0.5, $data->getFloat('[missing_ratio]', 0.5));
        $this->assertSame(0.5, $data->getFloat('[ratio]', 0.5));
        $this->assertSame($defaultSettings, $data->getTypeArray('[missing_settings]', $defaultSettings));
        $this->assertSame($defaultSettings, $data->getTypeArray('[settings]', $defaultSettings));
    }

    public function testNullableGettersReturnNullForMissingAndNullValues(): void
    {
        $data = new TypeArray([
            'name' => null,
            'retries' => null,
            'ratio' => null,
        ]);

        $this->assertNull($data->getStringOrNull('[missing_name]'));
        $this->assertNull($data->getStringOrNull('[name]'));
        $this->assertNull($data->getIntOrNull('[missing_retries]'));
        $this->assertNull($data->getIntOrNull('[retries]'));
        $this->assertNull($data->getFloatOrNull('[missing_ratio]'));
        $this->assertNull($data->getFloatOrNull('[ratio]'));
        $this->assertNull($data->getTypeArrayOrNull('[options]'));
    }

    public function testGetBoolUsesFalseForMissingAndNullValuesAndAcceptsCustomDefault(): void
    {
        $data = new TypeArray(['enabled' => null]);

        $this->assertFalse($data->getBool('[missing]'));
        $this->assertFalse($data->getBool('[enabled]'));
        $this->assertTrue($data->getBool('[missing]', true));
        $this->assertTrue($data->getBool('[enabled]', true));
    }

    public function testNullableTypeArrayDistinguishesMissingAndExistingNullValues(): void
    {
        $data = new TypeArray(['options' => null]);
        $options = $data->getTypeArrayOrNull('[options]');

        if ($options === null) {
            $this->fail('An existing null value did not produce an empty TypeArray.');
        }

        $this->assertTrue($options->isEmpty());
        $this->assertNull($data->getTypeArrayOrNull('[missing]'));
    }

    public function testNestedArraysAndIterableItemsRetainTheirContentsAndTypes(): void
    {
        $order = new TypeArray([
            'shipping' => ['country' => 'NL'],
            'items' => [
                ['sku' => 'ABC-123', 'quantity' => 2],
                ['sku' => 'XYZ-987', 'quantity' => 1],
                'unavailable',
            ],
        ]);

        $this->assertSame('NL', $order->getTypeArray('[shipping]')->getString('[country]'));

        $items = [];
        $otherValues = [];

        foreach ($order->getIterable('[items]') as $item) {
            if ($item instanceof TypeArray) {
                $items[] = [
                    'sku' => $item->getString('[sku]'),
                    'quantity' => $item->getInt('[quantity]'),
                ];
                continue;
            }

            if (is_array($item)) {
                $this->fail('A nested array item was not returned as a TypeArray.');
            }

            $otherValues[] = $item;
        }

        $this->assertSame([
            ['sku' => 'ABC-123', 'quantity' => 2],
            ['sku' => 'XYZ-987', 'quantity' => 1],
        ], $items);
        $this->assertSame(['unavailable'], $otherValues);
    }

    public function testJsonCreationArrayConversionAndJsonEncoding(): void
    {
        $payload = TypeArray::fromJson('{"id":42,"status":"open"}');

        $this->assertSame(42, $payload->getInt('[id]'));
        $this->assertSame('open', $payload->getString('[status]'));
        $this->assertSame(['id' => 42, 'status' => 'open'], $payload->toArray());
        $this->assertSame('{"id":42,"status":"open"}', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function testInvalidJsonThrowsJsonException(): void
    {
        $this->expectException(\JsonException::class);

        TypeArray::fromJson('{invalid}');
    }

    public function testInspectionHelpersReportTheDocumentedState(): void
    {
        $data = new TypeArray([
            'user' => [
                'name' => 'Ada',
                'middle_name' => null,
                'preferences' => ['locale' => 'nl'],
            ],
        ]);

        $this->assertTrue($data->exists('[user][name]'));
        $this->assertTrue($data->isNullOrNotExists('[user][middle_name]'));
        $this->assertTrue($data->isNullOrNotExists('[user][missing]'));
        $this->assertTrue($data->isTypeArray('[user][preferences]'));
        $this->assertFalse($data->isEmpty());
        $this->assertTrue(TypeArray::empty()->isEmpty());
    }

    public function testMissingTypedGettersThrowInvalidIndexException(): void
    {
        $data = new TypeArray([]);

        foreach (['getString', 'getInt', 'getFloat', 'getTypeArray'] as $method) {
            try {
                $data->{$method}('[missing]');
                $this->fail(sprintf('%s() did not throw for a missing path.', $method));
            } catch (InvalidIndexException $exception) {
                $this->assertSame('Invalid index: "[missing]"', $exception->getMessage());
            }
        }
    }

    public function testWrongTypedValuesThrowIncorrectDataTypeException(): void
    {
        $data = new TypeArray([
            'string' => 42,
            'int' => '42',
            'float' => '1.5',
            'bool' => 'true',
            'array' => 'not-an-array',
        ]);

        $invalidPaths = [
            'getString' => '[string]',
            'getInt' => '[int]',
            'getFloat' => '[float]',
            'getBool' => '[bool]',
            'getTypeArray' => '[array]',
        ];

        foreach ($invalidPaths as $method => $path) {
            try {
                $data->{$method}($path);
                $this->fail(sprintf('%s() did not reject an incorrect value type.', $method));
            } catch (IncorrectDataTypeException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
