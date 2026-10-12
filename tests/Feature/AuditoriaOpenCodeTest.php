<?php

namespace Tests\Feature;

use App\Models\Proyecto;
use App\Support\ConfigAgentes;
use App\Support\LecturaFigma;
use App\Support\PromptGuia;
use App\Support\RolesAgentes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** Lo que encontró la auditoría antes de construir con OpenCode y modelos gratuitos. */
class AuditoriaOpenCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_puente_que_leen_los_agentes_salta_la_politica_de_ejecucion(): void
    {
        $p = Proyecto::create(['nombre' => 'Cota']);
        $pag = $p->paginas()->create(['nombre' => 'Inicio']);
        $pag->secciones()->create(['nombre' => 'Hero', 'estado' => 'planificada']);

        $textos = [PromptGuia::agentsMd($p), PromptGuia::prompt($p, $pag), PromptGuia::siguiente($p, $pag), ...array_values(RolesAgentes::archivos($p)),
            file_get_contents(base_path('.claude/skills/alma-figma/SKILL.md'))];
        foreach ($textos as $t) {
            $this->assertStringNotContainsString('-NoProfile -File', $t);
        }
        $this->assertStringContainsString('-ExecutionPolicy Bypass -File', PromptGuia::agentsMd($p));
    }

    public function test_el_frontmatter_de_los_roles_no_tiene_escapes_yaml_invalidos(): void
    {
        $p = Proyecto::create(['nombre' => 'Cota']);
        foreach (RolesAgentes::archivos($p) as $ruta => $contenido) {
            if (! str_ends_with(dirname($ruta), 'agents')) {
                continue;
            }
            preg_match('/^description: (.*)$/m', $contenido, $m);
            // Comillas simples: la barra invertida es literal ("Design\vN\secciones" rompía la versión con comillas dobles).
            $this->assertMatchesRegularExpression("/^'(?:[^']|'')*'$/", $m[1], $ruta);
        }
    }

    public function test_opencode_sin_code_mode_solo_lectura_de_figwright_y_sin_oauth(): void
    {
        $p = Proyecto::create(['nombre' => 'Cota']);
        $c = $p->conexiones()->create(['nombre_mcp' => 'elementor-cota', 'sitio_url' => 'https://cota.ejemplo.pe']);
        $oc = json_decode(ConfigAgentes::opencode($p, $c), true);

        $this->assertFalse($oc['mcp']['servers']['figwright']['codemode']);
        $this->assertFalse($oc['mcp']['servers']['elementor-cota']['oauth']);
        $this->assertSame('{env:ALMA_COTA_AUTH}', $oc['mcp']['servers']['elementor-cota']['headers']['Authorization']);
        $acciones = array_column($oc['permissions'], 'effect', 'action');
        $this->assertSame('deny', $acciones['figwright_*']);
        $this->assertSame('allow', $acciones['figwright_get_design_context']);
        $this->assertArrayNotHasKey('figwright_create_frame', $acciones);
        $this->assertSame('allow', $oc['permissions'][array_key_last($oc['permissions'])]['effect']);   // la última regla gana
    }

    public function test_la_lectura_acepta_lo_que_suele_escribir_un_modelo_pequeno(): void
    {
        $r = LecturaFigma::normalizar([
            'pages' => [['name' => 'Inicio', 'id' => '1:2', 'sections' => [['name' => 'Hero', 'id' => '1:3'], 'Servicios', ['id' => '9:9']]]],
            'tokens' => [
                ['tipo' => 'tipografía', 'valor' => 'Poppins', 'nota' => 'títulos'],
                ['type' => 'colour', 'value' => 'rgb(47, 107, 79)', 'role' => 'primario'],
                ['tipo' => 'color', 'valor' => '0.2, 0.4'],
                ['tipo' => 'icono', 'valor' => 'x'],
            ],
        ]);

        $this->assertSame('Inicio', $r['datos']['paginas'][0]['nombre']);
        $this->assertSame('1:2', $r['datos']['paginas'][0]['figma_id']);
        $this->assertSame([['nombre' => 'Hero', 'figma_id' => '1:3'], ['nombre' => 'Servicios']], $r['datos']['paginas'][0]['secciones']);
        $this->assertSame(['tipo' => 'tipografia', 'valor' => 'Poppins', 'nota' => 'títulos'], $r['datos']['tokens'][0]);
        $this->assertSame(['tipo' => 'color', 'valor' => '#2F6B4F', 'nota' => 'primario'], $r['datos']['tokens'][1]);
        $this->assertCount(2, $r['datos']['tokens']);
        $this->assertCount(3, $r['avisos']);   // sección sin nombre, color inválido, tipo desconocido
        $this->assertSame('#2F6B4F', LecturaFigma::hex('rgba(0.184, 0.42, 0.31, 1)'));
    }

    public function test_diseno_guardar_explica_errores_y_no_duplica_versiones(): void
    {
        $p = Proyecto::create(['nombre' => 'Clínica Sur']);
        $tmp = tempnam(sys_get_temp_dir(), 'lectura');

        // Archivo que no existe.
        $this->artisan('diseno:guardar', ['proyecto' => 'Clínica Sur', '--archivo' => $tmp . '-no'])->expectsOutputToContain('No encuentro el archivo')->assertFailed();

        // Sin páginas: no crea versión y muestra el formato.
        file_put_contents($tmp, json_encode(['tokens' => [['tipo' => 'color', 'valor' => '#000000']]]));
        $this->artisan('diseno:guardar', ['proyecto' => 'Clínica Sur', '--archivo' => $tmp])->expectsOutputToContain('no tiene páginas')->assertFailed();
        $this->assertSame(0, $p->disenos()->count());

        // UTF-8 con BOM (Set-Content -Encoding UTF8 de PowerShell 5.1) y claves en inglés.
        file_put_contents($tmp, "\xEF\xBB\xBF" . json_encode(['pages' => [['name' => 'Inicio', 'sections' => ['Hero']]], 'tokens' => [['tipo' => 'tipografía', 'valor' => 'Inter']]]));
        $this->assertSame(0, Artisan::call('diseno:guardar', ['proyecto' => 'clinica sur', '--archivo' => $tmp]));
        $this->assertStringContainsString('1 páginas, 1 secciones y 1 tokens', Artisan::output());

        // La misma lectura otra vez: no crea v2.
        $this->assertSame(0, Artisan::call('diseno:guardar', ['proyecto' => 'Clínica Sur', '--archivo' => $tmp]));
        $this->assertStringContainsString('Es igual al diseño v1', Artisan::output());
        $this->assertSame(1, $p->disenos()->count());

        // UTF-16 (Out-File de PowerShell 5.1).
        file_put_contents($tmp, "\xFF\xFE" . mb_convert_encoding(json_encode(['paginas' => [['nombre' => 'Contacto']]]), 'UTF-16LE', 'UTF-8'));
        $this->assertSame(0, Artisan::call('diseno:guardar', ['proyecto' => 'Clínica Sur', '--archivo' => $tmp]));
        $this->assertSame(2, $p->disenos()->count());
        @unlink($tmp);
    }

    public function test_nombres_sin_tildes_ni_mayusculas_no_crean_paginas_duplicadas(): void
    {
        $p = Proyecto::create(['nombre' => 'Clínica Sur']);
        $p->paginas()->create(['nombre' => 'Inicio']);

        $this->artisan('registro:add', ['proyecto' => 'Clinica Sur', 'pagina' => 'inicio', 'seccion' => 'Hero', 'minutos' => 10])->assertSuccessful();
        $this->assertSame(1, $p->paginas()->count());

        $this->artisan('registro:add', ['proyecto' => 'Clínica Sur', 'pagina' => 'Inicioo', 'seccion' => 'Hero', 'minutos' => 10])
            ->expectsOutputToContain('no está en Clínica Sur')->assertFailed();
        $this->assertSame(1, $p->paginas()->count());

        $this->artisan('registro:cola', ['proyecto' => 'clinica sur'])->expectsOutputToContain('Cola vacía')->assertSuccessful();
        $this->artisan('registro:cola', ['proyecto' => 'Otro'])->expectsOutputToContain('No existe el proyecto')->assertFailed();
    }
}
