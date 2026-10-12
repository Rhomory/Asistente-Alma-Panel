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

    public function test_eliminar_con_respaldo_borra_carpeta_y_credencial_y_se_puede_restaurar(): void
    {
        $tmp = sys_get_temp_dir() . '/alma-borrar-' . uniqid();
        mkdir($tmp);
        config(['alma.proyectos_dir' => $tmp]);
        \App\Support\Windows::$llamadas = [];

        $p = Proyecto::create(['nombre' => 'Clínica Sur', 'cliente' => 'Clínica Sur S.A.C. (ficticio)']);
        $pagina = $p->paginas()->create(['nombre' => 'Inicio', 'incluida' => true]);
        $s = $pagina->secciones()->create(['nombre' => 'Hero', 'estado' => 'aprobada', 'minutos' => 12, 'min_asistente' => 9, 'min_dev' => 3]);
        Evento::create(['seccion_id' => $s->id, 'tipo' => 'construccion', 'detalle' => 'Hero construida']);
        $p->tokens()->create(['tipo' => 'color', 'valor' => '#2F6B4F', 'nota' => 'primario']);
        $p->conexiones()->create(['nombre_mcp' => 'elementor-clinica-sur', 'sitio_url' => 'https://clinica.ejemplo.pe']);
        $p->disenos()->create(['version' => 1, 'estado' => 'activa', 'datos' => '{"paginas":[]}', 'resumen' => ['paginas' => 1]]);
        \App\Support\CarpetaProyecto::preparar($p->fresh());
        $carpeta = \App\Support\CarpetaProyecto::ruta($p->fresh());
        $this->assertDirectoryExists($carpeta);

        $this->delete("/proyectos/{$p->slug}", ['confirmacion' => 'Clínica Sur', 'respaldo' => '1', 'borrar_carpeta' => '1', 'borrar_credencial' => '1'])
            ->assertRedirect('/')
            ->assertSessionHas('ok', fn ($m) => str_contains($m, 'Respaldo de') && str_contains($m, 'Su carpeta se borró'));

        $this->assertDirectoryDoesNotExist($carpeta);
        $this->assertDirectoryExists($tmp);                      // la carpeta base nunca se toca
        $this->assertContains(['accion' => 'borrar-credencial', 'args' => ['ALMA_CLINICA_SUR_AUTH'], 'env' => []], \App\Support\Windows::$llamadas);
        $respaldos = glob("{$tmp}/_archivo/clinica-sur-*.json.gz");
        $this->assertCount(1, $respaldos);
        $this->assertLessThan(20 * 1024, filesize($respaldos[0]));  // pesa casi nada
        $this->assertStringNotContainsString('Basic ', gzdecode(file_get_contents($respaldos[0])));

        // Restaurar: vuelve todo, con sus tiempos.
        $this->artisan('proyecto:restaurar')->expectsOutputToContain('clinica-sur-')->assertSuccessful();
        $this->artisan('proyecto:restaurar', ['archivo' => basename($respaldos[0], '.json.gz')])->assertSuccessful();
        $r = Proyecto::where('nombre', 'Clínica Sur')->firstOrFail();
        $this->assertSame('clinica-sur', $r->slug);
        $this->assertSame(1, $r->paginas()->count());
        $this->assertSame(12, (int) Seccion::first()->minutos);
        $this->assertSame(1, Evento::where('tipo', 'construccion')->count());
        $this->assertSame('#2F6B4F', $r->tokens()->first()->valor);
        $this->assertSame(['paginas' => 1], $r->disenos()->first()->resumen);
        $this->assertSame('elementor-clinica-sur', $r->conexion->nombre_mcp);
        $this->assertDirectoryExists(\App\Support\CarpetaProyecto::ruta($r));

        // Restaurar de nuevo choca con el que ya existe.
        $this->artisan('proyecto:restaurar', ['archivo' => basename($respaldos[0])])->expectsOutputToContain('Ya existe')->assertFailed();

        \Illuminate\Support\Facades\File::deleteDirectory($tmp);
    }

    public function test_el_respaldo_se_restaura_desde_el_panel(): void
    {
        $tmp = sys_get_temp_dir() . '/alma-panel-' . uniqid();
        mkdir($tmp);
        config(['alma.proyectos_dir' => $tmp]);
        $p = Proyecto::create(['nombre' => 'Cota']);
        $p->paginas()->create(['nombre' => 'Inicio'])->secciones()->create(['nombre' => 'Hero', 'estado' => 'aprobada']);

        $this->delete("/proyectos/{$p->slug}", ['confirmacion' => 'Cota', 'respaldo' => '1'])->assertRedirect('/');
        $archivo = basename(glob("{$tmp}/_archivo/cota-*.json.gz")[0]);

        $this->get('/')->assertOk()->assertSee('Proyectos eliminados con respaldo')->assertSee($archivo, false);
        $this->post('/respaldos/restaurar', ['archivo' => '../../.env'])->assertSessionHasErrors('archivo');
        $this->post('/respaldos/restaurar', ['archivo' => $archivo])->assertRedirect('/proyectos/cota')->assertSessionHas('ok');
        $this->assertSame(1, Proyecto::where('nombre', 'Cota')->firstOrFail()->paginas()->count());
        $this->post('/respaldos/restaurar', ['archivo' => $archivo])->assertSessionHasErrors('archivo');   // ya existe

        \Illuminate\Support\Facades\File::deleteDirectory($tmp);
    }
}
