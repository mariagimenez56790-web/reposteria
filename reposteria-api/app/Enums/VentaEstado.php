<?php

namespace App\Enums;

enum VentaEstado: string
{
    case Pendiente = 'pendiente';
    case Parcial = 'parcial';
    case Pagada = 'pagada';
    case Anulada = 'anulada';
}
