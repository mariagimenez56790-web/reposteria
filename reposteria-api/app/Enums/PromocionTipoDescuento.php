<?php

namespace App\Enums;

enum PromocionTipoDescuento: string
{
    case Porcentaje = 'porcentaje';
    case MontoFijo = 'monto_fijo';
}
