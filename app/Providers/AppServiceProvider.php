<?php

namespace App\Providers;

use App\Models\Evento;
use App\Models\Proyecto;
use Carbon\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('es');

        // Datos comunes del marco: menú de proyectos, cola y qué está haciendo el asistente.
        View::composer('layouts.app', function ($view) {
            $ultimo = Evento::with('seccion.pagina')->where('tipo', 'construccion')->latest('id')->first();
            $activo = $ultimo && $ultimo->created_at->gt(now()->subMinutes(20));

            $view->with([
                'navProyectos' => Proyecto::withCount([
                    'paginas as p_total',
                    'paginas as p_obra' => fn ($q) => $q->where('estado', 'construyendo'),
                    'paginas as p_listas' => fn ($q) => $q->whereIn('estado', ['aprobada', 'en_qc']),
                ])->orderByDesc('updated_at')->limit(6)->get(),
                'navTotal'     => Proyecto::count(),
                'navCola'      => Evento::where('tipo', 'solicitud_correccion')->where('resuelto', false)->count(),
                'asistente'    => [
                    'activo'  => $activo,
                    'detalle' => $ultimo?->seccion
                        ? "{$ultimo->seccion->pagina?->nombre} › {$ultimo->seccion->nombre}"
                        : null,
                    'hace'    => $ultimo?->created_at->diffForHumans(),
                ],
            ]);
        });
    }
}
