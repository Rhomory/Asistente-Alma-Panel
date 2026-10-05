<?php

namespace App\Support;

use App\Models\Conexion;
use Illuminate\Support\Facades\Cache;

/**
 * Comprueba un sitio WordPress sin credenciales:
 *  - /wp-json/ responde → API REST activa (y namespaces de Elementor / JetEngine).
 *  - Portada: <meta name="generator"> de WordPress y Elementor, y ?ver= de los assets de JetEngine.
 * Si el sitio es localhost y no responde desde WSL, reintenta contra el host de Windows
 * (puerta de enlace de WSL) conservando el nombre de host original.
 * Las últimas versiones publicadas se consultan en la API pública de WordPress.org.
 */
class EntornoWP
{
    public const ENLACE_WP = 'https://wordpress.org/download/releases/';
    public const ENLACE_ELEMENTOR = 'https://wordpress.org/plugins/elementor/#developers';

    public static function comprobar(Conexion $c): Conexion
    {
        [$raiz, $via] = self::pedir(rtrim($c->sitio_url, '/') . '/wp-json/');
        [$portada] = self::pedir(rtrim($c->sitio_url, '/') . '/', $via);

        $json = $raiz ? json_decode($raiz, true) : null;
        $namespaces = is_array($json['namespaces'] ?? null) ? $json['namespaces'] : [];
        $html = (string) $portada;

        $c->fill([
            'estado'            => ($raiz || $portada) ? 'conectado' : 'sin_conexion',
            'rest_activo'       => is_array($json) && isset($json['namespaces']),
            'wp_version'        => self::generador($html, 'WordPress'),
            'elementor_version' => self::generador($html, 'Elementor') ?? self::verAsset($html, 'elementor'),
            'jetengine_version' => self::verAsset($html, 'jet-engine')
                ?? (self::tieneNamespace($namespaces, 'jet-engine') ? 'activo' : null),
            'via'               => $via ? "host de Windows ({$via})" : null,
            'comprobada_en'     => now(),
        ])->save();

        return $c;
    }

    /** @return array{wordpress: ?string, elementor: ?string} */
    public static function ultimasVersiones(): array
    {
        return Cache::remember('versiones.wordpress.org', now()->addHours(6), function () {
            $wp = self::json('https://api.wordpress.org/core/version-check/1.7/');
            $el = self::json('https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=elementor&request[fields][sections]=0');

            return [
                'wordpress' => $wp['offers'][0]['version'] ?? null,
                'elementor' => $el['version'] ?? null,
            ];
        });
    }

    /** true si la versión instalada es menor que la última publicada. */
    public static function desactualizada(?string $instalada, ?string $ultima): bool
    {
        return $instalada && $ultima && preg_match('/^\d/', $instalada) && version_compare($instalada, $ultima, '<');
    }

    /**
     * GET con curl (timeout corto). Devuelve [cuerpo|null, ip del host de Windows si se usó].
     * $via fuerza el mismo desvío que funcionó en la petición anterior.
     */
    private static function pedir(string $url, ?string $via = null): array
    {
        $partes = parse_url($url);
        $host = $partes['host'] ?? '';
        $esLocal = in_array($host, ['localhost', '127.0.0.1'], true);

        if (! $via) {
            $cuerpo = self::curl($url);
            if ($cuerpo !== null || ! $esLocal) {
                return [$cuerpo, null];
            }
            $via = self::hostWindows();
            if (! $via) {
                return [null, null];
            }
        }

        $puerto = $partes['port'] ?? (($partes['scheme'] ?? 'http') === 'https' ? 443 : 80);
        $cuerpo = self::curl($url, ["{$host}:{$puerto}:{$via}"]);

        return [$cuerpo, $cuerpo !== null ? $via : null];
    }

    private static function curl(string $url, array $resolver = []): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 4, CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_USERAGENT => 'AsistenteAlma-Panel/1.0',
            CURLOPT_RESOLVE => $resolver,
        ]);
        $cuerpo = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return ($cuerpo !== false && $codigo >= 200 && $codigo < 400) ? $cuerpo : null;
    }

    private static function json(string $url): ?array
    {
        $cuerpo = self::curl($url);

        return $cuerpo ? json_decode($cuerpo, true) : null;
    }

    /** IP del host de Windows vista desde WSL: variable ALMA_HOST_WINDOWS o la puerta de enlace por defecto. */
    public static function hostWindows(): ?string
    {
        if ($ip = env('ALMA_HOST_WINDOWS')) {
            return $ip;
        }
        $rutas = @file('/proc/net/route') ?: [];
        foreach (array_slice($rutas, 1) as $linea) {
            $c = preg_split('/\s+/', trim($linea));
            if (($c[1] ?? '') === '00000000' && isset($c[2])) {
                return long2ip(unpack('V', pack('H*', $c[2]))[1]); // hex little-endian → IPv4
            }
        }

        return null;
    }

    private static function generador(string $html, string $producto): ?string
    {
        return preg_match('/<meta[^>]+name=["\']generator["\'][^>]+content=["\']' . $producto . '\s+([\d.]+)/i', $html, $m) ? $m[1] : null;
    }

    private static function verAsset(string $html, string $plugin): ?string
    {
        return preg_match('#/plugins/' . preg_quote($plugin, '#') . '/[^"\'\s]+\?ver=([\d.]+)#i', $html, $m) ? $m[1] : null;
    }

    private static function tieneNamespace(array $namespaces, string $prefijo): bool
    {
        foreach ($namespaces as $n) {
            if (str_starts_with($n, $prefijo)) {
                return true;
            }
        }

        return false;
    }
}
