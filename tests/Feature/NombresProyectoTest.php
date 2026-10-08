<?php

namespace Tests\Feature;

use App\Models\Proyecto;
use App\Support\NombresProyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NombresProyectoTest extends TestCase
{
    use RefreshDatabase;

    public function test_detecta_variantes_y_mismo_nombre(): void
    {
        $this->assertSame('cota', NombresProyecto::base('Cota v2'));
        $this->assertSame('cota', NombresProyecto::base('cotav1'));
        $this->assertSame('cota', NombresProyecto::base('Cota - copia final'));
        $this->assertSame('cota', NombresProyecto::base('Cota 2'));
        $this->assertSame('clinicadelsur', NombresProyecto::base('Clínica del Sur (nuevo)'));
        $this->assertSame(NombresProyecto::clave('Cotá'), NombresProyecto::clave(' COTA '));
        $this->assertNotSame(NombresProyecto::base('Cota Norte'), NombresProyecto::base('Cota'));
    }

    public function test_el_panel_no_crea_nombres_repetidos_ni_variantes_sin_confirmar(): void
    {
        Proyecto::create(['nombre' => 'Cota']);

        $this->post(route('proyecto.guardar'), ['nombre' => 'COTÁ'])->assertSessionHasErrors('nombre');
        $this->post(route('proyecto.guardar'), ['nombre' => 'cotav1'])->assertSessionHasErrors('variante');
        $this->assertSame(1, Proyecto::count());

        $this->get(route('proyecto.nuevo'))->assertOk();

        $this->post(route('proyecto.guardar'), ['nombre' => 'cotav1', 'forzar_variante' => '1'])->assertRedirect();
        $this->post(route('proyecto.guardar'), ['nombre' => 'Cota Norte'])->assertRedirect();
        $this->assertSame(3, Proyecto::count());
    }

    public function test_la_consola_reusa_el_proyecto_y_frena_variantes(): void
    {
        Proyecto::create(['nombre' => 'Cota']);

        // Mismo nombre con otra escritura: usa el existente.
        $this->artisan('registro:paginas', ['proyecto' => 'cota', 'paginas' => ['Inicio|3']])->assertSuccessful();
        $this->assertSame(1, Proyecto::count());
        $this->assertSame(1, Proyecto::first()->paginas()->count());

        // Variante: no crea nada y explica por qué.
        $this->artisan('registro:paginas', ['proyecto' => 'Cota v2', 'paginas' => ['Inicio']])
            ->expectsOutputToContain('parece una variante de: Cota')
            ->assertFailed();
        $this->assertSame(1, Proyecto::count());

        $this->artisan('registro:paginas', ['proyecto' => 'Cota v2', 'paginas' => ['Inicio'], '--forzar-variante' => true])->assertSuccessful();
        $this->assertSame(2, Proyecto::count());

        // Los comandos de lectura también encuentran el proyecto sin importar mayúsculas.
        $this->artisan('guia:prompt', ['proyecto' => 'COTA', 'pagina' => 'Inicio'])->assertSuccessful();
    }
}
