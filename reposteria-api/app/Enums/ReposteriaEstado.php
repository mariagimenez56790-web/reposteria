<?php

namespace App\Enums;

enum ReposteriaEstado: string
{
    case Pendiente = 'pendiente';
    case Aprobada = 'aprobada';
    case Rechazada = 'rechazada';
    case Suspendida = 'suspendida';
    case Inactiva = 'inactiva';
}
