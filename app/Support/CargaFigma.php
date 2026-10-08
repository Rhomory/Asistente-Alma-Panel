<?php

namespace App\Support;

use App\Http\Controllers\FlujoController;
use App\Models\Pagina;
use App\Models\Proyecto;

/**
 * Aplica una lectura del Figma al panel: enlace del archivo, páginas (con ID de Figma y URL), secciones
 * planificadas con su ID y tokens. No borra nada y no duplica: reconoce por ID de Figma o por nombre.
 */
class CargaFigma
{
    /** @return array{paginas: int, secciones: int, tokens: int, avisos: string[]} */
    public static function aplicar(Proyecto $proyecto, array $datos): array
    {
        $resumen = ['paginas' => 0, 'secciones' => 0, 'tokens' => 0, 'avisos' => []];

        $figma = trim((string) ($datos['figma'] ?? ''));
        if ($figma !== '') {
            if (preg_match('#^https://(www\.)?figma\.com/#', $figma)) {
                $proyecto->update(['archivo_figma' => $figma]);
            } else {
                $resumen['avisos'][] = "Enlace de Figma ignorado (debe ser https://www.figma.com/…): {$figma}";
            }
        }

        foreach ($datos['paginas'] ?? [] as $p) {
            if (is_array($p) && trim((string) ($p['nombre'] ?? '')) !== '') {
                $resumen['secciones'] += self::pagina($proyecto, $p);
                $resumen['paginas']++;
            }
        }

        foreach ($datos['tokens'] ?? [] as $t) {
            if (! is_array($t)) {
                continue;
            }
            $tipo = strtolower(trim((string) ($t['tipo'] ?? '')));
            $valor = trim((string) ($t['valor'] ?? ''));
            if ($valor === '' || ! in_array($tipo, ['color', 'tipografia', 'espaciado', 'otro'], true)) {
                $resumen['avisos'][] = "Token omitido (tipo o valor no válido): {$tipo} {$valor}";
                continue;
            }
            $existe = $proyecto->tokens()->where('tipo', $tipo)->whereRaw('LOWER(valor) = ?', [mb_strtolower($valor)])->exists();
            if (! $existe) {
                $proyecto->tokens()->create(['tipo' => $tipo, 'valor' => $valor, 'nota' => $t['nota'] ?? null, 'origen' => 'detectado']);
                $resumen['tokens']++;
            }
        }

        return $resumen;
    }

    /** Crea o actualiza una página y sus secciones; devuelve cuántas secciones nuevas creó. */
    private static function pagina(Proyecto $proyecto, array $p): int
    {
        $nombre = trim($p['nombre']);
        $figmaId = isset($p['figma_id']) ? trim((string) $p['figma_id']) : null;

        $pagina = ($figmaId ? $proyecto->paginas()->where('figma_id', $figmaId)->first() : null)
            ?? $proyecto->paginas()->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first();

        $secciones = $p['secciones'] ?? null;
        $total = is_array($secciones) ? count($secciones) : (is_numeric($secciones) ? (int) $secciones : null);
        $campos = array_filter([
            'figma_id'        => $figmaId ?: null,
            'url'             => isset($p['url']) ? trim((string) $p['url']) : null,
            'secciones_total' => $total,
            'origen'          => 'detectada',
        ], fn ($v) => $v !== null && $v !== '');

        if ($pagina) {
            $pagina->update($campos);
        } else {
            $pagina = $proyecto->paginas()->create($campos + ['nombre' => $nombre, 'estado' => 'pendiente']);
        }

        return is_array($secciones) ? self::secciones($pagina, $secciones) : 0;
    }

    private static function secciones(Pagina $pagina, array $secciones): int
    {
        $nuevas = 0;
        foreach ($secciones as $s) {
            $s = is_string($s) ? ['nombre' => $s] : $s;
            $nombre = trim((string) ($s['nombre'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            $id = isset($s['figma_id']) ? trim((string) $s['figma_id']) : null;
            $existente = ($id ? $pagina->secciones()->where('figma_id', $id)->first() : null)
                ?? $pagina->secciones()->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first();
            if ($existente) {
                if ($id && ! $existente->figma_id) {
                    $existente->update(['figma_id' => $id]);
                }
                continue;
            }
            $pagina->secciones()->create(['nombre' => $nombre, 'figma_id' => $id, 'estado' => 'planificada', 'ejecuto' => 'asistente']);
            $nuevas++;
        }
        if ($nuevas) {
            FlujoController::reabrir($pagina);
        }

        return $nuevas;
    }
}
