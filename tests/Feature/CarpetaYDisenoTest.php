<?php

namespace Tests\Feature;

use App\Models\Conexion;
use App\Models\Diseno;
use App\Models\Proyecto;
use App\Support\CarpetaProyecto;
use App\Support\Windows;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Fase 1: carpeta por proyecto · Fase 2: MCP de los 4 agentes y credencial · Fase 3: versiones del diseño. */
class CarpetaYDisenoTest extends TestCase
{
    use RefreshDatabase;

    private string $base;

    private const PROMPT = "claude mcp add --transport http elementor-cota http://localhost:8883/wp-json/elementor/v1/mcp\nUsername: editor\nApplication password: abcd efgh ijkl mnop qrst uvwx";

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir() . '/alma-proyectos-' . uniqid();
        mkdir($this->base);
        config(['alma.proyectos_dir' => $this->base]);
        Windows::$llamadas = [];
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    private function lectura(array $paginas): string
    {
        return json_encode(['figma' => 'https://www.figma.com/design/abc/Cota', 'paginas' => $paginas,
            'tokens' => [['tipo' => 'color', 'valor' => '#2F6B4F', 'nota' => 'primario']]]);
    }

    public function test_crear_proyecto_deja_la_carpeta_lista_para_los_cuatro_agentes_sin_secretos(): void
    {
        $this->post('/proyectos', ['nombre' => 'Cota', 'prompt_mcp' => self::PROMPT])->assertRedirect()
            ->assertSessionHas('ok', fn ($m) => str_contains($m, 'ALMA_COTA_AUTH'));

        $p = Proyecto::where('nombre', 'Cota')->sole();
        $raiz = $this->base . '/Cota';
        $this->assertSame('Cota', $p->carpeta);
        foreach (['AGENTS.md', 'CLAUDE.md', '.mcp.json', '.codex/config.toml', '.cursor/mcp.json', 'opencode.json'] as $f) {
            $this->assertFileExists("{$raiz}/{$f}");
        }
        $this->assertDirectoryExists("{$raiz}/Design");
        $this->assertSame("@AGENTS.md\n", file_get_contents("{$raiz}/CLAUDE.md"));

        $claude = json_decode(file_get_contents("{$raiz}/.mcp.json"), true);
        $this->assertSame('${ALMA_COTA_AUTH}', $claude['mcpServers']['elementor-cota']['headers']['Authorization']);
        $this->assertSame('stdio', $claude['mcpServers']['figwright']['type']);
        $cursor = json_decode(file_get_contents("{$raiz}/.cursor/mcp.json"), true);
        $this->assertSame('${env:ALMA_COTA_AUTH}', $cursor['mcpServers']['elementor-cota']['headers']['Authorization']);
        $opencode = json_decode(file_get_contents("{$raiz}/opencode.json"), true);
        $this->assertSame('{env:ALMA_COTA_AUTH}', $opencode['mcp']['elementor-cota']['headers']['Authorization']);
        $this->assertStringContainsString('env_http_headers = { "Authorization" = "ALMA_COTA_AUTH" }', file_get_contents("{$raiz}/.codex/config.toml"));
        $this->assertStringContainsString('Trabajas únicamente en **Cota**', file_get_contents("{$raiz}/AGENTS.md"));

        // La credencial fue a Windows por variable de entorno; ni la carpeta ni la base la contienen.
        $llamada = collect(Windows::$llamadas)->firstWhere('accion', 'guardar-credencial');
        $this->assertSame(['ALMA_COTA_AUTH'], $llamada['args']);
        $this->assertSame('Basic ' . base64_encode('editor:abcd efgh ijkl mnop qrst uvwx'), $llamada['env']['ALMA_VALOR']);
        $this->assertNotNull(Conexion::sole()->credencial_en_equipo);
        foreach (File::allFiles($raiz, true) as $archivo) {
            $this->assertStringNotContainsString('abcd efgh', $archivo->getContents());
            $this->assertStringNotContainsString(base64_encode('editor:abcd efgh ijkl mnop qrst uvwx'), $archivo->getContents());
        }
        $this->assertStringNotContainsString('abcd efgh', (string) Conexion::sole()->prompt_saneado);
    }

