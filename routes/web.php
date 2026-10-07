<?php

use App\Http\Controllers\Clientes\ClienteController;
use App\Http\Controllers\Cotizaciones\CotizacionController;
use App\Http\Controllers\Cotizaciones\FacturaController;
use App\Http\Controllers\Cotizaciones\OrdenCompraController;
use App\Http\Controllers\Cotizaciones\PagoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Servicios\ReferenciaPrecioController;
use App\Http\Controllers\Servicios\ServicioController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/inicio');

Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {
    Route::get('/inicio', DashboardController::class)->name('dashboard');
    Route::resource('clientes', ClienteController::class)->except(['destroy']);
    Route::get(
        '/servicios/referencias-precio',
        [ReferenciaPrecioController::class, 'index'],
    )->name('servicios.referencias-precio.index');
    Route::patch(
        '/servicios/referencias-precio/{catalogoServicio}',
        [ReferenciaPrecioController::class, 'update'],
    )->name('servicios.referencias-precio.update');
    Route::resource('servicios', ServicioController::class)->only([
        'index',
        'create',
        'store',
        'show',
    ]);
    Route::resource('cotizaciones', CotizacionController::class)->only([
        'index',
        'create',
        'store',
        'show',
    ])->parameters(['cotizaciones' => 'cotizacion']);
    Route::resource('facturas', FacturaController::class)->only([
        'index',
        'create',
        'store',
        'show',
    ]);
    Route::resource('pagos', PagoController::class)->only([
        'index',
        'create',
        'store',
        'show',
    ]);
    Route::patch(
        '/facturas/{factura}/fecha-pago-informada',
        [FacturaController::class, 'actualizarFechaPago'],
    )->name('facturas.fecha-pago-informada.update');
    Route::patch(
        '/cotizaciones/{cotizacion}/aceptar',
        [CotizacionController::class, 'aceptar'],
    )->name('cotizaciones.aceptar');
    Route::post(
        '/cotizaciones/{cotizacion}/orden-compra',
        [OrdenCompraController::class, 'store'],
    )->name('cotizaciones.orden-compra.store');
});

Route::post('/salir', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})
    ->middleware('auth')
    ->name('logout');
