<?php

namespace App\Services\Money;

use InvalidArgumentException;

/** Decimal arithmetic helpers backed exclusively by BCMath. */
class Money
{
    private const MONEY_SCALE = 2;

    private const CALCULATION_SCALE = 8;

    /** @return numeric-string */
    public function add(string|int $left, string|int $right): string
    {
        return bcadd($this->decimal($left), $this->decimal($right), self::MONEY_SCALE);
    }

    /** @return numeric-string */
    public function sub(string|int $left, string|int $right): string
    {
        return bcsub($this->decimal($left), $this->decimal($right), self::MONEY_SCALE);
    }

    /** @return numeric-string */
    public function mul(string|int $left, string|int $right): string
    {
        return $this->round(bcmul($this->decimal($left), $this->decimal($right), self::CALCULATION_SCALE));
    }

    /** @return numeric-string */
    public function percent(string|int $amount, string|int $rate): string
    {
        return $this->round(bcdiv(
            bcmul($this->decimal($amount), $this->decimal($rate), self::CALCULATION_SCALE),
            '100',
            self::CALCULATION_SCALE,
        ));
    }

    /** @return numeric-string */
    public function round(string|int $amount): string
    {
        $value = $this->decimal($amount);

        $rounded = bccomp($value, '0', self::CALCULATION_SCALE) < 0
            ? bcsub($value, '0.005', 3)
            : bcadd($value, '0.005', 3);

        return bcadd($rounded, '0', self::MONEY_SCALE);
    }

    public function compare(string|int $left, string|int $right): int
    {
        return bccomp($this->decimal($left), $this->decimal($right), self::MONEY_SCALE);
    }

    /** @return numeric-string */
    public function divide(string|int $numerator, string|int $denominator, int $scale = self::CALCULATION_SCALE): string
    {
        $normalizedDenominator = $this->decimal($denominator);

        if (bccomp($normalizedDenominator, '0', $scale) === 0) {
            throw new InvalidArgumentException('Cannot divide a monetary amount by zero.');
        }

        return bcdiv($this->decimal($numerator), $normalizedDenominator, $scale);
    }

    /** Convert a scalar to a validated decimal string accepted by BCMath.
     *
     * @return numeric-string
     */
    public function decimal(string|int $value): string
    {
        $normalized = (string) $value;

        if (! is_numeric($normalized) || preg_match('/^-?\d+(?:\.\d+)?$/D', $normalized) !== 1) {
            throw new InvalidArgumentException('Amount must be a decimal number.');
        }

        return $normalized;
    }
}
