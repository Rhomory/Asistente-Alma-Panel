<?php

namespace Tests\Feature;

use App\Models\Proyecto;
use App\Support\CarpetaProyecto;
use App\Support\ConfigAgentes;
use App\Support\PromptGuia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MultiagenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_figwright_va_con_version_fija_en_los_cuatro_agentes(): void
    {
        config(['alma.figwright' => '0.6.0']);
        $p = Proyecto::create(['nombre' => 'Cota']);

        foreach (ConfigAgentes::todos($p) as $agente) {
            $this->assertStringContainsString('@figwright/mcp@0.6.0', $agente['codigo']);
            $this->assertStringNotContainsString('@latest', $agente['codigo']);
        }
    }

    public function test_la_carpeta_trae_los_roles_para_cada_agente(): void
    {
        $tmp = sys_get_temp_dir() . '/alma-multi-' . uniqid();
        mkdir($tmp);
        config(['alma.proyectos_dir' => $tmp]);
        $p = Proyecto::create(['nombre' => 'Cota']);

        $archivos = CarpetaProyecto::preparar($p);
        $raiz = CarpetaProyecto::ruta($p);

        foreach (['.claude/agents', '.opencode/agents', '.alma/roles'] as $dir) {
            $this->assertContains("{$dir}/alma-figma-lector.md", $archivos);
            $this->assertFileExists("{$raiz}/{$dir}/alma-conexion.md");
        }
        $claude = file_get_contents("{$raiz}/.claude/agents/alma-figma-lector.md");
        $this->assertStringStartsWith("---\nname: alma-figma-lector\n", $claude);
        $this->assertStringContainsString('Design\vN\secciones', $claude);
        $this->assertStringContainsString("mode: subagent", file_get_contents("{$raiz}/.opencode/agents/alma-figma-lector.md"));
        foreach ($archivos as $a) {
            $this->assertSame(0, preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', file_get_contents("{$raiz}/{$a}")), "Caracter de control en {$a}");
        }

        $md = PromptGuia::agentsMd($p);
        $this->assertStringContainsString('Trabajo en paralelo', $md);
        $this->assertStringContainsString('.alma/roles/alma-figma-lector.md', $md);

        File::deleteDirectory($tmp);
    }

    public function test_el_proyecto_muestra_que_falta_para_empezar(): void
    {
        config(['alma.proyectos_dir' => null]);
        $p = Proyecto::create(['nombre' => 'Cota']);

        $this->get(route('proyecto', $p))->assertOk()
            ->assertSee('Para empezar')
            ->assertSee('0 de 4 listos')
            ->assertSee('ejecuta scripts');
    }
}
