<?php

namespace App\Policies;

use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Auth\Access\Response;

class VentaPolicy
{
    /**
     * Solo el cajero que registró la venta puede verla / imprimir su factura.
     */
    public function ver(Usuario $usuario, Venta $venta): Response
    {
        return (int) $venta->cajero_idUsuario === (int) $usuario->getAuthIdentifier()
            ? Response::allow()
            : Response::deny('No puedes ver una factura de otro cajero.');
    }

    /**
     * Solo el cajero que registró la venta puede cancelarla.
     */
    public function cancelar(Usuario $usuario, Venta $venta): Response
    {
        return (int) $venta->cajero_idUsuario === (int) $usuario->getAuthIdentifier()
            ? Response::allow()
            : Response::deny('No puedes cancelar una venta de otro cajero.');
    }
}
