<?php

namespace App\Console\Commands;

use App\Models\Pagina;
use Illuminate\Console\Command;

/**
 * Experimento Jev (TypeSafe vía OpenRouter): decisiones tipadas sobre una página.
 *
 *   php artisan jev:sugerencias 3            → simulación (muestra el payload)
 *   php artisan jev:sugerencias 3 --en-vivo  → llama a OpenRouter (requiere OPENROUTER_API_KEY en .env)
 *
 * Preguntas que se evalúan:
 *  - noul:   ¿hay widgets repetidos que convenga volver componente/estilo global?
 *  - choice: ¿cuál widget es el mejor candidato a reutilizarse?
 *  - score:  riesgo de perder fidelidad al diseño en esta página (bajo→alto).
 */
class JevSugerencias extends Command
{
    protected $signature = 'jev:sugerencias {pagina : ID de la página} {--en-vivo : Llamar de verdad a OpenRouter}';
    protected $description = 'Pide a Jev (modelo de decisiones) sugerencias de reutilización y fidelidad para una página';

    public function handle(): int
    {
        $pagina = Pagina::with(['proyecto', 'secciones'])->findOrFail($this->argument('pagina'));

        $lineas = $pagina->secciones->map(fn ($s) => sprintf(
            '- %s | widget: %s | estado: %s | %d min | %d correcciones',
            $s->nombre, $s->widget_plan ?? 'sin mapear', $s->estado, $s->minutos, $s->correcciones
        ))->implode("\n");

        $widgets = $pagina->secciones->pluck('widget_plan')->filter()->unique()->values()->all();
        if (count($widgets) < 2) {
            $widgets = array_merge($widgets, ['(ninguno)']);
        }

        $payload = [
            'model' => 'typesafe/jev-1.13',
            'state' => "Página \"{$pagina->nombre}\" del proyecto \"{$pagina->proyecto->nombre}\" "
                . "(constructor: Elementor, flujo: diseño de Figma → construcción supervisada por secciones).\n"
                . "Secciones:\n{$lineas}",
            'questions' => [
                ['type' => 'noul', 'text' => '¿Hay widgets que se repiten en varias secciones y convendría convertirlos en un componente o estilo global reutilizable?'],
                ['type' => 'choice', 'text' => '¿Cuál de estos widgets es el mejor candidato a componente reutilizable?', 'options' => $widgets],
                ['type' => 'score', 'text' => '¿Qué riesgo hay de perder fidelidad al diseño original en esta página?', 'options' => ['bajo', 'medio', 'alto']],
            ],
        ];

        if (! $this->option('en-vivo')) {
            $this->info('SIMULACIÓN (usa --en-vivo con OPENROUTER_API_KEY para llamar de verdad). Payload:');
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->line("\nEndpoint: POST https://openrouter.ai/api/alpha/decisions");

            return self::SUCCESS;
        }

        $key = env('OPENROUTER_API_KEY');
        if (! $key) {
            $this->error('Falta OPENROUTER_API_KEY en el .env');

            return self::FAILURE;
        }

        $ch = curl_init('https://openrouter.ai/api/alpha/decisions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$key}", 'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        ]);
        $respuesta = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($respuesta === false || $codigo >= 400) {
            $this->error("OpenRouter respondió HTTP {$codigo}: " . substr((string) $respuesta, 0, 400));

            return self::FAILURE;
        }

        $this->info("Decisiones de Jev (HTTP {$codigo}):");
        $this->line(json_encode(json_decode($respuesta, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->line("\nRecuerda: la API está en alpha; valida las respuestas antes de automatizar nada con ellas.");

        return self::SUCCESS;
    }
}
