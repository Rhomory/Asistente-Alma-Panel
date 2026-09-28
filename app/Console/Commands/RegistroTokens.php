<?php

namespace App\Console\Commands;

use App\Models\Evento;
use App\Models\Proyecto;
use Illuminate\Console\Command;

/**
 * El asistente registra los tokens que detectó en el diseño:
 *
 *   php artisan registro:tokens "Sitio Bodega Andina" "color|#7A3E2E|primario" "tipografia|Poppins|títulos"
 *
 * Formato: "tipo|valor|nota" (tipos: color, tipografia, espaciado, otro).
 * Los repetidos (mismo tipo y valor) se omiten.
 */
class RegistroTokens extends Command
{
    protected $signature = 'registro:tokens {proyecto} {tokens*}';
    protected $description = 'Registra los tokens de diseño detectados en el archivo (los escribe el asistente)';

    public function handle(): int
    {
        $proyecto = Proyecto::firstOrCreate(['nombre' => $this->argument('proyecto')]);
        $nuevos = $omitidos = 0;

        foreach ($this->argument('tokens') as $entrada) {
            [$tipo, $valor, $nota] = array_pad(explode('|', $entrada, 3), 3, null);
            $tipo = strtolower(trim((string) $tipo));
            $valor = trim((string) $valor);
            if ($valor === '' || ! in_array($tipo, ['color', 'tipografia', 'espaciado', 'otro'], true)) {
                $this->warn("Omitido (formato tipo|valor|nota): {$entrada}");
                continue;
            }

            $existe = $proyecto->tokens()->where('tipo', $tipo)
                ->whereRaw('LOWER(valor) = ?', [mb_strtolower($valor)])->exists();
            if ($existe) {
                $omitidos++;
                continue;
            }

            $token = $proyecto->tokens()->create(['tipo' => $tipo, 'valor' => $valor, 'nota' => $nota, 'origen' => 'detectado']);
            Evento::create(['tipo' => 'token', 'detalle' => "Token {$tipo} \"{$valor}\" detectado en el diseño de {$proyecto->nombre}"]);
            $nuevos++;
        }

        $this->info("Tokens de \"{$proyecto->nombre}\": {$nuevos} nuevos, {$omitidos} ya registrados.");

        return self::SUCCESS;
    }
}
