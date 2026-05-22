<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\Model\ProductName;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class ProductNameType extends Type
{
    public const NAME = 'product_name';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return \sprintf('VARCHAR(%d)', ProductName::MAX_LENGTH);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ProductName
    {
        if (null === $value || $value instanceof ProductName) {
            return $value;
        }

        return new ProductName((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        return $value instanceof ProductName ? $value->value() : (string) $value;
    }
}
