<?php

namespace Tests\Feature;

use App\Models\Conexion;
use App\Models\Evento;
use App\Models\Proyecto;
use App\Support\EntornoWP;
use App\Support\PromptElementor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Conexiones por sitio, puente con Figma, recarga en vivo y guía con prompt. */
class ConexionYGuiaTest extends TestCase
{
    use RefreshDatabase;

    /** Prompt de ejemplo con el formato de un comando MCP; credenciales ficticias. */
    private const PROMPT = <<<'TXT'
        Connect Claude Code to your Elementor site.
        claude mcp add --transport http elementor-ecocreations https://ecocreations-staging.ejemplo.pe/wp-json/elementor/v1/mcp --header "Authorization: Basic ZWRpdG9yOmFiY2QgZWZnaCBpamtsIG1ub3AgcXJzdCB1dnd4"
        Username: editor
        Application password: abcd efgh ijkl mnop qrst uvwx
        TXT;

    protected function setUp(): void
    {
        parent::setUp();
        // Sin red en las pruebas: versiones publicadas fijas.
        Cache::put('versiones.wordpress.org', ['wordpress' => '6.8.3', 'elementor' => '4.3.1'], 3600);
    }

    public function test_el_prompt_de_elementor_se_lee_y_se_guarda_sin_secretos(): void
    {
        $datos = PromptElementor::extraer(self::PROMPT);
        $this->assertSame('https://ecocreations-staging.ejemplo.pe', $datos['sitio_url']);
        $this->assertSame('https://ecocreations-staging.ejemplo.pe/wp-json/elementor/v1/mcp', $datos['endpoint']);
        $this->assertSame('editor', $datos['usuario_wp']);
        $this->assertSame('elementor-ecocreations', $datos['nombre_mcp']);

        $saneado = PromptElementor::sanear(self::PROMPT);
        $this->assertStringNotContainsString('abcd efgh', $saneado['texto']);
        $this->assertStringNotContainsString('ZWRpdG9y', $saneado['texto']);
        $this->assertStringContainsString('elementor-ecocreations', $saneado['texto']);
        $this->assertSame(2, $saneado['ocultos']);

        $json = PromptElementor::sanear('{"mcpServers":{"wp":{"env":{"WP_APP_PASSWORD":"s3cr3t0","WP_API_URL":"https://x.ejemplo.pe/wp-json/mcp"}}}}');
        $this->assertStringNotContainsString('s3cr3t0', $json['texto']);
        $this->assertSame('wp', PromptElementor::extraer('{"mcpServers":{"wp":{}}}')['nombre_mcp']);
    }

    public function test_guardar_conexion_desde_el_prompt_no_almacena_la_contrasena(): void
    {
        $proyecto = Proyecto::create(['nombre' => 'ECOCREATIONS']);

        $this->post('/conexiones', ['proyecto_id' => $proyecto->id, 'prompt_mcp' => self::PROMPT])
            ->assertRedirect('/conexion')->assertSessionHas('ok');

        $c = Conexion::sole();
        $this->assertSame('elementor-ecocreations', $c->nombre_mcp);
        $this->assertSame('https://ecocreations-staging.ejemplo.pe', $c->sitio_url);
        $this->assertSame('editor', $c->usuario_wp);
        $this->assertStringNotContainsString('abcd efgh', $c->prompt_saneado);
        $this->assertStringContainsString('[oculto]', $c->prompt_saneado);
    }

    public function test_si_falla_la_validacion_el_prompt_no_vuelve_a_la_sesion(): void
    {
        $this->post('/conexiones', ['prompt_mcp' => self::PROMPT])->assertSessionHasErrors('proyecto_id');
        $this->assertNull(session()->getOldInput('prompt_mcp'));
    }

    public function test_el_nuevo_proyecto_crea_su_conexion_con_el_prompt(): void
    {
        $this->post('/proyectos', ['nombre' => 'ECOCREATIONS', 'prompt_mcp' => self::PROMPT])->assertRedirect();

        $proyecto = Proyecto::where('nombre', 'ECOCREATIONS')->sole();
        $this->assertSame('https://ecocreations-staging.ejemplo.pe', $proyecto->sitio_wp);
        $this->assertSame('elementor-ecocreations', $proyecto->conexion->nombre_mcp);
    }

    public function test_conexion_destaca_la_del_ultimo_proyecto_trabajado(): void
    {
        $viejo = Proyecto::create(['nombre' => 'Café Misti']);
        $viejo->conexiones()->create(['nombre_mcp' => 'elementor-cafe-misti', 'sitio_url' => 'https://cafe.ejemplo.pe']);
        $this->travel(5)->minutes();
        $nuevo = Proyecto::create(['nombre' => 'Hostal Selva Alegre']);
        $nuevo->conexiones()->create(['nombre_mcp' => 'elementor-hostal', 'sitio_url' => 'http://localhost:8080', 'elementor_version' => '4.2.0']);

        $this->get('/conexion')->assertOk()
            ->assertSee('Conexión actual · Hostal Selva Alegre')
            ->assertSee('elementor-cafe-misti')
            ->assertSee('4.3.1 disponible', false);
    }

