<?php

namespace App\Support;

/**
 * Normaliza la lectura del Figma que escribe el agente antes de guardarla. Los modelos pequeños suelen
 * escribir claves en inglés (pages, name, id), "tipografía" con tilde o colores en rgb(): aquí se aceptan
 * y se convierten al formato del panel, y todo lo que se descarta queda en avisos (nada se pierde en silencio).
 */
class LecturaFigma
{
    private const TIPOS = [
        'color' => 'color', 'colour' => 'color', 'colores' => 'color', 'colors' => 'color',
        'tipografia' => 'tipografia', 'tipografias' => 'tipografia', 'typography' => 'tipografia', 'font' => 'tipografia',
        'fonts' => 'tipografia', 'fuente' => 'tipografia', 'fuentes' => 'tipografia', 'texto' => 'tipografia', 'text' => 'tipografia',
        'espaciado' => 'espaciado', 'espaciados' => 'espaciado', 'spacing' => 'espaciado', 'space' => 'espaciado', 'gap' => 'espaciado',
        'otro' => 'otro', 'otros' => 'otro', 'other' => 'otro', 'radius' => 'otro', 'radio' => 'otro', 'sombra' => 'otro', 'shadow' => 'otro',
    ];

    /** @return array{datos: array, avisos: string[]} */
    public static function normalizar(array $crudo): array
    {
        $avisos = [];
        $datos = [];

        $figma = self::primero($crudo, ['figma', 'figma_url', 'archivo_figma', 'file', 'url']);
        if (is_string($figma) && $figma !== '') {
            $datos['figma'] = trim($figma);
        }

        $datos['paginas'] = [];
        foreach ((array) (self::primero($crudo, ['paginas', 'páginas', 'pages']) ?? []) as $i => $p) {
            $nombre = is_array($p) ? self::texto(self::primero($p, ['nombre', 'name', 'titulo', 'title'])) : '';
            if ($nombre === '') {
                $avisos[] = 'Página ' . ($i + 1) . ' omitida: no tiene "nombre".';
                continue;
            }
            $pagina = ['nombre' => $nombre];
            if ($id = self::texto(self::primero($p, ['figma_id', 'id', 'nodeId', 'node_id']))) {
                $pagina['figma_id'] = $id;
            }
            if ($url = self::texto(self::primero($p, ['url', 'ruta', 'path', 'slug']))) {
                $pagina['url'] = $url;
            }
            $secciones = self::primero($p, ['secciones', 'sections', 'children']);
            if (is_array($secciones)) {
                $pagina['secciones'] = [];
                foreach ($secciones as $s) {
                    $sn = is_string($s) ? trim($s) : (is_array($s) ? self::texto(self::primero($s, ['nombre', 'name', 'titulo', 'title'])) : '');
                    if ($sn === '') {
                        $avisos[] = "Sección sin nombre omitida en {$nombre}.";
                        continue;
                    }
                    $sid = is_array($s) ? self::texto(self::primero($s, ['figma_id', 'id', 'nodeId', 'node_id'])) : '';
                    $pagina['secciones'][] = $sid !== '' ? ['nombre' => $sn, 'figma_id' => $sid] : ['nombre' => $sn];
                }
            }
            $datos['paginas'][] = $pagina;
        }

        $datos['tokens'] = [];
        foreach ((array) (self::primero($crudo, ['tokens', 'variables']) ?? []) as $t) {
            if (! is_array($t)) {
                continue;
            }
            $tipoOriginal = self::texto(self::primero($t, ['tipo', 'type', 'kind']));
            $tipo = self::TIPOS[self::sinTildes(mb_strtolower($tipoOriginal))] ?? null;
            $valor = self::texto(self::primero($t, ['valor', 'value']));
            if (! $tipo || $valor === '') {
                $avisos[] = "Token omitido (tipo \"{$tipoOriginal}\" o valor vacío). Tipos válidos: color, tipografia, espaciado, otro.";
                continue;
            }
            if ($tipo === 'color') {
                $hex = self::hex($valor);
                if (! $hex) {
                    $avisos[] = "Color omitido: \"{$valor}\" no es hexadecimal ni rgb().";
                    continue;
                }
                $valor = $hex;
            }
            $token = ['tipo' => $tipo, 'valor' => $valor];
            if ($nota = self::texto(self::primero($t, ['nota', 'note', 'rol', 'role', 'nombre', 'name']))) {
                $token['nota'] = $nota;
            }
            $datos['tokens'][] = $token;
        }

        return ['datos' => $datos, 'avisos' => $avisos];
    }

    /** #RGB, #RRGGBB, #RRGGBBAA o rgb()/rgba() (0-255 o 0-1) → #RRGGBB en mayúsculas. */
    public static function hex(string $valor): ?string
    {
        $v = trim($valor);
        if (preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $v, $m)) {
            $h = $m[1];
            if (strlen($h) === 3) {
                $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
            }

            return '#' . strtoupper($h);
        }
        if (preg_match('/^rgba?\(\s*([\d.]+)\s*[, ]\s*([\d.]+)\s*[, ]\s*([\d.]+)/i', $v, $m)) {
            $c = array_map('floatval', array_slice($m, 1, 3));
            $escala = max($c) <= 1 ? 255 : 1;   // Figma usa 0-1

            return '#' . strtoupper(implode('', array_map(fn ($x) => str_pad(dechex((int) round(min(255, $x * $escala))), 2, '0', STR_PAD_LEFT), $c)));
        }

        return null;
    }

    private static function primero(mixed $arr, array $claves): mixed
    {
        if (! is_array($arr)) {
            return null;
        }
        foreach ($claves as $k) {
            if (array_key_exists($k, $arr) && $arr[$k] !== null && $arr[$k] !== '') {
                return $arr[$k];
            }
        }

        return null;
    }

    private static function texto(mixed $v): string
    {
        return is_scalar($v) ? trim((string) $v) : '';
    }

    private static function sinTildes(string $s): string
    {
        return strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
    }
}
