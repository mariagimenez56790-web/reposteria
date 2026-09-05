<?php

namespace App\Enums;

enum MetodoPago: string
{
    case Efectivo = 'efectivo';
    case Transferencia = 'transferencia';
    case Qr = 'qr';
    case Tarjeta = 'tarjeta';
    case Otro = 'otro';
}