    public function test_registro_figma_carga_paginas_tokens_y_enlace(): void
    {
        $json = json_encode([
            'figma'   => 'https://www.figma.com/design/Xk29Pq/ECOCREATIONS',
            'paginas' => [['nombre' => 'Inicio', 'secciones' => 7], ['nombre' => 'Contacto', 'secciones' => 3]],
            'tokens'  => [['tipo' => 'color', 'valor' => '#2F6B4F', 'nota' => 'primario'], ['tipo' => 'tipografia', 'valor' => 'Poppins', 'nota' => 'títulos']],
        ]);

        $this->artisan('registro:figma', ['proyecto' => 'ECOCREATIONS', '--json' => $json])->assertSuccessful();
        $this->artisan('registro:figma', ['proyecto' => 'ECOCREATIONS', '--json' => $json])->assertSuccessful(); // sin duplicar

        $p = Proyecto::where('nombre', 'ECOCREATIONS')->sole();
        $this->assertSame('https://www.figma.com/design/Xk29Pq/ECOCREATIONS', $p->archivo_figma);
        $this->assertSame(2, $p->paginas()->count());
        $this->assertSame(7, $p->paginas()->where('nombre', 'Inicio')->value('secciones_total'));
        $this->assertSame(2, $p->tokens()->count());
        $this->assertSame(2, Evento::where('tipo', 'figma')->count());

        $this->artisan('registro:figma', ['proyecto' => 'ECOCREATIONS', '--json' => 'no es json'])->assertFailed();
    }

    public function test_la_huella_cambia_cuando_la_consola_escribe(): void
    {
        Proyecto::create(['nombre' => 'En vivo']);
        $antes = $this->getJson('/estado/version')->assertOk()->json('v');
        $this->assertSame($antes, $this->getJson('/estado/version')->json('v'));

        $this->artisan('registro:tokens', ['proyecto' => 'En vivo', 'tokens' => ['color|#123456|primario']]);

        $this->assertNotSame($antes, $this->getJson('/estado/version')->json('v'));
    }

    public function test_la_guia_muestra_tokens_activos_y_el_prompt_con_el_servidor_mcp(): void
    {
        $p = Proyecto::create(['nombre' => 'ECOCREATIONS', 'sitio_wp' => 'https://eco.ejemplo.pe']);
        $p->conexiones()->create(['nombre_mcp' => 'elementor-ecocreations', 'sitio_url' => 'https://eco.ejemplo.pe']);
        $p->tokens()->create(['tipo' => 'color', 'valor' => '#2F6B4F', 'nota' => 'primario']);
        $p->tokens()->create(['tipo' => 'color', 'valor' => '#FF0000', 'nota' => 'descartado', 'incluido' => false]);
        $p->tokens()->create(['tipo' => 'tipografia', 'valor' => "Poppins'; background:url(x)", 'nota' => 'títulos']);
        $inicio = $p->paginas()->create(['nombre' => 'Inicio']);

        $this->get("/proyectos/{$p->id}/guia?pagina={$inicio->id}")->assertOk()
            ->assertSee('#2F6B4F')
            ->assertDontSee('#FF0000')
            ->assertSee('servidor MCP `elementor-ecocreations`', false)
            ->assertSee('Construye la página &quot;Inicio&quot;', false)
            ->assertDontSee("font-family: 'Poppins'; background", false);
    }

    public function test_comprobar_varias_conexiones_en_paralelo_no_bloquea(): void
    {
        $p = Proyecto::create(['nombre' => 'Sin sitio']);
        // Puertos locales cerrados: fallan al instante, no esperan el timeout.
        $a = $p->conexiones()->create(['nombre_mcp' => 'a', 'sitio_url' => 'http://127.0.0.1:9']);
        $b = $p->conexiones()->create(['nombre_mcp' => 'b', 'sitio_url' => 'http://127.0.0.1:19']);

        $inicio = microtime(true);
        $this->post('/conexiones/comprobar')->assertRedirect()->assertSessionHas('ok', fn ($m) => str_contains($m, '0 de 2'));

        $this->assertLessThan(3, microtime(true) - $inicio);
        $this->assertSame('sin_conexion', $a->fresh()->estado);
        $this->assertNotNull($b->fresh()->comprobada_en);
    }

    public function test_claude_md_y_guia_prompt_para_la_carpeta_del_cliente(): void
    {
        $p = Proyecto::create(['nombre' => 'ECOCREATIONS', 'sitio_wp' => 'https://eco.ejemplo.pe']);
        $p->conexiones()->create(['nombre_mcp' => 'elementor-ecocreations', 'sitio_url' => 'https://eco.ejemplo.pe']);
        $p->paginas()->create(['nombre' => 'Inicio']);
        $p->tokens()->create(['tipo' => 'color', 'valor' => '#2F6B4F', 'nota' => 'primario']);

        $md = $this->get("/proyectos/{$p->id}/claude-md")->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="CLAUDE.md"')->getContent();
        $this->assertStringContainsString('# ECOCREATIONS — contexto para el asistente', $md);
        $this->assertStringContainsString('`elementor-ecocreations`', $md);
        $this->assertStringContainsString('Páginas en alcance: Inicio', $md);
        $this->assertStringContainsString('guia:prompt "ECOCREATIONS" "<Página>"', $md);

        $this->artisan('guia:prompt', ['proyecto' => 'ECOCREATIONS', 'pagina' => 'inicio'])
            ->expectsOutputToContain('Color primario: #2F6B4F')->assertSuccessful();
        $this->artisan('guia:prompt', ['proyecto' => 'No existe'])->assertFailed();
        $this->artisan('guia:prompt', ['proyecto' => 'ECOCREATIONS', 'pagina' => 'Blog'])->assertFailed();
    }

    public function test_versiones_desactualizadas(): void
    {
        $this->assertTrue(EntornoWP::desactualizada('3.31.4', '4.3.1'));
        $this->assertFalse(EntornoWP::desactualizada('4.3.1', '4.3.1'));
        $this->assertFalse(EntornoWP::desactualizada('activo', '4.3.1'));
        $this->assertFalse(EntornoWP::desactualizada(null, '4.3.1'));
    }
}
