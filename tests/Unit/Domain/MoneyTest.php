<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Catalog\Domain\Exception\InvalidArgument;
use App\Catalog\Domain\Model\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testItStoresAnAmountInMinorUnitsAndACurrency(): void
    {
        $money = Money::of(2599, 'EUR');

        self::assertSame(2599, $money->amount());
        self::assertSame('EUR', $money->currency());
    }

    public function testItNormalisesTheCurrencyCode(): void
    {
        self::assertSame('USD', Money::of(100, ' usd ')->currency());
    }

    public function testItAcceptsAZeroAmount(): void
    {
        self::assertSame(0, Money::of(0, 'EUR')->amount());
    }

    public function testItRejectsNegativeAmounts(): void
    {
        $this->expectException(InvalidArgument::class);

        Money::of(-1, 'EUR');
    }

    public function testItRejectsAnInvalidCurrencyCode(): void
    {
        $this->expectException(InvalidArgument::class);

        Money::of(100, 'EURO');
    }

    public function testItAddsAmountsOfTheSameCurrency(): void
    {
        $total = Money::of(1000, 'EUR')->add(Money::of(250, 'EUR'));

        self::assertTrue($total->equals(Money::of(1250, 'EUR')));
    }

    public function testItRefusesToAddDifferentCurrencies(): void
    {
        $this->expectException(InvalidArgument::class);

        Money::of(1000, 'EUR')->add(Money::of(250, 'USD'));
    }

    public function testEqualityConsidersBothAmountAndCurrency(): void
    {
        self::assertTrue(Money::of(100, 'EUR')->equals(Money::of(100, 'EUR')));
        self::assertFalse(Money::of(100, 'EUR')->equals(Money::of(100, 'USD')));
        self::assertFalse(Money::of(100, 'EUR')->equals(Money::of(999, 'EUR')));
    }
}
