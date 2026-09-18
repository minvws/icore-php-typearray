# Typed Array

Typed Array provides runtime-checked access to values in PHP arrays. It uses
[Symfony PropertyAccess](https://symfony.com/doc/current/components/property_access.html)
paths to read nested data and returns values with explicit PHP types.

This is useful when working with decoded JSON or other arrays containing
`mixed` values. The package validates values when they are read and does not
cast them to the requested type.

## Dependencies

- PHP `^8.2`
- Symfony PropertyAccess `^6.0`, `^7.0`, or `^8.0`

## Installation

Install the package with Composer:

```shell
composer require minvws/icore-php-typearray
```

## Quick start

```php
<?php

use MinVWS\TypeArray\TypeArray;

require __DIR__ . '/vendor/autoload.php';

$data = new TypeArray([
    'user' => [
        'name' => 'Ada',
        'age' => 37,
        'active' => true,
        'address' => [
            'city' => 'Amsterdam',
        ],
        'preferences' => [
            'locale' => 'nl',
        ],
    ],
]);

$name = $data->getString('[user][name]');
$age = $data->getInt('[user][age]');
$active = $data->getBool('[user][active]');
$locale = $data
    ->getTypeArray('[user][preferences]')
    ->getString('[locale]');
```

The values assigned above have the types `string`, `int`, `bool`, and `string`
respectively.

The examples below use the Composer autoloader and `TypeArray` import from this
quick start.

## Usage

### Reading typed values

TypeArray provides getters for the most common PHP value types:

| Method | Return type |
| --- | --- |
| `getString()` | `string` |
| `getInt()` | `int` |
| `getFloat()` | `float` |
| `getBool()` | `bool` |
| `getTypeArray()` | `TypeArray` |

The requested value must have the exact PHP type. For example, `getInt()` does
not convert a numeric string such as `"42"` to an integer.

### Paths

Paths use the Symfony PropertyAccess syntax. Wrap each array key in square
brackets and append brackets to read nested values:

```php
$city = $data->getString('[user][address][city]');
```

See the [Symfony PropertyAccess documentation](https://symfony.com/doc/current/components/property_access.html#reading-from-arrays)
for the complete path syntax and escaping rules.

### Default and nullable values

`getString()`, `getInt()`, `getFloat()`, and `getTypeArray()` accept an optional
default value. The default is returned when the path does not exist or contains
`null`:

```php
$config = new TypeArray([
    'description' => null,
]);

$environment = $config->getString('[environment]', 'production');
$description = $config->getString('[description]', 'No description');
```

The nullable getters return `null` when a path does not exist:

```php
$name = $config->getStringOrNull('[name]');
$retries = $config->getIntOrNull('[retries]');
$ratio = $config->getFloatOrNull('[ratio]');
$options = $config->getTypeArrayOrNull('[options]');
```

`getBool()` returns `false` by default for a missing or `null` value. Pass a
different default as its second argument when needed.

An existing `null` value passed to `getTypeArrayOrNull()` produces an empty
TypeArray. A missing path returns `null`.

### Nested arrays and iteration

Use `getTypeArray()` to keep reading a nested array with typed getters:

```php
$order = new TypeArray([
    'shipping' => [
        'country' => 'NL',
    ],
    'items' => [
        ['sku' => 'ABC-123', 'quantity' => 2],
        ['sku' => 'XYZ-987', 'quantity' => 1],
    ],
]);

$country = $order
    ->getTypeArray('[shipping]')
    ->getString('[country]');

foreach ($order->getIterable('[items]') as $item) {
    if ($item instanceof TypeArray) {
        $sku = $item->getString('[sku]');
        $quantity = $item->getInt('[quantity]');
    }
}
```

When `getIterable()` encounters a nested array, it yields that value as a
TypeArray instance. Other values are yielded unchanged.

### JSON and array conversion

Create a TypeArray directly from JSON:

```php
$payload = TypeArray::fromJson('{"id":42,"status":"open"}');

$id = $payload->getInt('[id]');
$status = $payload->getString('[status]');
```

Use `toArray()` to retrieve the wrapped array. TypeArray also implements
`JsonSerializable`, so it can be passed directly to `json_encode()`:

```php
$array = $payload->toArray();
$json = json_encode($payload, JSON_THROW_ON_ERROR);
```

Invalid JSON passed to `fromJson()` throws `JsonException`.

### Inspecting values

```php
$data->exists('[user][name]');
$data->isNullOrNotExists('[user][middle_name]');
$data->isTypeArray('[user][preferences]');
$data->isEmpty();

$empty = TypeArray::empty();
```

### Exceptions

A missing path passed to `getString()`, `getInt()`, `getFloat()`, or
`getTypeArray()` without a default throws `InvalidIndexException`. A value with
the wrong PHP type throws `IncorrectDataTypeException`:

```php
use MinVWS\TypeArray\Exception\IncorrectDataTypeException;
use MinVWS\TypeArray\Exception\InvalidIndexException;

$data = new TypeArray([
    'count' => '42',
]);

try {
    $data->getInt('[missing]');
} catch (InvalidIndexException $exception) {
    echo $exception->getMessage();
}

try {
    $data->getInt('[count]');
} catch (IncorrectDataTypeException $exception) {
    echo $exception->getMessage();
}
```

## Doctrine DBAL

The package includes the optional `type_array` type for Doctrine DBAL. Install
DBAL separately when using this integration:

```shell
composer require doctrine/dbal
```

Register the type during application startup:

```php
use Doctrine\DBAL\Types\Type;
use MinVWS\TypeArray\Doctrine\DBAL\Types\TypeArrayType;

if (!Type::hasType(TypeArrayType::TYPE)) {
    Type::addType(TypeArrayType::TYPE, TypeArrayType::class);
}
```

The type stores a TypeArray as JSON and converts database values back to a
TypeArray. See the [Doctrine DBAL custom mapping type documentation](https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/types.html#custom-mapping-types)
for integration details.

## Development

Install the development dependencies:

```shell
composer install
```

## Testing

Run the coding-style checks, static analysis, and unit tests with:

```shell
composer test
```

## License

This package is available under the [BSD 3-Clause License](LICENSE).

## Part of iCore

This package is part of the iCore project.
