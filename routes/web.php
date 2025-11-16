<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TurnoController;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/', [TurnoController::class, 'mostrarFormulario'])->name('turno.formulario');


Route::post('/procesar', [TurnoController::class, 'procesarPdf'])->name('turno.procesar');
