<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Catalog\Domain\Exception\InvalidArgument;
use App\Catalog\Domain\Model\ProductDescription;
use App\Catalog\Domain\Model\ProductName;
use PHPUnit\Framework\TestCase;

final class ProductTextValueObjectsTest extends TestCase
{
    public function testProductNameTrimsSurroundingWhitespace(): void
    {
        self::assertSame('Maillot', (new ProductName('  Maillot  '))->value());
    }

    public function testProductNameRejectsEmptyInput(): void
    {
        $this->expectException(InvalidArgument::class);

        new ProductName('   ');
    }

    public function testProductNameRejectsOverlongInput(): void
    {
        $this->expectException(InvalidArgument::class);

        new ProductName(str_repeat('a', ProductName::MAX_LENGTH + 1));
    }

    public function testProductDescriptionRejectsEmptyInput(): void
    {
        $this->expectException(InvalidArgument::class);

        new ProductDescription('');
    }

    public function testProductDescriptionRejectsOverlongInput(): void
    {
        $this->expectException(InvalidArgument::class);

        new ProductDescription(str_repeat('a', ProductDescription::MAX_LENGTH + 1));
    }

    public function testProductDescriptionKeepsValidInput(): void
    {
        $text = 'Camiseta térmica de manga larga para ciclismo de invierno.';

        self::assertSame($text, (new ProductDescription($text))->value());
    }
}
