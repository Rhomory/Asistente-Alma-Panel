<?php

namespace Tests\Feature;

use App\Models\Evento;
use App\Models\Pagina;
use App\Models\Proyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Flujo completo del panel: catálogo → plan → construcción (consola) →
 * aprobación/corrección → control de calidad.
 */
class FlujoCompletoTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_pantallas_principales_responden(): void
    {
        \Illuminate\Support\Facades\Cache::put('versiones.wordpress.org', ['wordpress' => null, 'elementor' => null], 60);
        $p = Proyecto::create(['nombre' => 'Demo']);
        $pagina = $p->paginas()->create(['nombre' => 'Inicio']);

        foreach (['/', '/conexion', '/registro', '/proyectos/nuevo', "/proyectos/{$p->id}", "/proyectos/{$p->id}/guia", "/paginas/{$pagina->id}"] as $ruta) {
            $this->get($ruta)->assertOk();
        }
    }

    public function test_se_crea_un_proyecto_desde_el_formulario(): void
    {
        $this->post('/proyectos', [
            'nombre' => 'Sitio de Prueba', 'cliente' => 'Cliente ficticio · Arequipa',
            'sitio_wp' => 'https://prueba-staging.ejemplo.pe',
        ])->assertRedirect();

        $this->assertDatabaseHas('proyectos', ['nombre' => 'Sitio de Prueba']);
    }

    public function test_no_permite_proyectos_con_nombre_repetido(): void
    {
        Proyecto::create(['nombre' => 'Repetido']);
        $this->post('/proyectos', ['nombre' => 'Repetido'])->assertSessionHasErrors('nombre');
    }

    public function test_plan_estandar_crea_las_siete_secciones_de_la_guia(): void
    {
        $pagina = $this->pagina();

        $this->post("/paginas/{$pagina->id}/plan-estandar")->assertRedirect();

        $this->assertSame(7, $pagina->secciones()->where('estado', 'planificada')->count());
    }

    public function test_registro_add_construye_la_seccion_planificada_sin_duplicarla(): void
    {
        $pagina = $this->pagina();
        $pagina->secciones()->create(['nombre' => 'Hero', 'widget_plan' => 'Contenedor + Heading', 'min_estimado' => 12, 'estado' => 'planificada']);

        $this->artisan('registro:add', [
            'proyecto' => $pagina->proyecto->nombre, 'pagina' => $pagina->nombre,
            'seccion' => 'Hero', 'minutos' => 14, '--asistente' => 11, '--dev' => 3,
        ])->assertSuccessful();

        $this->assertSame(1, $pagina->secciones()->count()); // se actualizó, no se duplicó
        $hero = $pagina->secciones()->first();
        $this->assertSame('construida', $hero->estado);
        $this->assertSame(14, $hero->minutos);
        $this->assertSame('Contenedor + Heading', $hero->widget_plan); // conserva el mapeo del plan
        $this->assertSame('construyendo', $pagina->fresh()->estado);   // pendiente → construyendo
    }

    public function test_aprobar_todas_las_secciones_aprueba_la_pagina_y_copiar_el_mensaje_solicita_qa(): void
    {
        $pagina = $this->pagina();
        $s = $pagina->secciones()->create(['nombre' => 'Hero', 'estado' => 'construida', 'minutos' => 15, 'min_asistente' => 12, 'min_dev' => 3]);

        $this->post("/secciones/{$s->id}/aprobar")->assertRedirect();
        $this->assertSame('aprobada', $s->fresh()->estado);
        $this->assertSame('aprobada', $pagina->fresh()->estado);

        $this->postJson("/paginas/{$pagina->id}/qa")->assertOk()->assertJson(['estado' => 'en_qc', 'completa' => true]);
        $this->assertSame('en_qc', $pagina->fresh()->estado);
        $this->assertSame(1, Evento::where('tipo', 'qa_solicitado')->count());
    }

    public function test_mensaje_qa_usa_sitio_figma_y_trello_y_no_cambia_estado_si_falta_aprobar(): void
    {
        $proyecto = Proyecto::create([
            'nombre' => 'Sitio QA', 'sitio_wp' => 'https://qa-staging.ejemplo.pe',
            'archivo_figma' => 'https://www.figma.com/design/abc123/Sitio-QA',
        ]);
        $pagina = $proyecto->paginas()->create(['nombre' => 'Inicio', 'estado' => 'construyendo']);
        $pagina->secciones()->create(['nombre' => 'Hero', 'estado' => 'construida', 'minutos' => 10, 'min_asistente' => 10]);

        $this->post("/paginas/{$pagina->id}/trello", ['trello_url' => 'https://example.com/no-es-trello'])->assertSessionHasErrors('trello_url');
        $this->post("/paginas/{$pagina->id}/trello", ['trello_url' => 'https://trello.com/c/AbC123/inicio'])->assertRedirect();

        $this->get("/paginas/{$pagina->id}")->assertOk()->assertSee(
            '@canal Solicito QA para https://qa-staging.ejemplo.pe aquí archivo Figma: https://www.figma.com/design/abc123/Sitio-QA y link de Trello: https://trello.com/c/AbC123/inicio',
            false
        );

        $this->postJson("/paginas/{$pagina->id}/qa")->assertOk()->assertJson(['completa' => false]);
        $this->assertSame('construyendo', $pagina->fresh()->estado);
    }

    public function test_pedir_correccion_encola_y_registro_add_la_resuelve(): void
    {
        $pagina = $this->pagina();
        $s = $pagina->secciones()->create(['nombre' => 'Hero', 'estado' => 'construida', 'minutos' => 15, 'min_asistente' => 15, 'correcciones' => 0]);

        $this->post("/secciones/{$s->id}/correccion", ['detalle' => 'El título debe usar el color primario'])->assertRedirect();

        $s->refresh();
        $this->assertSame('construyendo', $s->estado);
        $this->assertSame(1, $s->correcciones);
        $this->assertSame(1, Evento::where('tipo', 'solicitud_correccion')->where('resuelto', false)->count());

        $this->artisan('registro:add', [
            'proyecto' => $pagina->proyecto->nombre, 'pagina' => $pagina->nombre,
            'seccion' => 'Hero', 'minutos' => 6, '--asistente' => 6,
        ])->assertSuccessful();

        $this->assertSame(0, Evento::where('tipo', 'solicitud_correccion')->where('resuelto', false)->count());
        $this->assertSame('construida', $s->fresh()->estado);
    }

    public function test_los_tokens_de_diseno_se_agregan_y_eliminan(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Con Tokens']);

        $this->post("/proyectos/{$proyecto->id}/tokens", ['tipo' => 'color', 'valor' => '#7A3E2E', 'nota' => 'primario'])->assertRedirect();
        $token = $proyecto->tokens()->first();
        $this->assertNotNull($token);

        $this->delete("/tokens/{$token->id}")->assertRedirect();
        $this->assertSame(0, $proyecto->tokens()->count());
    }

    public function test_el_export_csv_descarga_el_registro(): void
    {
        $pagina = $this->pagina();
        $pagina->secciones()->create(['nombre' => 'Hero', 'estado' => 'aprobada', 'aprobada' => true, 'minutos' => 20, 'min_asistente' => 18, 'min_dev' => 2, 'inicio' => now(), 'fin' => now()]);

        $this->get('/registro/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_registro_paginas_detecta_sin_duplicar_y_el_check_las_excluye(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Sitio Detectado']);
        $proyecto->paginas()->create(['nombre' => 'Inicio', 'secciones_total' => 3]);

        $this->artisan('registro:paginas', [
            'proyecto' => 'Sitio Detectado',
            'paginas'  => ['Inicio|7', 'Nosotros|5', 'Contacto'],
        ])->assertSuccessful();

        $this->assertSame(3, $proyecto->paginas()->count());
        $inicio = $proyecto->paginas()->where('nombre', 'Inicio')->first();
        $this->assertSame(7, $inicio->secciones_total);
        $this->assertSame('detectada', $inicio->origen);

        $this->post("/paginas/{$inicio->id}/incluir", ['incluida' => 0])->assertRedirect();
        $this->assertFalse($inicio->fresh()->incluida);
    }

    public function test_registro_tokens_detecta_sin_repetir(): void
    {
        Proyecto::create(['nombre' => 'Con Diseño']);

        $this->artisan('registro:tokens', [
            'proyecto' => 'Con Diseño',
            'tokens'   => ['color|#7A3E2E|primario', 'tipografia|Poppins|títulos', 'color|#7a3e2e|repetido'],
        ])->assertSuccessful();

        $this->assertSame(2, Proyecto::where('nombre', 'Con Diseño')->first()->tokens()->count());
    }

    public function test_tokens_editables_solo_si_no_son_color(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'Editable']);
        $tipografia = $proyecto->tokens()->create(['tipo' => 'tipografia', 'valor' => 'Popins', 'nota' => 'títulos']);
        $color = $proyecto->tokens()->create(['tipo' => 'color', 'valor' => '#7A3E2E']);

        $this->put("/tokens/{$tipografia->id}", ['valor' => 'Poppins', 'nota' => 'títulos'])->assertRedirect();
        $this->assertSame('Poppins', $tipografia->fresh()->valor);

        $this->put("/tokens/{$color->id}", ['valor' => '#000000'])->assertStatus(422);
        $this->assertSame('#7A3E2E', $color->fresh()->valor);
    }

    private function pagina(): Pagina
    {
        $proyecto = Proyecto::create(['nombre' => 'Sitio Demo Test']);

        return $proyecto->paginas()->create(['nombre' => 'Inicio', 'estado' => 'pendiente']);
    }
}
