<?php

namespace App\Jobs;

use App\Models\PlanilhaCotacaoItem;
use App\Services\CrawlerService;
use App\Support\Utf8Sanitizer;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessarPlanilhaCotacaoItemLojaJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 90;

    public int $tries = 1;

    public function __construct(
        public readonly int $itemId,
        public readonly string $scraperIdentificador,
    ) {}

    public function handle(CrawlerService $crawler): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $item = PlanilhaCotacaoItem::findOrFail($this->itemId);
        $termoBusca = $this->termoBusca($item);
        $resultadosResumo = [];
        $erro = null;

        try {
            $resultados = $crawler->buscarEmLoja($termoBusca, $this->scraperIdentificador);
            $resultados = array_values(array_filter(
                $resultados,
                fn (array $resultado): bool => (float) ($resultado['preco'] ?? 0) > 0,
            ));

            $resultadosResumo = array_map(
                fn (array $resultado): array => [
                    'site' => $resultado['site'] ?? $this->scraperIdentificador,
                    'nome_site' => $resultado['nome_site'] ?? null,
                    'nome' => $resultado['nome'] ?? null,
                    'preco' => isset($resultado['preco']) ? (float) $resultado['preco'] : null,
                    'url' => $resultado['url'] ?? null,
                    'imagem' => $resultado['imagem'] ?? null,
                    'marca_detectada' => $resultado['marca_detectada'] ?? null,
                    'score_produto' => $resultado['score_produto'] ?? null,
                    'score_meilisearch' => $resultado['score_meilisearch'] ?? null,
                    'match_meilisearch' => $resultado['match_meilisearch'] ?? null,
                    'atributos_extraidos' => $resultado['atributos_extraidos'] ?? null,
                    'codigo' => $resultado['codigo'] ?? null,
                    'disponivel' => $resultado['disponivel'] ?? true,
                    'capturado_em' => now()->toIso8601String(),
                ],
                $resultados,
            );
        } catch (\Throwable $e) {
            $erro = "{$this->scraperIdentificador}: {$e->getMessage()}";
        }

        $this->registrarConclusaoDaLoja($resultadosResumo, $erro);
    }

    public function failed(?\Throwable $exception): void
    {
        $erro = "{$this->scraperIdentificador}: ".($exception?->getMessage() ?? 'Falha ao processar a loja.');

        $this->registrarConclusaoDaLoja([], $erro);
    }

    /**
     * @param  array<int, array<string, mixed>>  $resultadosResumo
     */
    private function registrarConclusaoDaLoja(array $resultadosResumo, ?string $erro): void
    {
        DB::transaction(function () use ($resultadosResumo, $erro): void {
            $item = PlanilhaCotacaoItem::whereKey($this->itemId)->lockForUpdate()->firstOrFail();
            $statusAnterior = $item->status;

            if (in_array($statusAnterior, ['concluido', 'sem_resultado', 'erro'], true)) {
                return;
            }

            $resultados = array_merge($item->resultados ?? [], $resultadosResumo);
            $resultados = $this->resultadosUnicosOrdenados($resultados);
            $resultados = Utf8Sanitizer::sanitize($resultados);
            $totalLojas = max((int) $item->lojas_total, 1);
            $lojasProcessadas = min(($item->lojas_processadas ?? 0) + 1, $totalLojas);
            $erros = trim(implode("\n", array_filter([$item->erro_mensagem, $erro])));
            $finalizouItem = $lojasProcessadas >= $totalLojas;

            $updates = [
                'lojas_processadas' => $lojasProcessadas,
                'resultados' => array_slice($resultados, 0, 20),
                'erro_mensagem' => $erros !== '' ? $erros : null,
            ];

            if ($finalizouItem) {
                $updates['status'] = ! empty($resultados) ? 'concluido' : 'sem_resultado';
            }

            $item->update($updates);

            if ($finalizouItem) {
                $item->planilha()->increment('itens_processados');
            }
        });
    }

    private function termoBusca(PlanilhaCotacaoItem $item): string
    {
        return trim((string) ($item->termo_busca ?: $item->descricao));
    }

    /**
     * @param  array<int, array<string, mixed>>  $resultados
     * @return array<int, array<string, mixed>>
     */
    private function resultadosUnicosOrdenados(array $resultados): array
    {
        $vistos = [];
        $unicos = [];

        foreach ($resultados as $resultado) {
            $chave = md5(strtolower(trim(($resultado['site'] ?? '').'|'.($resultado['url'] ?? '').'|'.($resultado['nome'] ?? ''))));

            if (isset($vistos[$chave])) {
                continue;
            }

            $vistos[$chave] = true;
            $unicos[] = $resultado;
        }

        usort($unicos, function (array $a, array $b): int {
            $scoreA = $this->scoreResultado($a);
            $scoreB = $this->scoreResultado($b);
            $precoA = isset($a['preco']) && $a['preco'] !== null ? (float) $a['preco'] : PHP_FLOAT_MAX;
            $precoB = isset($b['preco']) && $b['preco'] !== null ? (float) $b['preco'] : PHP_FLOAT_MAX;
            $faixaA = $this->faixaConfianca($scoreA);
            $faixaB = $this->faixaConfianca($scoreB);

            if ($faixaA !== $faixaB) {
                return $faixaA <=> $faixaB;
            }

            if ($faixaA >= 3) {
                return ($scoreB <=> $scoreA) ?: ($precoA <=> $precoB);
            }

            return ($precoA <=> $precoB) ?: ($scoreB <=> $scoreA);
        });

        return $unicos;
    }

    private function scoreResultado(array $resultado): float
    {
        return max((float) ($resultado['score_meilisearch'] ?? 0), (float) ($resultado['score_produto'] ?? 0));
    }

    private function faixaConfianca(float $score): int
    {
        return match (true) {
            $score >= 0.95 => 0,
            $score >= 0.85 => 1,
            $score >= 0.70 => 2,
            $score > 0.0 => 3,
            default => 4,
        };
    }
}
