<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use Illuminate\Support\Facades\Cache;

/**
 * Pantalla 1 del mockup: estado del entorno.
 * Los estados de los MCP y del asistente se declaran en .env (la conexión real
 * vive en la configuración de Claude Code, no en el panel); el staging de cada
 * proyecto sí se comprueba con una petición HTTP real (curl, timeout corto).
 */
class ConexionController extends Controller
{
    public function index()
    {
        $servicios = [
            ['nombre' => 'MCP de Elementor (oficial · beta 4.3)', 'detalle' => 'Atomic Editor · construye estructura nativa en borrador', 'estado' => env('ALMA_MCP_ELEMENTOR', 'demo')],
            ['nombre' => 'MCP de Figma', 'detalle' => 'Lectura estructurada del diseño (tokens y marcos)', 'estado' => env('ALMA_MCP_FIGMA', 'demo')],
            ['nombre' => 'Asistente (Claude Code)', 'detalle' => 'Motor del proceso · escribe este registro desde la consola', 'estado' => env('ALMA_ASISTENTE', 'demo')],
        ];

        $proyectos = Proyecto::orderBy('nombre')->get()->map(function ($p) {
            $p->estado_staging = $p->sitio_wp
                ? Cache::remember("staging.{$p->id}", 60, fn () => $this->probar($p->sitio_wp))
                : 'sin URL';

            return $p;
        });

        return view('panel.conexion', compact('servicios', 'proyectos'));
    }

    /** Comprobación HTTP mínima con curl nativo (timeout 2 s, solo cabeceras). */
    private function probar(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 2, CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return $codigo >= 200 && $codigo < 400 ? 'conectado' : 'sin conexión';
    }
}
