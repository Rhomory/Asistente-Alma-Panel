<?php

namespace App\Support;

use App\Models\Conexion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Comprueba sitios WordPress sin credenciales:
 *  - /wp-json/ responde → API REST activa (y namespaces de Elementor / JetEngine).
 *  - Portada: <meta name="generator"> de WordPress y Elementor, y ?ver= de los assets de JetEngine.
 * Todas las peticiones salen en paralelo (curl_multi). Si un sitio localhost no responde desde WSL,
 * se reintenta contra el host de Windows conservando el nombre de host original.
 * Las últimas versiones publicadas salen de la API pública de WordPress.org.
 */
class EntornoWP
{
    public const ENLACE_WP = 'https://wordpress.org/download/releases/';
    public const ENLACE_ELEMENTOR = 'https://wordpress.org/plugins/elementor/#developers';

    private const TIMEOUT = 4;          // s por petición; en paralelo, el total ronda este valor
    private const CACHE_OK = 6 * 3600;  // versiones publicadas: 6 h
    private const CACHE_FALLO = 600;    // si WordPress.org no respondió: reintentar a los 10 min

    public static function comprobar(Conexion $c): Conexion
    {
        return self::comprobarVarias(collect([$c]))->first();
    }

    /** Comprueba varias conexiones a la vez. */
    public static function comprobarVarias(Collection $conexiones): Collection
    {
        // 1.ª ronda: todos los sitios en paralelo, directo.
        $peticiones = [];
        foreach ($conexiones as $c) {
            $base = rtrim($c->sitio_url, '/');
            $peticiones["{$c->id}.raiz"] = [$base . '/wp-json/', []];
            $peticiones["{$c->id}.portada"] = [$base . '/', []];
        }
        $resp = self::multi($peticiones);

        // 2.ª ronda: solo los localhost que no respondieron, por el host de Windows.
        $via = [];
        $reintento = [];
        $hostWin = null;
        foreach ($conexiones as $c) {
            $p = parse_url($c->sitio_url);
            $local = in_array($p['host'] ?? '', ['localhost', '127.0.0.1'], true);
            if ($local && $resp["{$c->id}.raiz"] === null && $resp["{$c->id}.portada"] === null) {
                $hostWin ??= self::hostWindows();
                if (! $hostWin) {
                    continue;
                }
                $puerto = $p['port'] ?? (($p['scheme'] ?? 'http') === 'https' ? 443 : 80);
                $resolver = ["{$p['host']}:{$puerto}:{$hostWin}"];
                $reintento["{$c->id}.raiz"] = [$peticiones["{$c->id}.raiz"][0], $resolver];
                $reintento["{$c->id}.portada"] = [$peticiones["{$c->id}.portada"][0], $resolver];
                $via[$c->id] = $hostWin;
            }
        }
        if ($reintento) {
            $resp = array_merge($resp, self::multi($reintento));
        }

        foreach ($conexiones as $c) {
            $raiz = $resp["{$c->id}.raiz"];
            $html = (string) $resp["{$c->id}.portada"];
            $json = $raiz ? json_decode($raiz, true) : null;
            $namespaces = is_array($json['namespaces'] ?? null) ? $json['namespaces'] : [];
            $respondio = $raiz !== null || $resp["{$c->id}.portada"] !== null;

            $c->fill([
                'estado'            => $respondio ? 'conectado' : 'sin_conexion',
                'rest_activo'       => is_array($json) && isset($json['namespaces']),
                'wp_version'        => self::generador($html, 'WordPress'),
                'elementor_version' => self::generador($html, 'Elementor') ?? self::verAsset($html, 'elementor'),
                'jetengine_version' => self::verAsset($html, 'jet-engine')
                    ?? (self::tieneNamespace($namespaces, 'jet-engine') ? 'activo' : null),
                'via'               => ($respondio && isset($via[$c->id])) ? "host de Windows ({$via[$c->id]})" : null,
                'comprobada_en'     => now(),
            ])->save();
        }

        return $conexiones;
    }

    /** @return array{wordpress: ?string, elementor: ?string} */
    public static function ultimasVersiones(): array
    {
        if ($guardadas = Cache::get('versiones.wordpress.org')) {
            return $guardadas;
        }

        $r = self::multi([
            'wp' => ['https://api.wordpress.org/core/version-check/1.7/', []],
            'el' => ['https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=elementor&request[fields][sections]=0', []],
        ], 3);
        $wp = $r['wp'] ? json_decode($r['wp'], true) : null;
        $el = $r['el'] ? json_decode($r['el'], true) : null;

        $versiones = [
            'wordpress' => $wp['offers'][0]['version'] ?? null,
            'elementor' => $el['version'] ?? null,
        ];
        $completas = $versiones['wordpress'] && $versiones['elementor'];
        Cache::put('versiones.wordpress.org', $versiones, $completas ? self::CACHE_OK : self::CACHE_FALLO);

        return $versiones;
    }

    /** true si la versión instalada es menor que la última publicada. */
    public static function desactualizada(?string $instalada, ?string $ultima): bool
    {
        return $instalada && $ultima && preg_match('/^\d/', $instalada) && version_compare($instalada, $ultima, '<');
    }

    /**
     * Ejecuta varias GET en paralelo. $peticiones = [clave => [url, resolver[]]].
     * Devuelve [clave => cuerpo|null] (null si falló o respondió fuera de 2xx/3xx).
     */
    private static function multi(array $peticiones, int $timeout = self::TIMEOUT): array
    {
        $mh = curl_multi_init();
        $handles = [];
        foreach ($peticiones as $clave => [$url, $resolver]) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_USERAGENT => 'AsistenteAlma-Panel/1.0',
                CURLOPT_RESOLVE => $resolver,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$clave] = $ch;
        }

        do {
            $estado = curl_multi_exec($mh, $activas);
            if ($activas) {
                curl_multi_select($mh, 0.5);
            }
        } while ($activas && $estado === CURLM_OK);

        $salida = [];
        foreach ($handles as $clave => $ch) {
            $cuerpo = curl_multi_getcontent($ch);
            $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $salida[$clave] = ($cuerpo !== null && $cuerpo !== '' && $codigo >= 200 && $codigo < 400) ? $cuerpo : null;
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);

        return $salida;
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
