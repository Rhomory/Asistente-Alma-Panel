<?php

namespace Tests\Feature;

use App\Http\Controllers\FlujoController;
use App\Models\Evento;
use App\Models\Proyecto;
use App\Support\PromptGuia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Memoria del diseño (IDs de Figma por sección), URL por página y alcance por proyecto. */
class MemoriaFigmaTest extends TestCase
{
    use RefreshDatabase;

    private function datosFigma(): string
    {
        return json_encode([
            'figma'   => 'https://www.figma.com/design/abc/Cota',
            'paginas' => [
                ['nombre' => 'Inicio', 'figma_id' => '1:2', 'url' => '/', 'secciones' => [
                    ['nombre' => 'Hero', 'figma_id' => '1:3'],
                    ['nombre' => 'Servicios', 'figma_id' => '1:9'],
                ]],
                ['nombre' => 'Nosotros', 'secciones' => ['Historia', 'Equipo']],
                ['nombre' => 'Contacto', 'secciones' => 3],
            ],
        ]);
    }

    public function test_registro_figma_crea_secciones_planificadas_con_su_id_sin_duplicar(): void
    {
        $this->artisan('registro:figma', ['proyecto' => 'Cota', '--json' => $this->datosFigma()])->assertSuccessful();
        $this->artisan('registro:figma', ['proyecto' => 'Cota', '--json' => $this->datosFigma()])->assertSuccessful();

        $p = Proyecto::where('nombre', 'Cota')->sole();
        $inicio = $p->paginas()->where('nombre', 'Inicio')->sole();
        $this->assertSame('1:2', $inicio->figma_id);
        $this->assertSame(2, $inicio->secciones()->count());
        $this->assertSame('1:3', $inicio->secciones()->where('nombre', 'Hero')->value('figma_id'));
        $this->assertSame('planificada', $inicio->secciones()->first()->estado);
        $this->assertSame(2, $p->paginas()->where('nombre', 'Nosotros')->sole()->secciones()->count());

        $contacto = $p->paginas()->where('nombre', 'Contacto')->sole();
        $this->assertSame(3, $contacto->secciones_total);
        $this->get("/paginas/{$contacto->id}")->assertSee('Figma detectó 3 secciones');

        $prompt = PromptGuia::prompt($p, $inicio);
        $this->assertStringContainsString('1. Hero — — · Figma 1:3', $prompt);
        $this->assertStringContainsString('usa su ID guardado', $prompt);
    }

    public function test_url_de_cada_pagina_y_mensaje_de_qa_con_la_url_de_la_pagina(): void
    {
        $p = Proyecto::create(['nombre' => 'Cota', 'sitio_wp' => 'http://localhost:8883/', 'archivo_figma' => 'https://www.figma.com/design/x/Cota']);
        $inicio = $p->paginas()->create(['nombre' => 'Inicio']);
        $nosotros = $p->paginas()->create(['nombre' => 'Nosotros y Visión']);
        $blog = $p->paginas()->create(['nombre' => 'Blog', 'url' => '/noticias']);

        $this->assertSame('http://localhost:8883', $inicio->urlSitio());
        $this->assertSame('http://localhost:8883/nosotros-y-vision', $nosotros->urlSitio());
        $this->assertSame('http://localhost:8883/noticias', $blog->urlSitio());
        $this->assertStringStartsWith('@canal Solicito QA para http://localhost:8883/nosotros-y-vision ', FlujoController::mensajeQA($nosotros));

        $this->post("/paginas/{$nosotros->id}/url", ['url' => 'nosotros'])->assertSessionHasErrors('url');
        $this->post("/paginas/{$nosotros->id}/url", ['url' => '/quienes-somos'])->assertRedirect();
        $this->assertSame('http://localhost:8883/quienes-somos', $nosotros->fresh()->urlSitio());
    }

    public function test_la_cola_y_el_agents_md_se_limitan_al_proyecto(): void
    {
        foreach (['Cota', 'Picnicplus'] as $nombre) {
            $s = Proyecto::create(['nombre' => $nombre])->paginas()->create(['nombre' => 'Inicio'])
                ->secciones()->create(['nombre' => "Hero {$nombre}", 'estado' => 'construyendo']);
            Evento::create(['seccion_id' => $s->id, 'tipo' => 'solicitud_correccion', 'detalle' => "Corregir {$nombre}", 'resuelto' => false]);
        }

        $this->artisan('registro:cola', ['proyecto' => 'Cota'])
            ->expectsOutputToContain('Corregir Cota')
            ->doesntExpectOutputToContain('Corregir Picnicplus')
            ->assertSuccessful();

        $md = PromptGuia::agentsMd(Proyecto::where('nombre', 'Cota')->sole());
        $this->assertStringContainsString('Trabajas únicamente en **Cota**', $md);
        $this->assertStringContainsString('registro:cola "Cota"', $md);
    }
}
