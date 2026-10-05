<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

/**
 * Huella de los datos para la recarga en vivo: el panel la consulta cada pocos segundos
 * y se recarga cuando la consola escribe algo (páginas, tokens, secciones, eventos).
 */
class EstadoController extends Controller
{
    public function version()
    {
        $partes = [];
        foreach (['proyectos', 'paginas', 'secciones', 'tokens', 'conexiones'] as $tabla) {
            $fila = DB::table($tabla)->selectRaw('COUNT(*) as n, MAX(updated_at) as u')->first();
            $partes[] = "{$fila->n}:{$fila->u}";
        }
        $partes[] = (string) DB::table('eventos')->max('id');

        return response()->json(['v' => md5(implode('|', $partes))])
            ->header('Cache-Control', 'no-store');
    }
}
