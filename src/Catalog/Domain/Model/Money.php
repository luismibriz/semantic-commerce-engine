<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Exception\InvalidArgument;

/**
 * Monetary amount stored as an integer number of minor units (e.g. cents).
 *
 * Floats are intentionally rejected: binary floating point cannot represent
 * decimal money exactly, so any arithmetic would accumulate rounding error.
 * See ai/DECISIONS.md.
 */
final class Money
{
    private const CURRENCY_PATTERN = '/^[A-Z]{3}$/';

    private int $amount;

    private string $currency;

    private function __construct(int $amount, string $currency)
    {
        if ($amount < 0) {
            throw InvalidArgument::because('Money amount cannot be negative.');
        }

        if (1 !== preg_match(self::CURRENCY_PATTERN, $currency)) {
            throw InvalidArgument::because(\sprintf('"%s" is not a valid ISO-4217 currency code.', $currency));
        }

        $this->amount = $amount;
        $this->currency = $currency;
    }

    /**
     * @param int $minorUnits amount in the currency's smallest unit (cents)
     */
    public static function of(int $minorUnits, string $currency): self
    {
        return new self($minorUnits, strtoupper(trim($currency)));
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw InvalidArgument::because(\sprintf('Cannot operate on %s and %s amounts.', $this->currency, $other->currency));
        }
    }
}
