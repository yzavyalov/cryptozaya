<?php

namespace App\Services\Operations;

class AmountRoundingService
{
    public function rounding(string $amount): string
    {
        if (bccomp($amount, '0', 10) === 0) {
            return '0';
        }

        $precision = 2;

        while ($precision <= 18) { // для крипты можно до 18 знаков
            $rounded = $this->bcround($amount, $precision);

            if (bccomp($rounded, '0', $precision) === 1) {
                return $rounded;
            }

            $precision++;
        }

        return '0';
    }

    private function bcround(string $number, int $precision = 2): string
    {
        $factor = bcpow('10', (string)($precision + 1), 0);

        // сдвигаем число
        $tmp = bcmul($number, $factor, 0);

        // смотрим на последний знак для округления
        $lastDigit = (int) bcmod($tmp, '10');

        // отрезаем последний знак
        $tmp = bcdiv($tmp, '10', 0);

        if ($lastDigit >= 5) {
            $tmp = bcadd($tmp, '1', 0);
        }

        return bcdiv($tmp, bcpow('10', (string)$precision, 0), $precision);
    }
}
