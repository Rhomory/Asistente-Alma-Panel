<?php

namespace Tests\Feature;

use App\Models\Conexion;
use App\Models\Evento;
use App\Models\Pagina;
use App\Models\Proyecto;
use App\Models\Seccion;
use App\Models\Token;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Eliminar proyecto: en blanco basta confirmar; con registros hay que escribir su nombre. */
class EliminarProyectoTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_proyecto_en_blanco_se_elimina_sin_pedir_el_nombre(): void
    {
        $p = Proyecto::create(['nombre' => 'Vacío']);

        $this->get("/proyectos/{$p->id}")->assertOk()
            ->assertSee('Este proyecto está en blanco')
            ->assertDontSee('name="confirmacion"', false);

        $this->delete("/proyectos/{$p->id}")->assertRedirect('/')->assertSessionHas('ok');
        $this->assertModelMissing($p);
        $this->assertSame(1, Evento::where('tipo', 'proyecto_eliminado')->count());
    }

    public function test_con_registros_pide_el_nombre_exacto_y_borra_todo_lo_relacionado(): void
    {
        $p = Proyecto::create(['nombre' => 'ECOCREATIONS']);
        $pagina = $p->paginas()->create(['nombre' => 'Inicio']);
        $s = $pagina->secciones()->create(['nombre' => 'Hero', 'estado' => 'construida', 'minutos' => 10]);
        Evento::create(['seccion_id' => $s->id, 'tipo' => 'construccion', 'detalle' => 'x']);
        $p->tokens()->create(['tipo' => 'color', 'valor' => '#2F6B4F']);
        $p->conexiones()->create(['nombre_mcp' => 'elementor-ecocreations', 'sitio_url' => 'https://eco.ejemplo.pe']);

        $this->get("/proyectos/{$p->id}")->assertOk()
            ->assertSee('Este proyecto tiene registros')
            ->assertSee('name="confirmacion"', false);

        $this->delete("/proyectos/{$p->id}")->assertSessionHasErrors('confirmacion');
        $this->delete("/proyectos/{$p->id}", ['confirmacion' => 'ecocreations'])->assertSessionHasErrors('confirmacion');
        $this->assertModelExists($p);

        $this->delete("/proyectos/{$p->id}", ['confirmacion' => 'ECOCREATIONS'])->assertRedirect('/')
            ->assertSessionHas('ok', fn ($m) => str_contains($m, 'claude mcp remove elementor-ecocreations'));

        $this->assertModelMissing($p);
        $this->assertSame(0, Pagina::count());
        $this->assertSame(0, Seccion::count());
        $this->assertSame(0, Token::count());
        $this->assertSame(0, Conexion::count());
        $this->assertSame(0, Evento::where('tipo', 'construccion')->count());
    }
}