    public function test_botones_abren_carpeta_terminal_y_cursor_desde_el_panel(): void
    {
        $p = Proyecto::create(['nombre' => 'Cota']);
        CarpetaProyecto::preparar($p);

        foreach (['terminal' => 'abrir-terminal', 'cursor' => 'abrir-cursor', 'carpeta' => 'abrir-carpeta'] as $que => $accion) {
            $this->post("/proyectos/{$p->id}/abrir/{$que}")->assertRedirect();
            $this->assertSame($accion, end(Windows::$llamadas)['accion']);
        }
        $this->post("/proyectos/{$p->id}/abrir/otra-cosa")->assertNotFound();
        $this->assertSame('C:\\Users\\Ana\\AlmaProyectos\\Cota', CarpetaProyecto::aWindows('/mnt/c/Users/Ana/AlmaProyectos/Cota'));
        $this->assertSame('Cota Web 2026', CarpetaProyecto::nombreCarpeta('Cota: Web/2026?'));
    }

    public function test_versiones_del_diseno_la_primera_se_usa_y_las_siguientes_esperan_al_boton(): void
    {
        $p = Proyecto::create(['nombre' => 'Cota']);
        $v1 = $this->lectura([['nombre' => 'Inicio', 'figma_id' => '1:2', 'secciones' => [['nombre' => 'Hero', 'figma_id' => '1:3']]]]);

        $this->artisan('diseno:guardar', ['proyecto' => 'Cota', '--json' => $v1])
            ->expectsOutputToContain('Diseño v1 guardado')->expectsOutputToContain('Design\\v1\\capturas')->assertSuccessful();
        $this->assertSame('activa', Diseno::where('version', 1)->value('estado'));
        $this->assertSame(1, $p->paginas()->count());
        $this->assertFileExists($this->base . '/Cota/Design/v1/lectura.json');
        $this->assertDirectoryExists($this->base . '/Cota/Design/v1/capturas');

        $v2 = $this->lectura([
            ['nombre' => 'Inicio', 'figma_id' => '1:2', 'secciones' => [['nombre' => 'Hero', 'figma_id' => '1:3'], ['nombre' => 'Testimonios', 'figma_id' => '1:9']]],
            ['nombre' => 'Contacto', 'figma_id' => '4:1', 'secciones' => [['nombre' => 'Formulario', 'figma_id' => '4:2']]],
        ]);
        $this->artisan('diseno:guardar', ['proyecto' => 'Cota', '--json' => $v2])->expectsOutputToContain('Queda como versión nueva')->assertSuccessful();

        // El panel no cambió: la v2 espera.
        $this->assertSame(1, $p->paginas()->count());
        $this->get("/proyectos/{$p->id}")->assertOk()->assertSee('Páginas nuevas:')->assertSee('Contacto')->assertSee('Inicio › Testimonios');

        $v2modelo = Diseno::where('version', 2)->sole();
        $this->post("/disenos/{$v2modelo->id}/usar")->assertRedirect();
        $this->assertSame(2, $p->paginas()->count());
        $this->assertSame('activa', $v2modelo->fresh()->estado);
        $this->assertSame('anterior', Diseno::where('version', 1)->value('estado'));
        $this->assertStringContainsString('Design\\v2', file_get_contents($this->base . '/Cota/AGENTS.md'));

        $this->artisan('diseno:guardar', ['proyecto' => 'No existe', '--json' => $v1])->assertFailed();
    }

    public function test_sin_carpeta_base_el_panel_sigue_funcionando(): void
    {
        config(['alma.proyectos_dir' => null]);
        $this->post('/proyectos', ['nombre' => 'Sin carpeta'])->assertRedirect();
        $p = Proyecto::where('nombre', 'Sin carpeta')->sole();
        $this->assertNull($p->carpeta);
        $this->get("/proyectos/{$p->id}")->assertOk()->assertSee('Falta la carpeta base de proyectos');
    }
}
