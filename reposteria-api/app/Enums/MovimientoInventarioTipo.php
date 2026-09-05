<?php

namespace App\Enums;

enum MovimientoInventarioTipo: string
{
    case Entrada = 'entrada';
    case Salida = 'salida';
    case AjustePositivo = 'ajuste_positivo';
    case AjusteNegativo = 'ajuste_negativo';

    public function incrementa(): bool
    {
        return in_array($this, [self::Entrada, self::AjustePositivo], true);
    }
}
