<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\Model\ProductDescription;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class ProductDescriptionType extends Type
{
    public const NAME = 'product_description';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'TEXT';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ProductDescription
    {
        if (null === $value || $value instanceof ProductDescription) {
            return $value;
        }

        return new ProductDescription((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        return $value instanceof ProductDescription ? $value->value() : (string) $value;
    }
}
