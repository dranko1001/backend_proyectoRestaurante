<?php

namespace App\Policies;

use App\Models\Reserva;
use App\Models\Usuario;
use Illuminate\Auth\Access\Response;

class ReservaPolicy
{
    /**
     * Solo el cliente dueño de la reserva puede cancelarla.
     */
    public function cancelar(Usuario $usuario, Reserva $reserva): Response
    {
        return (int) $reserva->cliente_idUsuario === (int) $usuario->getAuthIdentifier()
            ? Response::allow()
            : Response::deny('No autorizado para esta reserva.');
    }
}
