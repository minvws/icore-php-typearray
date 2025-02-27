<?php

declare(strict_types=1);

namespace Jaytaph\TypeArray\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\SerializationFailed;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Doctrine\Deprecations\Deprecation;
use JsonException;
use Jaytaph\TypeArray\TypeArray;

use function is_resource;
use function json_encode;
use function stream_get_contents;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

class TypeArrayType extends Type
{
    public const TYPE = 'type_array';

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): false|string|null
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof TypeArray) {
            throw InvalidType::new($value, self::TYPE, ['null', TypeArray::class]);
        }

        try {
            return json_encode($value->toArray(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $e) {
            throw SerializationFailed::new($value, self::TYPE, $e->getMessage(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?TypeArray
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        try {
            return TypeArray::fromJson(strval($value));
        } catch (JsonException $e) {
            throw ValueNotConvertible::new($value, $this->getName(), $e->getMessage(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return self::TYPE;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        Deprecation::triggerIfCalledFromOutside(
            'doctrine/dbal',
            'https://github.com/doctrine/dbal/pull/5509',
            '%s is deprecated.',
            __METHOD__,
        );

        return true;
    }
}
