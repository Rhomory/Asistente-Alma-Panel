<?php

namespace App\Http\Controllers;

use App\Models\Diseno;
use App\Models\Proyecto;
use App\Support\CarpetaProyecto;
use App\Support\Disenos;
use App\Support\Windows;

/** Carpeta del proyecto en Windows (crear, abrir) y versiones del diseño (usar una versión). */
class CarpetaController extends Controller
{
    public function preparar(Proyecto $proyecto)
    {
        if (! CarpetaProyecto::disponible()) {
            return back()->with('ok', 'Falta configurar la carpeta base de proyectos: ejecuta scripts\instalar-windows.ps1 (escribe ALMA_PROYECTOS_DIR en .env).');
        }
        $archivos = CarpetaProyecto::preparar($proyecto);

        return back()->with('ok', count($archivos) . ' archivos del agente escritos en ' . CarpetaProyecto::rutaWindows($proyecto->fresh()) . '.');
    }

    public function abrir(Proyecto $proyecto, string $que)
    {
        abort_unless(in_array($que, ['carpeta', 'diseno', 'terminal', 'cursor'], true), 404);
        if (! $proyecto->carpeta) {
            CarpetaProyecto::preparar($proyecto);
            $proyecto->refresh();
        }
        $ruta = CarpetaProyecto::rutaWindows($proyecto);
        if (! $ruta) {
            return back()->with('ok', 'El proyecto aún no tiene carpeta: configura la carpeta base con el instalador.');
        }

        $ok = match ($que) {
            'carpeta'  => Windows::abrirCarpeta($ruta),
            'diseno'   => Windows::abrirCarpeta($ruta . '\Design'),
            'terminal' => Windows::abrirTerminal($ruta),
            'cursor'   => Windows::abrirCursor($ruta),
        };

        return back()->with('ok', $ok
            ? match ($que) {
                'carpeta'  => "Carpeta abierta: {$ruta}",
                'diseno'   => "Carpeta del diseño abierta: {$ruta}\Design",
                'terminal' => 'Terminal abierta en la carpeta del proyecto. Inicia ahí tu agente (claude, codex u opencode).',
                'cursor'   => 'Cursor abierto en la carpeta del proyecto.',
            }
            : 'No pude abrirlo desde el panel: revisa ALMA_PUENTE en .env (lo escribe el instalador).');
    }

    public function usarDiseno(Diseno $diseno)
    {
        $r = Disenos::adoptar($diseno);

        return back()->with('ok', "Diseño v{$diseno->version} en uso: {$r['paginas']} páginas, {$r['secciones']} secciones nuevas y {$r['tokens']} tokens nuevos.");
    }
}
