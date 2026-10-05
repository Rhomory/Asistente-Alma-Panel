<?php

namespace App\Console\Commands;

use App\Models\Conexion;
use App\Support\EntornoWP;
use Illuminate\Console\Command;

/** Comprueba los sitios registrados (versiones de WordPress, Elementor y JetEngine). */
class ConexionComprobar extends Command
{
    protected $signature = 'conexion:comprobar {proyecto? : Solo la conexión de este proyecto}';
    protected $description = 'Comprueba los sitios WordPress de las conexiones registradas';

    public function handle(): int
    {
        $conexiones = Conexion::with('proyecto')
            ->when($this->argument('proyecto'), fn ($q, $n) => $q->whereHas('proyecto', fn ($p) => $p->where('nombre', $n)))
            ->get();

        if ($conexiones->isEmpty()) {
            $this->warn('No hay conexiones registradas. Agrégalas en el panel, pantalla Conexión.');

            return self::SUCCESS;
        }

        $ultimas = EntornoWP::ultimasVersiones();
        $this->table(['Proyecto', 'MCP', 'Estado', 'WordPress', 'Elementor', 'JetEngine', 'Vía'], $conexiones->map(function ($c) {
            EntornoWP::comprobar($c);

            return [$c->proyecto->nombre, $c->nombre_mcp, $c->estado, $c->wp_version ?? '—', $c->elementor_version ?? '—', $c->jetengine_version ?? '—', $c->via ?? 'directo'];
        }));
        $this->line('Últimas publicadas: WordPress ' . ($ultimas['wordpress'] ?? '?') . ' · Elementor ' . ($ultimas['elementor'] ?? '?'));

        return self::SUCCESS;
    }
}
