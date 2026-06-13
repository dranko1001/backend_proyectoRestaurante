<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Categoria;
use App\Models\Gasto;
use App\Models\Ingrediente;
use App\Models\InventarioIngrediente;
use App\Models\Mesa;
use App\Models\MovimientoIngrediente;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\RecetaDetalle;
use App\Models\Reserva;
use App\Models\RestauranteConfig;
use App\Models\Usuario;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RestauranteSeeder extends Seeder
{
    public function run(): void
    {
        $this->limpiarTablas();

        // 1) Cargos (5)
        $cargos = collect([
            'CLIENTE',
            'MESERO',
            'COCINERO',
            'CAJERO',
            'ADMINISTRADOR',
        ])->map(fn ($nombre) => Cargo::create(['nombre' => $nombre]))->keyBy('nombre');

        // 2) Usuarios (6) (5 roles + 1 extra cliente)
        $cliente = Usuario::create([
            'nombre' => 'Cliente',
            'apellido' => 'Demo',
            'cedula' => '100000001',
            'telefono' => '3000000001',
            'correo' => 'cliente@gmail.com',
            'password' => 'clientee',
            'cargos_idCargo' => $cargos['CLIENTE']->idCargo,
            'activo' => true,
            'creado_en' => now(),
        ]);

        $mesero = Usuario::create([
            'nombre' => 'Mesero',
            'apellido' => 'Demo',
            'cedula' => '100000002',
            'telefono' => '3000000002',
            'correo' => 'mesero@gmail.com',
            'password' => 'meseroo',
            'cargos_idCargo' => $cargos['MESERO']->idCargo,
            'activo' => true,
            'creado_en' => now(),
        ]);

        $cocinero = Usuario::create([
            'nombre' => 'Cocinero',
            'apellido' => 'Demo',
            'cedula' => '100000003',
            'telefono' => '3000000003',
            'correo' => 'cocinero@gmail.com',
            'password' => 'cocineroo',
            'cargos_idCargo' => $cargos['COCINERO']->idCargo,
            'activo' => true,
            'creado_en' => now(),
        ]);

        $cajero = Usuario::create([
            'nombre' => 'Cajero',
            'apellido' => 'Demo',
            'cedula' => '100000004',
            'telefono' => '3000000004',
            'correo' => 'cajero@gmail.com',
            'password' => 'cajeroo',
            'cargos_idCargo' => $cargos['CAJERO']->idCargo,
            'activo' => true,
            'creado_en' => now(),
        ]);

        $admin = Usuario::create([
            'nombre' => 'Admin',
            'apellido' => 'Demo',
            'cedula' => '100000005',
            'telefono' => '3000000005',
            'correo' => 'admin@gmail.com',
            'password' => 'adminn',
            'cargos_idCargo' => $cargos['ADMINISTRADOR']->idCargo,
            'activo' => true,
            'creado_en' => now(),
        ]);

        $cliente2 = Usuario::create([
            'nombre' => 'Cliente2',
            'apellido' => 'Demo',
            'cedula' => '100000006',
            'telefono' => '3000000006',
            'correo' => 'cliente2@gmail.com',
            'password' => 'cliente22',
            'cargos_idCargo' => $cargos['CLIENTE']->idCargo,
            'activo' => true,
            'creado_en' => now(),
        ]);

        // 3) Categorías (5)
        $categorias = collect([
            ['nombre' => 'Entradas', 'orden' => 1, 'activa' => true],
            ['nombre' => 'Platos fuertes', 'orden' => 2, 'activa' => true],
            ['nombre' => 'Bebidas', 'orden' => 3, 'activa' => true],
            ['nombre' => 'Postres', 'orden' => 4, 'activa' => true],
            ['nombre' => 'Combos', 'orden' => 5, 'activa' => true],
        ])->map(fn ($data) => Categoria::create($data));

        // 4) Ingredientes (5) + InventarioIngrediente (5)
        $ingredientes = collect([
            ['nombreIngrediente' => 'Arroz', 'unidad' => 'g'],
            ['nombreIngrediente' => 'Pollo', 'unidad' => 'g'],
            ['nombreIngrediente' => 'Papa', 'unidad' => 'g'],
            ['nombreIngrediente' => 'Aceite', 'unidad' => 'ml'],
            ['nombreIngrediente' => 'Sal', 'unidad' => 'g'],
        ])->map(fn ($data) => Ingrediente::create($data));

        $ingredientes->each(function (Ingrediente $ing) {
            InventarioIngrediente::create([
                'ingrediente_idIngrediente' => $ing->idIngrediente,
                'stock' => 5000,
                'stock_minimo' => 800,
                'actualizado_en' => now(),
            ]);
        });

        // 5) Recetas (5)
        $recetas = collect([
            ['nombre' => 'Receta Arroz con Pollo', 'rendimiento' => 4],
            ['nombre' => 'Receta Papas Fritas', 'rendimiento' => 6],
            ['nombre' => 'Receta Pollo a la Plancha', 'rendimiento' => 3],
            ['nombre' => 'Receta Arroz Simple', 'rendimiento' => 8],
            ['nombre' => 'Receta Papa Cocida', 'rendimiento' => 5],
        ])->map(fn ($data) => Receta::create($data));

        // 6) RecetaDetalle (5)
        for ($i = 0; $i < 5; $i++) {
            $receta = $recetas[$i];
            $ing = $ingredientes[$i];

            RecetaDetalle::create([
                'receta_idReceta' => $receta->idReceta,
                'ingrediente_idIngrediente' => $ing->idIngrediente,
                'cantidad' => 150 + ($i * 25),
            ]);
        }

        // 7) Productos (5)
        $productos = collect([
            [
                'nombreProducto' => 'Arroz con Pollo',
                'precio' => 18000,
                'descripcion' => 'Plato típico, porción personal.',
                'tipo' => 'PLATO',
                'categoria_idCategoria' => $categorias[1]->idCategoria,
                'receta_idReceta' => $recetas[0]->idReceta,
                'activo' => true,
            ],
            [
                'nombreProducto' => 'Papas Fritas',
                'precio' => 9000,
                'descripcion' => 'Porción mediana.',
                'tipo' => 'PLATO',
                'categoria_idCategoria' => $categorias[0]->idCategoria,
                'receta_idReceta' => $recetas[1]->idReceta,
                'activo' => true,
            ],
            [
                'nombreProducto' => 'Limonada',
                'precio' => 6000,
                'descripcion' => 'Natural.',
                'tipo' => 'BEBIDA',
                'categoria_idCategoria' => $categorias[2]->idCategoria,
                'receta_idReceta' => null,
                'activo' => true,
            ],
            [
                'nombreProducto' => 'Postre de la casa',
                'precio' => 7000,
                'descripcion' => 'Varía según el día.',
                'tipo' => 'PLATO',
                'categoria_idCategoria' => $categorias[3]->idCategoria,
                'receta_idReceta' => $recetas[3]->idReceta,
                'activo' => true,
            ],
            [
                'nombreProducto' => 'Combo Pollo + Bebida',
                'precio' => 22000,
                'descripcion' => 'Incluye bebida.',
                'tipo' => 'COMBO',
                'categoria_idCategoria' => $categorias[4]->idCategoria,
                'receta_idReceta' => $recetas[2]->idReceta,
                'activo' => true,
            ],
        ])->map(fn ($data) => Producto::create($data));

        // 8) Mesas (5)
        $mesas = collect([1, 2, 3, 4, 5])->map(function (int $n) {
            return Mesa::create([
                'numero' => $n,
                'nombre' => "Mesa $n",
                'capacidad' => 2 + ($n % 4),
                'estado' => 'LIBRE',
                'activa' => true,
            ]);
        });

        // 9) Restaurante Config (5) (aunque normalmente sería 1)
        $configs = collect(range(1, 5))->map(function (int $i) {
            return RestauranteConfig::create([
                'idConfig' => $i,
                'nombre_comercial' => "Restaurante Demo $i",
                'nit_o_documento' => "90000000$i",
                'telefono' => "60100000$i",
                'direccion' => "Calle $i # 10-20",
                'logo_url' => null,
                'actualizado_en' => now(),
            ]);
        });

        // 10) Reservas (5)
        $clientes = collect([$cliente, $cliente2]);
        $reservas = collect(range(0, 4))->map(function (int $i) use ($clientes, $mesas) {
            $fecha = Carbon::now()->addDays($i + 1)->setTime(19, 0);

            return Reserva::create([
                'cliente_idUsuario' => $clientes[$i % $clientes->count()]->idUsuario,
                'mesa_idMesa' => $i < 3 ? $mesas[$i]->idMesa : null,
                'fecha_hora' => $fecha,
                'num_personas' => 2 + ($i % 4),
                'estado' => $i === 0 ? 'CONFIRMADA' : 'SOLICITADA',
                'notas' => $i === 2 ? 'Cumpleaños (decoración simple).' : null,
                'creado_en' => now(),
            ]);
        });

        // 11) Pedidos (5)
        $pedidos = collect(range(0, 4))->map(function (int $i) use ($mesas, $mesero, $reservas) {
            return Pedido::create([
                'mesa_idMesa' => $mesas[$i]->idMesa,
                'mesero_idUsuario' => $mesero->idUsuario,
                'reserva_idReserva' => $i < 2 ? $reservas[$i]->idReserva : null,
                'estado' => 'PENDIENTE',
                'notas' => $i === 1 ? 'Sin cebolla.' : null,
                'creado_en' => now(),
                'actualizado_en' => now(),
                'cerrado_en' => null,
            ]);
        });

        // 12) Pedido Detalle (5) (1 item por pedido)
        $pedidoDetalles = collect(range(0, 4))->map(function (int $i) use ($pedidos, $productos) {
            $pedido = $pedidos[$i];
            $producto = $productos[$i];
            $cantidad = 1 + ($i % 3);

            return PedidoDetalle::create([
                'pedido_idPedido' => $pedido->idPedido,
                'producto_idProducto' => $producto->idProducto,
                'cantidad' => $cantidad,
                'precio_unitario' => $producto->precio,
                'nota' => null,
                'estado_item' => 'PENDIENTE',
                'creado_en' => now(),
            ]);
        });

        // 13) Ventas (5) (1 por pedido, cajero admin)
        $ventas = $pedidos->map(function (Pedido $pedido, int $i) use ($admin, $pedidoDetalles) {
            $detalle = $pedidoDetalles[$i];
            $subtotal = (float) $detalle->precio_unitario * (int) $detalle->cantidad;
            $impuesto = 0.0;

            return Venta::create([
                'pedido_idPedido' => $pedido->idPedido,
                'subtotal' => $subtotal,
                'impuesto_o_servicio' => $impuesto,
                'total' => $subtotal + $impuesto,
                'registrada_en' => now(),
                'cajero_idUsuario' => $admin->idUsuario,
            ]);
        });

        // 14) Pagos (5) (1 por venta)
        $metodosPago = ['EFECTIVO', 'TARJETA', 'NEQUI', 'DAVIPLATA', 'EFECTIVO'];
        $ventas->each(function (Venta $venta, int $i) use ($metodosPago) {
            Pago::create([
                'venta_idVenta' => $venta->idVenta,
                'metodo' => $metodosPago[$i],
                'valor' => $venta->total,
                'referencia' => $metodosPago[$i] === 'EFECTIVO' ? null : ('REF-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)),
                'pagado_en' => now(),
            ]);
        });

        // 15) Gastos (5) (registrados por admin)
        $categoriasGasto = ['arriendo', 'servicios', 'insumos', 'otros', 'insumos'];
        $metodosGasto = ['EFECTIVO', 'TARJETA', 'NEQUI', 'DAVIPLATA', 'OTRO'];
        foreach (range(0, 4) as $i) {
            Gasto::create([
                'categoria' => $categoriasGasto[$i],
                'descripcion' => "Gasto demo #".($i + 1),
                'valor' => 25000 + ($i * 5000),
                'fecha' => Carbon::now()->subDays(5 - $i),
                'metodo' => $metodosGasto[$i],
                'registrado_por_idUsuario' => $admin->idUsuario,
            ]);
        }

        // 16) Movimientos de ingrediente (5)
        $ingredientes->each(function (Ingrediente $ing, int $i) use ($admin) {
            MovimientoIngrediente::create([
                'tipo' => $i % 2 === 0 ? 'ENTRADA' : 'AJUSTE',
                'cantidad' => 250 + ($i * 50),
                'motivo' => $i % 2 === 0 ? 'Compra de insumos' : 'Ajuste de inventario',
                'referencia' => 'SEED-' . ($i + 1),
                'fecha' => now(),
                'ingrediente_idIngrediente' => $ing->idIngrediente,
                'usuario_idUsuario' => $admin->idUsuario,
            ]);
        });
    }

    private function limpiarTablas(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Hijas -> Padres (para evitar problemas incluso si FK checks están ON)
        $tablas = [
            'pago',
            'venta',
            'pedido_detalle',
            'pedido',
            'reserva',
            'movimientoingrediente',
            'inventarioingrediente',
            'recetadetalle',
            'producto',
            'receta',
            'ingrediente',
            'gasto',
            'mesa',
            'usuario',
            'categoria',
            'cargos',
            'restaurante_config',
        ];

        foreach ($tablas as $tabla) {
            DB::table($tabla)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}

