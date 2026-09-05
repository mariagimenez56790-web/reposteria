<?php

namespace App\Support;

final class Dinero
{
    public static function aCentavos(string|int $importe): int
    {
        [$entero, $decimales] = array_pad(explode('.', (string) $importe, 2), 2, '');

        return ((int) $entero * 100) + (int) substr(str_pad($decimales, 2, '0'), 0, 2);
    }

    public static function formatear(int $centavos): string
    {
        return sprintf('%d.%02d', intdiv($centavos, 100), $centavos % 100);
    }
}
