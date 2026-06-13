<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AdminPasswordResetController;
use App\Http\Controllers\Api\AdminTwoFactorController;
use App\Http\Controllers\Api\AdminCajeroController;
use App\Http\Controllers\Api\AdminCocineroController;
use App\Http\Controllers\Api\CajeroController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminLicenseController;
use App\Http\Controllers\Api\AdminMesaController;
use App\Http\Controllers\Api\AdminPedidoCancelacionController;
use App\Http\Controllers\Api\AdminMeseroController;
use App\Http\Controllers\Api\AdminProductoController;
use App\Http\Controllers\Api\AdminReservaController;
use App\Http\Controllers\Api\AdminRestauranteConfigController;
use App\Http\Controllers\Api\AdminSubscriptionController;
use App\Http\Controllers\Api\AdminVentaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClienteReservaController;
use App\Http\Controllers\Api\CocinaPedidoController;
use App\Http\Controllers\Api\CocinaProductoController;
use App\Http\Controllers\Api\GastoController;
use App\Http\Controllers\Api\IngredienteController;
use App\Http\Controllers\Api\Master\MasterAuthController;
use App\Http\Controllers\Api\Master\MasterBillingController;
use App\Http\Controllers\Api\Master\MasterInvitationController;
use App\Http\Controllers\Api\Master\MasterPlatformController;
use App\Http\Controllers\Api\Master\MasterTenantAccessController;
use App\Http\Controllers\Api\Master\MasterTwoFactorController;
use App\Http\Controllers\Api\Master\OnboardingController;
use App\Http\Controllers\Api\MeseroController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Plataforma Master (BD master, sin subdominio de tenant)
|--------------------------------------------------------------------------
*/
Route::prefix('master')->group(function () {
    Route::post('auth/login', [MasterAuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('auth/two-factor-challenge', [MasterAuthController::class, 'twoFactorChallenge'])
        ->middleware('throttle:two-factor');
    Route::post('auth/two-factor-email', [MasterAuthController::class, 'resendTwoFactorEmail'])
        ->middleware('throttle:two-factor');

    Route::get('onboarding/{token}', [OnboardingController::class, 'show'])
        ->middleware('throttle:onboarding');
    Route::post('onboarding/{token}', [OnboardingController::class, 'complete'])
        ->middleware('throttle:onboarding-complete');

    Route::middleware('auth.master')->group(function () {
        Route::get('auth/me', [MasterAuthController::class, 'me']);
        Route::post('auth/logout', [MasterAuthController::class, 'logout']);
        Route::get('two-factor/status', [MasterTwoFactorController::class, 'status']);
        Route::post('two-factor/enable', [MasterTwoFactorController::class, 'enable']);
        Route::post('two-factor/confirm', [MasterTwoFactorController::class, 'confirm']);
        Route::delete('two-factor/disable', [MasterTwoFactorController::class, 'disable']);
        Route::get('platform/settings', [MasterPlatformController::class, 'settings']);
        Route::get('billing/settings', [MasterBillingController::class, 'settings']);
        Route::match(['put', 'post'], 'billing/settings', [MasterBillingController::class, 'updateSettings']);
        Route::get('billing/renewal-requests', [MasterBillingController::class, 'renewalRequests']);
        Route::get('billing/renewal-history', [MasterBillingController::class, 'renewalHistory']);
        Route::post('billing/renewal-requests/{renewalRequest}/approve', [MasterBillingController::class, 'approveRenewal']);
        Route::post('billing/renewal-requests/{renewalRequest}/reject', [MasterBillingController::class, 'rejectRenewal']);
        Route::get('tenants', [MasterInvitationController::class, 'index']);
        Route::post('invitations', [MasterInvitationController::class, 'store']);
        Route::post('tenants/{tenant}/resend-invitation', [MasterInvitationController::class, 'resend']);
        Route::post('tenants/{tenant}/suspend', [MasterTenantAccessController::class, 'suspend']);
        Route::post('tenants/{tenant}/extend-access', [MasterTenantAccessController::class, 'extendAccess']);
    });
});

/*
|--------------------------------------------------------------------------
| API del restaurante (requiere tenant en modo multi)
|--------------------------------------------------------------------------
*/
Route::middleware('tenant.identify')->group(function () {

    Route::get('public/productos-carta', [ProductoController::class, 'catalogoPublico']);

    Route::prefix('auth')->group(function () {
        // Staff y cliente: límite por IP + correo (AUTH_RATE_LIMIT en .env).
        Route::middleware('throttle:auth')->group(function () {
            Route::post('login', [AuthController::class, 'login']);
            Route::post('login-cliente', [AuthController::class, 'loginCliente']);
            Route::post('register-cliente', [AuthController::class, 'registerCliente']);
            Route::post('login-cocina', [AuthController::class, 'loginCocina']);
            Route::post('login-mesero', [AuthController::class, 'loginMesero']);
            Route::post('login-cajero', [AuthController::class, 'loginCajero']);
            Route::post('forgot-password', [AdminPasswordResetController::class, 'sendResetLink']);
            Route::post('reset-password', [AdminPasswordResetController::class, 'resetPassword']);
            Route::post('oauth/exchange', [AuthController::class, 'exchangeOAuthCode']);
        });

        // Admin: login propio + 2FA (Fortify rate limiters).
        Route::post('login-admin', [AdminAuthController::class, 'login'])
            ->middleware('throttle:login');
        Route::post('two-factor-challenge', [AdminAuthController::class, 'twoFactorChallenge'])
            ->middleware('throttle:two-factor');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    Route::middleware(['auth:sanctum', 'role:CLIENTE'])->prefix('cliente')->group(function () {
        Route::get('reservas', [ClienteReservaController::class, 'index']);
        Route::get('reservas/disponibilidad', [ClienteReservaController::class, 'disponibilidad']);
        Route::post('reservas', [ClienteReservaController::class, 'store']);
        Route::post('reservas/{reserva:idReserva}/cancelar', [ClienteReservaController::class, 'cancelar']);
    });

    Route::middleware(['auth:sanctum', 'role:MESERO'])->prefix('mesero')->group(function () {
        Route::get('mesas', [MeseroController::class, 'mesas']);
        Route::get('perfil', [MeseroController::class, 'perfil']);
        Route::get('pedidos/historial', [MeseroController::class, 'historialPedidos']);
        Route::get('alertas', [MeseroController::class, 'alertas']);
        Route::post('alertas/llamada-cocina/{llamada}/atender', [MeseroController::class, 'atenderLlamadaCocina']);
        Route::post('alertas/cambio-menu/{log:idLog}/atender', [MeseroController::class, 'atenderCambioMenu']);
        Route::post('pedidos/{pedido:idPedido}/recibir', [MeseroController::class, 'recibirPedido']);
        Route::get('categorias', [ProductoController::class, 'categoriasMesero']);
        Route::get('productos', [ProductoController::class, 'indexMesero']);
        Route::post('pedidos', [MeseroController::class, 'storePedido']);
        Route::post('pedidos/{pedido:idPedido}/cancelar', [MeseroController::class, 'cancelarPedido']);
        Route::post('pedidos/{pedido:idPedido}/enviar-caja', [MeseroController::class, 'enviarACaja']);
        Route::get('pedidos/{pedido:idPedido}', [MeseroController::class, 'showPedido']);
        Route::post('pedidos/{pedido:idPedido}/detalles', [MeseroController::class, 'storeDetalle']);
    });

    Route::middleware(['auth:sanctum', 'role:CAJERO'])->prefix('cajero')->group(function () {
        Route::get('cuentas-pendientes', [CajeroController::class, 'cuentasPendientes']);
        Route::get('perfil', [CajeroController::class, 'perfil']);
        Route::get('reservas', [CajeroController::class, 'reservas']);
        Route::post('llamar-mesero', [CajeroController::class, 'llamarMesero']);
        Route::get('mesas', [CajeroController::class, 'mesas']);
        Route::get('ventas', [CajeroController::class, 'ventas']);
        Route::get('ventas/{venta:idVenta}/factura', [CajeroController::class, 'factura']);
        Route::post('ventas/{venta:idVenta}/cancelar', [CajeroController::class, 'cancelarVenta']);
        Route::get('pedidos/{pedido:idPedido}', [CajeroController::class, 'showPedido']);
        Route::post('pedidos/{pedido:idPedido}/cobrar', [CajeroController::class, 'cobrar']);
    });

    Route::middleware(['auth:sanctum', 'role:COCINERO'])->prefix('cocina')->group(function () {
        Route::get('pedidos', [CocinaPedidoController::class, 'index']);
        Route::get('pedidos/historial', [CocinaPedidoController::class, 'historial']);
        Route::post('llamar-mesero', [CocinaPedidoController::class, 'llamarMesero']);
        Route::patch('pedidos/{pedido:idPedido}/estado', [CocinaPedidoController::class, 'updateEstado']);
        Route::post('pedidos/{pedido:idPedido}/detalles/{detalle:idPedidoDetalle}/cancelar', [CocinaPedidoController::class, 'cancelarDetalle']);

        Route::get('inventario/ingredientes', [IngredienteController::class, 'index']);
        Route::get('inventario/alertas', [IngredienteController::class, 'alertas']);
        Route::get('inventario/ingredientes/{ingrediente:idIngrediente}/movimientos', [IngredienteController::class, 'movimientos']);
        Route::post('inventario/ingredientes/{ingrediente:idIngrediente}/movimiento', [IngredienteController::class, 'registrarMovimiento']);

        Route::get('menu/productos', [CocinaProductoController::class, 'index']);
        Route::patch('menu/productos/{producto:idProducto}/activo', [CocinaProductoController::class, 'setActivo']);
    });

    Route::middleware(['auth:sanctum', 'role:ADMINISTRADOR'])->prefix('admin')->group(function () {
        Route::get('licencia', [AdminLicenseController::class, 'status']);
        Route::get('suscripcion', [AdminSubscriptionController::class, 'show']);
        Route::post('suscripcion/renovacion', [AdminSubscriptionController::class, 'storeRenewal']);
        Route::get('dashboard', [AdminDashboardController::class, 'index']);

        Route::get('ventas', [AdminVentaController::class, 'index']);
        Route::get('ventas/notificaciones', [AdminVentaController::class, 'notificacionesPendientes']);
        Route::post('ventas/notificaciones/marcar-vistas', [AdminVentaController::class, 'marcarNotificacionesVistas']);

        Route::get('reservas', [AdminReservaController::class, 'index']);
        Route::get('pedidos-platos-cancelados', [AdminPedidoCancelacionController::class, 'index']);

        Route::get('mesas', [AdminMesaController::class, 'index']);
        Route::post('mesas', [AdminMesaController::class, 'store']);
        Route::put('mesas/{mesa:idMesa}', [AdminMesaController::class, 'update']);
        Route::patch('mesas/{mesa:idMesa}/activo', [AdminMesaController::class, 'setActivo']);
        Route::delete('mesas/{mesa:idMesa}', [AdminMesaController::class, 'destroy']);
        Route::post('mesas/{mesa:idMesa}/restaurar', [AdminMesaController::class, 'restaurar']);
        Route::get('mesas/{mesa:idMesa}/historial', [AdminMesaController::class, 'historialPedidos']);

        Route::get('productos', [AdminProductoController::class, 'index']);
        Route::get('productos/historial-activo', [AdminProductoController::class, 'historialActivo']);
        Route::post('productos', [AdminProductoController::class, 'store']);
        Route::get('productos/{producto:idProducto}', [AdminProductoController::class, 'show']);
        Route::match(['put', 'post'], 'productos/{producto:idProducto}', [AdminProductoController::class, 'update']);
        Route::patch('productos/{producto:idProducto}/activo', [AdminProductoController::class, 'setActivo']);
        Route::delete('productos/{producto:idProducto}', [AdminProductoController::class, 'destroy']);
        Route::post('productos/{producto:idProducto}/restaurar', [AdminProductoController::class, 'restaurar']);

        Route::get('meseros', [AdminMeseroController::class, 'index']);
        Route::post('meseros', [AdminMeseroController::class, 'store']);
        Route::put('meseros/{usuario:idUsuario}', [AdminMeseroController::class, 'update']);
        Route::patch('meseros/{usuario:idUsuario}/activo', [AdminMeseroController::class, 'setActivo']);

        Route::get('cocineros', [AdminCocineroController::class, 'index']);
        Route::post('cocineros', [AdminCocineroController::class, 'store']);
        Route::put('cocineros/{usuario:idUsuario}', [AdminCocineroController::class, 'update']);
        Route::patch('cocineros/{usuario:idUsuario}/activo', [AdminCocineroController::class, 'setActivo']);

        Route::get('cajeros', [AdminCajeroController::class, 'index']);
        Route::post('cajeros', [AdminCajeroController::class, 'store']);
        Route::put('cajeros/{usuario:idUsuario}', [AdminCajeroController::class, 'update']);
        Route::patch('cajeros/{usuario:idUsuario}/activo', [AdminCajeroController::class, 'setActivo']);

        Route::get('restaurante-config', [AdminRestauranteConfigController::class, 'show']);

        Route::get('reportes/ventas-hoy', [ReporteController::class, 'ventasHoy']);
        Route::get('reportes/ventas', [ReporteController::class, 'ventasPorFecha']);
        Route::get('reportes/productos-mas-vendidos', [ReporteController::class, 'productosMasVendidos']);

        Route::get('inventario/alertas', [IngredienteController::class, 'alertas']);
        Route::get('inventario/movimientos', [IngredienteController::class, 'historialGlobal']);
        Route::get('inventario/ingredientes', [IngredienteController::class, 'index']);
        Route::post('inventario/ingredientes', [IngredienteController::class, 'store']);
        Route::put('inventario/ingredientes/{ingrediente:idIngrediente}', [IngredienteController::class, 'update']);
        Route::post('inventario/ingredientes/{ingrediente:idIngrediente}/movimiento', [IngredienteController::class, 'registrarMovimiento']);
        Route::get('inventario/ingredientes/{ingrediente:idIngrediente}/movimientos', [IngredienteController::class, 'movimientos']);

        Route::get('finanzas/gastos', [GastoController::class, 'index']);
        Route::post('finanzas/gastos', [GastoController::class, 'store']);
        Route::put('finanzas/gastos/{gasto:idGasto}', [GastoController::class, 'update']);
        Route::delete('finanzas/gastos/{gasto:idGasto}', [GastoController::class, 'destroy']);
        Route::get('finanzas/pyg', [GastoController::class, 'pyg']);

        Route::get('usuarios', [UsuarioController::class, 'index']);
        Route::post('usuarios', [UsuarioController::class, 'store']);
        Route::put('usuarios/{usuario:idUsuario}', [UsuarioController::class, 'update']);
        Route::patch('usuarios/{usuario:idUsuario}/activo', [UsuarioController::class, 'setActivo']);

        Route::get('two-factor/status', [AdminTwoFactorController::class, 'status']);
        Route::post('two-factor/enable', [AdminTwoFactorController::class, 'enable']);
        Route::post('two-factor/confirm', [AdminTwoFactorController::class, 'confirm']);
        Route::post('two-factor/recovery-codes', [AdminTwoFactorController::class, 'recoveryCodes']);
        Route::delete('two-factor/disable', [AdminTwoFactorController::class, 'disable']);
    });
});
