<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Catalog\Domain\Exception\InvalidArgument;
use App\Catalog\Domain\Model\ProductId;
use PHPUnit\Framework\TestCase;

final class ProductIdTest extends TestCase
{
    public function testItAcceptsAValidUuid(): void
    {
        $id = ProductId::fromString('A1B2C3D4-1111-4111-8111-1234567890AB');

        self::assertSame('a1b2c3d4-1111-4111-8111-1234567890ab', $id->value());
    }

    public function testItRejectsAMalformedIdentifier(): void
    {
        $this->expectException(InvalidArgument::class);

        ProductId::fromString('not-a-uuid');
    }

    public function testEqualityIsBasedOnValue(): void
    {
        $value = '11111111-1111-4111-8111-111111111111';

        self::assertTrue(ProductId::fromString($value)->equals(ProductId::fromString($value)));
        self::assertFalse(
            ProductId::fromString($value)->equals(
                ProductId::fromString('22222222-2222-4222-8222-222222222222')
            )
        );
    }
}
