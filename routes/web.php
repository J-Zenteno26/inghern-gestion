<?php

use App\Http\Controllers\Clientes\ClienteController;
use App\Http\Controllers\Cotizaciones\CotizacionController;
use App\Http\Controllers\Cotizaciones\FacturaController;
use App\Http\Controllers\Cotizaciones\OrdenCompraController;
use App\Http\Controllers\Cotizaciones\PagoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\PlantaController;
use App\Http\Controllers\Servicios\ReferenciaPrecioController;
use App\Http\Controllers\Servicios\ServicioController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/inicio');

Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {
    Route::get('/inicio', DashboardController::class)->name('dashboard');
    Route::get('/biblioteca', [DocumentoController::class, 'index'])
        ->name('biblioteca.index');
    Route::post('/biblioteca/documentos', [DocumentoController::class, 'storeFromLibrary'])
        ->name('biblioteca.documentos.store');
    Route::get(
        '/biblioteca/{cliente}/documentos/{documento}/descargar',
        [DocumentoController::class, 'downloadFromLibrary'],
    )->name('biblioteca.documentos.download');
    Route::get(
        '/biblioteca/{cliente}/documentos/{documento}/preview',
        [DocumentoController::class, 'previewFromLibrary'],
    )->name('biblioteca.documentos.preview');
    Route::get(
        '/biblioteca/{cliente}/documentos/{documento}/vinculos',
        [DocumentoController::class, 'links'],
    )->name('biblioteca.documentos.links');
    Route::post(
        '/biblioteca/{cliente}/documentos/{documento}/vinculos',
        [DocumentoController::class, 'storeLink'],
    )->name('biblioteca.documentos.links.store');
    Route::delete(
        '/biblioteca/{cliente}/documentos/{documento}/vinculos/{documentoVinculo}',
        [DocumentoController::class, 'destroyLink'],
    )->name('biblioteca.documentos.links.destroy');
    Route::resource('clientes', ClienteController::class)->except(['destroy']);
    Route::resource('plantas', PlantaController::class)->only(['index', 'show']);
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
        '/cotizaciones/{cotizacion}/planta',
        [CotizacionController::class, 'updatePlanta'],
    )->name('cotizaciones.planta.update');
    Route::patch(
        '/cotizaciones/{cotizacion}/aceptar',
        [CotizacionController::class, 'aceptar'],
    )->name('cotizaciones.aceptar');
    Route::post(
        '/cotizaciones/{cotizacion}/orden-compra',
        [OrdenCompraController::class, 'store'],
    )->name('cotizaciones.orden-compra.store');
    Route::post(
        '/clientes/{cliente}/documentos',
        [DocumentoController::class, 'storeForCliente'],
    )->name('clientes.documentos.store');
    Route::get(
        '/clientes/{cliente}/documentos/{documento}/descargar',
        [DocumentoController::class, 'downloadForCliente'],
    )->name('clientes.documentos.download');
    Route::delete(
        '/clientes/{cliente}/documentos/{documento}',
        [DocumentoController::class, 'destroyForCliente'],
    )->name('clientes.documentos.destroy');
    Route::post(
        '/cotizaciones/{cotizacion}/documentos',
        [DocumentoController::class, 'storeForCotizacion'],
    )->name('cotizaciones.documentos.store');
    Route::get(
        '/cotizaciones/{cotizacion}/documentos/{documento}/descargar',
        [DocumentoController::class, 'downloadForCotizacion'],
    )->name('cotizaciones.documentos.download');
    Route::delete(
        '/cotizaciones/{cotizacion}/documentos/{documento}',
        [DocumentoController::class, 'destroyForCotizacion'],
    )->name('cotizaciones.documentos.destroy');
});

Route::post('/salir', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})
    ->middleware('auth')
    ->name('logout');
