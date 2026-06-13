<?php

namespace App\Policies;

use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Auth\Access\Response;

class PedidoPolicy
{
    /**
     * Solo el mesero dueño del pedido puede verlo o gestionarlo.
     */
    public function gestionar(Usuario $usuario, Pedido $pedido): Response
    {
        return (int) $pedido->mesero_idUsuario === (int) $usuario->getAuthIdentifier()
            ? Response::allow()
            : Response::deny('No autorizado para este pedido.');
    }
}
