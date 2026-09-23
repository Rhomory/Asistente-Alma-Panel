<?php

namespace Database\Seeders;

use App\Models\Evento;
use App\Models\Pagina;
use App\Models\Proyecto;
use App\Models\Seccion;
use Illuminate\Database\Seeder;

/**
 * Datos demo del piloto simulado. Las cifras de la página "Inicio" de
 * Bodega Andina reproducen las del Proyecto de Mejora: 260 min totales
 * (-46 % vs línea base de 480), reparto 62/38 asistente/desarrollador
 * y 2 correcciones. Clientes ficticios (Arequipa, Perú).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- Proyecto 1: piloto terminado a medias ----------
        $bodega = Proyecto::create([
            'nombre'        => 'Sitio Bodega Andina',
            'cliente'       => 'Bodega Andina S.A.C. (ficticio) · Arequipa',
            'archivo_figma' => 'Sitio Bodega Andina — 5 páginas',
            'sitio_wp'      => 'https://bodega-staging.ejemplo.pe',
        ]);

        // Página Inicio: la del mockup, cifras canónicas del documento.
        $inicio = $bodega->paginas()->create([
            'nombre' => 'Inicio', 'secciones_total' => 7, 'estado' => 'aprobada',
        ]);
        $this->secciones($inicio, '2026-09-21 09:10', [
            // nombre, widget, min, asistente, dev, correcciones
            ['Hero con video',               'Contenedor + Heading + Button',      41, 30, 11, 1],
            ['Servicios (3 tarjetas)',       'Grid + Icon Box ×3',                 28, 26,  2, 0],
            ['Nosotros',                     'Contenedor + Image + Text Editor',   33, 18, 15, 1],
            ['Testimonios',                  'Carousel',                           36, 30,  6, 0],
            ['Portafolio destacado',         'Gallery + Heading',                  39, 32,  7, 0],
            ['Llamado a la acción',          'Contenedor + Button',                22, 15,  7, 0],
            ['Pie de página',                'Template de sitio (footer)',         21, 10, 11, 0],
            ['Verificación final y correcciones', null,                            40,  0, 40, 0],
        ], aprobadas: true);

        // Página Nosotros: aprobada, segunda medición del piloto.
        $nosotros = $bodega->paginas()->create([
            'nombre' => 'Nosotros', 'secciones_total' => 5, 'estado' => 'aprobada',
        ]);
        $this->secciones($nosotros, '2026-09-21 15:05', [
            ['Hero interno',        'Contenedor + Heading',             38, 28, 10, 0],
            ['Historia',            'Contenedor + Image + Text Editor', 42, 30, 12, 1],
            ['Equipo',              'Grid + Image Box ×4',              35, 25, 10, 0],
            ['Valores',             'Icon List',                        24, 16,  8, 0],
            ['Pie de página',       'Template de sitio (footer)',       18, 10,  8, 0],
            ['Verificación final y correcciones', null,                 30,  0, 30, 0],
        ], aprobadas: true);

        // Página Servicios: en construcción (para que el dashboard muestre estados mixtos).
        $servicios = $bodega->paginas()->create([
            'nombre' => 'Servicios', 'secciones_total' => 6, 'estado' => 'construyendo',
        ]);
        $this->secciones($servicios, '2026-09-22 09:30', [
            ['Hero de servicios',   'Contenedor + Heading',             40, 30, 10, 1],
            ['Lista de servicios',  'Grid + Icon Box ×6',               33, 26,  7, 0],
            ['Proceso de trabajo',  'Timeline / Icon List',             29, 22,  7, 0],
        ], aprobadas: true);

        $bodega->paginas()->create(['nombre' => 'Portafolio', 'secciones_total' => 4]);
        $bodega->paginas()->create(['nombre' => 'Contacto',   'secciones_total' => 3]);

        // ---------- Proyecto 2: recién iniciado ----------
        $cafe = Proyecto::create([
            'nombre'        => 'Portal Andes Café',
            'cliente'       => 'Andes Café E.I.R.L. (ficticio) · Arequipa',
            'archivo_figma' => 'Portal Andes Café — 4 páginas',
            'sitio_wp'      => 'https://andescafe-staging.ejemplo.pe',
        ]);
        $inicioCafe = $cafe->paginas()->create([
            'nombre' => 'Inicio', 'secciones_total' => 6, 'estado' => 'construyendo',
        ]);
        $this->secciones($inicioCafe, '2026-09-22 11:20', [
            ['Hero con carrusel',  'Carousel + Heading',   36, 28, 8, 0],
            ['Nuestra carta',      'Grid + Image Box',     31, 24, 7, 1],
        ], aprobadas: true);
        $cafe->paginas()->create(['nombre' => 'Carta', 'secciones_total' => 5]);
    }

    /** Crea secciones consecutivas con eventos, partiendo de una hora inicial. */
    private function secciones(Pagina $pagina, string $desde, array $filas, bool $aprobadas = false): void
    {
        $t = \Carbon\Carbon::parse($desde);
        foreach ($filas as [$nombre, $widget, $min, $asis, $dev, $corr]) {
            $inicio = $t->copy();
            $fin = $t->copy()->addMinutes($min);
            $t = $fin->copy()->addMinutes(4); // pausa entre secciones

            $ejecuto = $asis === 0 ? 'desarrollador' : ($dev <= 2 ? 'asistente' : ($asis >= $dev ? 'asistente' : 'mixto'));
            if ($asis > 0 && $dev > 10) {
                $ejecuto = 'mixto';
            }

            $s = $pagina->secciones()->create([
                'nombre' => $nombre, 'widget_plan' => $widget, 'ejecuto' => $ejecuto,
                'inicio' => $inicio, 'fin' => $fin, 'minutos' => $min,
                'min_asistente' => $asis, 'min_dev' => $dev,
                'correcciones' => $corr, 'aprobada' => $aprobadas,
            ]);

            Evento::create(['seccion_id' => $s->id, 'tipo' => 'construccion', 'detalle' => "Sección construida en {$min} min", 'created_at' => $fin]);
            if ($corr > 0) {
                Evento::create(['seccion_id' => $s->id, 'tipo' => 'correccion', 'detalle' => 'Ajuste solicitado por el desarrollador en la revisión por secciones', 'created_at' => $fin->copy()->addMinutes(2)]);
            }
            if ($aprobadas) {
                Evento::create(['seccion_id' => $s->id, 'tipo' => 'aprobacion', 'detalle' => 'Aprobada por el desarrollador', 'created_at' => $fin->copy()->addMinutes(3)]);
            }
        }
    }
}
