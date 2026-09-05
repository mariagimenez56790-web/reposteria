<?php

namespace App\Enums;

enum PedidoEstado: string
{
    case Pendiente = 'pendiente';
    case Confirmado = 'confirmado';
    case EnProduccion = 'en_produccion';
    case Listo = 'listo';
    case Entregado = 'entregado';
    case Cancelado = 'cancelado';
}
