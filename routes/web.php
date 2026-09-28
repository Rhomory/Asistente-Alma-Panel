<?php

use App\Http\Controllers\ConexionController;
use App\Http\Controllers\FlujoController;
use App\Http\Controllers\GestionController;
use App\Http\Controllers\PanelController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PanelController::class, 'dashboard'])->name('dashboard');
Route::get('/conexion', [ConexionController::class, 'index'])->name('conexion');
Route::get('/registro', [PanelController::class, 'registro'])->name('registro');
Route::get('/registro/export', [PanelController::class, 'exportCsv'])->name('registro.export');

// Catálogo (P2: proyectos, páginas y tokens de diseño)
Route::get('/proyectos/nuevo', [GestionController::class, 'crear'])->name('proyecto.nuevo');
Route::post('/proyectos', [GestionController::class, 'guardar'])->name('proyecto.guardar');
Route::get('/proyectos/{proyecto}', [PanelController::class, 'proyecto'])->name('proyecto');
Route::post('/proyectos/{proyecto}/paginas', [GestionController::class, 'guardarPagina'])->name('pagina.guardar');
Route::post('/proyectos/{proyecto}/tokens', [GestionController::class, 'guardarToken'])->name('token.guardar');
Route::delete('/tokens/{token}', [GestionController::class, 'eliminarToken'])->name('token.eliminar');

// Flujo de una página (P3 Plan + P4 Construcción)
Route::get('/paginas/{pagina}', [FlujoController::class, 'pagina'])->name('pagina');
Route::post('/paginas/{pagina}/plan-estandar', [FlujoController::class, 'planEstandar'])->name('pagina.plan.estandar');
Route::post('/paginas/{pagina}/plan', [FlujoController::class, 'agregarPlan'])->name('pagina.plan.agregar');
Route::delete('/secciones/{seccion}/plan', [FlujoController::class, 'eliminarPlan'])->name('seccion.plan.eliminar');
Route::post('/secciones/{seccion}/aprobar', [FlujoController::class, 'aprobar'])->name('seccion.aprobar');
Route::post('/secciones/{seccion}/correccion', [FlujoController::class, 'pedirCorreccion'])->name('seccion.correccion');
Route::post('/paginas/{pagina}/enviar-qc', [FlujoController::class, 'enviarQC'])->name('pagina.qc');
