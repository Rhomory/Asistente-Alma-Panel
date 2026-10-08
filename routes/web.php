<?php

use App\Http\Controllers\CarpetaController;
use App\Http\Controllers\ConexionController;
use App\Http\Controllers\EstadoController;
use App\Http\Controllers\FlujoController;
use App\Http\Controllers\GestionController;
use App\Http\Controllers\GuiaController;
use App\Http\Controllers\PanelController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PanelController::class, 'dashboard'])->name('dashboard');
Route::get('/registro', [PanelController::class, 'registro'])->name('registro');
Route::view('/como-funciona', 'panel.flujo')->name('flujo');
Route::get('/registro/export', [PanelController::class, 'exportCsv'])->name('registro.export');
Route::get('/estado/version', [EstadoController::class, 'version'])->name('estado.version');

// Conexiones: un sitio WordPress y un servidor MCP de Elementor por proyecto
Route::get('/conexion', [ConexionController::class, 'index'])->name('conexion');
Route::post('/conexiones', [ConexionController::class, 'guardar'])->name('conexion.guardar');
Route::post('/conexiones/comprobar', [ConexionController::class, 'comprobar'])->name('conexion.comprobar');
Route::delete('/conexiones/{conexion}', [ConexionController::class, 'eliminar'])->name('conexion.eliminar');

// Catálogo: proyectos, páginas y tokens de diseño
Route::get('/proyectos/nuevo', [GestionController::class, 'crear'])->name('proyecto.nuevo');
Route::post('/proyectos', [GestionController::class, 'guardar'])->name('proyecto.guardar');
Route::get('/proyectos/{proyecto}', [PanelController::class, 'proyecto'])->name('proyecto');
Route::delete('/proyectos/{proyecto}', [GestionController::class, 'eliminar'])->name('proyecto.eliminar');
Route::get('/proyectos/{proyecto}/guia', [GuiaController::class, 'show'])->name('proyecto.guia');
Route::get('/proyectos/{proyecto}/agents-md', [GuiaController::class, 'agentsMd'])->name('proyecto.agents');
Route::post('/proyectos/{proyecto}/paginas', [GestionController::class, 'guardarPagina'])->name('pagina.guardar');
Route::post('/paginas/{pagina}/incluir', [GestionController::class, 'incluirPagina'])->name('pagina.incluir');
Route::post('/proyectos/{proyecto}/tokens', [GestionController::class, 'guardarToken'])->name('token.guardar');
Route::post('/tokens/{token}/incluir', [GestionController::class, 'incluirToken'])->name('token.incluir');
Route::put('/tokens/{token}', [GestionController::class, 'actualizarToken'])->name('token.actualizar');
Route::delete('/tokens/{token}', [GestionController::class, 'eliminarToken'])->name('token.eliminar');

// Flujo de una página: plan, construcción supervisada y solicitud de QA
Route::get('/paginas/{pagina}', [FlujoController::class, 'pagina'])->name('pagina');
Route::post('/paginas/{pagina}/plan-estandar', [FlujoController::class, 'planEstandar'])->name('pagina.plan.estandar');
Route::post('/paginas/{pagina}/plan', [FlujoController::class, 'agregarPlan'])->name('pagina.plan.agregar');
Route::delete('/paginas/{pagina}/planificadas', [FlujoController::class, 'quitarPlanificadas'])->name('pagina.plan.limpiar');
Route::delete('/secciones/{seccion}/plan', [FlujoController::class, 'eliminarPlan'])->name('seccion.plan.eliminar');
Route::post('/secciones/{seccion}/aprobar', [FlujoController::class, 'aprobar'])->name('seccion.aprobar');
Route::post('/secciones/{seccion}/correccion', [FlujoController::class, 'pedirCorreccion'])->name('seccion.correccion');
Route::post('/paginas/{pagina}/url', [FlujoController::class, 'guardarUrl'])->name('pagina.url');
Route::post('/paginas/{pagina}/trello', [FlujoController::class, 'guardarTrello'])->name('pagina.trello');
Route::post('/paginas/{pagina}/qa', [FlujoController::class, 'qaSolicitado'])->name('pagina.qa');

// Carpeta del proyecto en Windows y versiones del diseño (Design/vN)
Route::post('/proyectos/{proyecto}/carpeta', [CarpetaController::class, 'preparar'])->name('proyecto.carpeta');
Route::post('/proyectos/{proyecto}/abrir/{que}', [CarpetaController::class, 'abrir'])->name('proyecto.abrir');
Route::post('/disenos/{diseno}/usar', [CarpetaController::class, 'usarDiseno'])->name('diseno.usar');
