<?php

use App\Http\Controllers\PanelController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PanelController::class, 'dashboard'])->name('dashboard');
Route::get('/proyectos/{proyecto}', [PanelController::class, 'proyecto'])->name('proyecto');
Route::get('/registro', [PanelController::class, 'registro'])->name('registro');
Route::get('/registro/export', [PanelController::class, 'exportCsv'])->name('registro.export');
