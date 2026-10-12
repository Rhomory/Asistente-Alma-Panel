<?php

namespace Tests\Feature;

use App\Models\Evento;
use App\Models\Proyecto;
use App\Support\ConfigAgentes;
use App\Support\PromptGuia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Arranque rápido de figwright, shell de OpenCode y retomar con poco contexto (--siguiente). */
class ConexionRapidaTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/alma-rapida-' . uniqid();
        mkdir($this->tmp);
        ConfigAgentes::olvidarComando();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        ConfigAgentes::olvidarComando();
        ConfigAgentes::$mnt = '/mnt';
        parent::tearDown();
    }

    /** Simula Z:\alma con figwright instalado, visto desde WSL en $tmp/z/alma. */
    private function instalarFigwrightLocal(string $version): void
    {
        $pkg = "{$this->tmp}/z/alma/mcp/node_modules/@figwright/mcp";
        mkdir($pkg . '/dist', 0775, true);
        file_put_contents("{$pkg}/package.json", json_encode(['version' => $version, 'bin' => ['figwright-mcp' => './dist/index.mjs']]));
    }

    public function test_sin_instalacion_local_usa_npx_con_version_fija(): void
    {
        config(['alma.puente' => 'C:\Users\nadie-' . uniqid() . '\.alma\alma.ps1', 'alma.figwright' => '0.6.0']);
        $p = Proyecto::create(['nombre' => 'Cota']);

        $this->assertSame(['npx', ['-y', '@figwright/mcp@0.6.0']], ConfigAgentes::comandoFigwright());
        $claude = json_decode(ConfigAgentes::claude($p, null), true)['mcpServers']['figwright'];
        $this->assertSame(['cmd', ['/c', 'npx', '-y', '@figwright/mcp@0.6.0']], [$claude['command'], $claude['args']]);
    }

    public function test_con_instalacion_local_en_la_version_fija_lanza_node_directo(): void
    {
        // Z:\alma se ve desde "WSL" como $tmp/z/alma.
        ConfigAgentes::$mnt = $this->tmp;
        $this->instalarFigwrightLocal('0.6.0');
        config(['alma.puente' => 'Z:\alma\alma.ps1', 'alma.figwright' => '0.6.0']);
        $p = Proyecto::create(['nombre' => 'Cota']);
        $entrada = 'Z:\alma\mcp\node_modules\@figwright\mcp\dist\index.mjs';

        $this->assertSame(['node', [$entrada]], ConfigAgentes::comandoFigwright());
        $oc = json_decode(ConfigAgentes::opencode($p, null), true);
        $this->assertSame(['node', $entrada], $oc['mcp']['servers']['figwright']['command']);
        $this->assertStringContainsString('command = "node"', ConfigAgentes::codex($p, null));
        $this->assertStringContainsString(json_encode($entrada, JSON_UNESCAPED_SLASHES), ConfigAgentes::codex($p, null));

        // Otra versión instalada: vuelve a npx para no desalinear servidor y plugin.
        ConfigAgentes::olvidarComando();
        config(['alma.figwright' => '0.7.0']);
        $this->assertSame('npx', ConfigAgentes::comandoFigwright()[0]);
    }

    public function test_opencode_fija_un_shell_powershell(): void
    {
        $p = Proyecto::create(['nombre' => 'Cota']);
        $oc = json_decode(ConfigAgentes::opencode($p, null), true);

        $this->assertContains($oc['shell'], ['pwsh', 'powershell']);
    }

    public function test_siguiente_da_solo_la_proxima_seccion_y_prioriza_correcciones(): void
    {
        $p = Proyecto::create(['nombre' => 'Clínica Sur']);
        $p->tokens()->create(['tipo' => 'color', 'valor' => '#2F6B4F', 'nota' => 'primario', 'incluido' => true]);
        $pag = $p->paginas()->create(['nombre' => 'Inicio']);
        $pag->secciones()->create(['nombre' => 'Hero', 'estado' => 'aprobada', 'figma_id' => '1:2']);
        $serv = $pag->secciones()->create(['nombre' => 'Servicios', 'estado' => 'planificada', 'figma_id' => '1:9', 'widget_plan' => 'Grid + Icon Box']);
        $pag->secciones()->create(['nombre' => 'Contacto', 'estado' => 'planificada', 'figma_id' => '1:20']);

        $this->assertSame(0, Artisan::call('guia:prompt', ['proyecto' => 'Clínica Sur', 'pagina' => 'Inicio', '--siguiente' => true]));
        $salida = Artisan::output();
        foreach (['avance 1/3', 'Siguiente: 2. Servicios — Grid + Icon Box', 'nodeId "1:9"', 'primario #2F6B4F', 'registro:add "Clínica Sur" "Inicio" "Servicios"'] as $texto) {
            $this->assertStringContainsString($texto, $salida);
        }
        $this->assertLessThan(12, substr_count($salida, "\n"));   // pocas líneas: cabe en cualquier contexto

        // Una corrección pedida pasa delante.
        $hero = $pag->secciones()->where('nombre', 'Hero')->first();
        $hero->update(['estado' => 'construyendo']);
        Evento::create(['seccion_id' => $hero->id, 'tipo' => 'solicitud_correccion', 'detalle' => 'El botón va en naranja', 'resuelto' => false]);
        $texto = PromptGuia::siguiente($p, $pag);
        $this->assertStringContainsString('Siguiente: 1. Hero', $texto);
        $this->assertStringContainsString('El botón va en naranja', $texto);

        // Todo hecho.
        $pag->secciones()->update(['estado' => 'construida']);
        $this->assertStringContainsString('No quedan secciones', PromptGuia::siguiente($p, $pag));

        // El plan completo muestra el estado de cada sección y cómo retomar.
        $serv->update(['estado' => 'aprobada']);
        $guia = PromptGuia::prompt($p, $pag);
        $this->assertStringContainsString('Servicios — Grid + Icon Box · Figma 1:9 · aprobada', $guia);
        $this->assertStringContainsString('--siguiente', $guia);
    }
}
