<?php

namespace App\Services\Planilhas;

use App\Jobs\FinalizarPlanilhaCotacaoJob;
use App\Jobs\ProcessarPlanilhaCotacaoItemLojaJob;
use App\Jobs\ProcessarPlanilhaCotacaoJob;
use App\Models\PlanilhaCotacao;
use App\Models\PlanilhaCotacaoItem;
use App\Services\CrawlerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PlanilhaCotacaoService
{
    public function __construct(
        private readonly XlsxCotacaoService $xlsx,
        private readonly PlanilhaRevalidacaoService $revalidacao,
        private readonly CrawlerService $crawler,
    ) {}

    public function listarDoUsuario(int $userId)
    {
        return PlanilhaCotacao::withCount('itens')
            ->where('user_id', $userId)
            ->latest()
            ->get();
    }

    public function criar(int $userId, string $nome, UploadedFile $arquivo): PlanilhaCotacao
    {
        $path = $arquivo->store('planilhas/originais');

        $planilha = PlanilhaCotacao::create([
            'user_id' => $userId,
            'nome' => trim($nome),
            'nome_arquivo' => $arquivo->getClientOriginalName(),
            'arquivo_original' => $path,
            'status' => 'pendente',
        ]);

        try {
            $itens = $this->xlsx->lerItens(Storage::path($path));

            foreach ($itens as $item) {
                $planilha->itens()->create($item);
            }

            $planilha->update(['total_itens' => count($itens)]);
            ProcessarPlanilhaCotacaoJob::dispatch($planilha->id);
        } catch (\Throwable $e) {
            $planilha->update([
                'status' => 'erro',
                'erro_mensagem' => $e->getMessage(),
            ]);
        }

        return $planilha;
    }

    /**
     * @return array{resumo: array<string, int>, arquivo_processado: string}
     */
    public function revalidar(PlanilhaCotacao $planilha): array
    {
        $this->garantirConcluida(
            $planilha,
            'Aguarde todos os processamentos finalizarem para revalidar.',
        );

        $resumo = $this->revalidacao->revalidarPlanilha($planilha);
        $arquivoProcessado = $this->regenerarArquivoProcessado($planilha);

        $planilha->load('itens');

        return [
            'resumo' => $resumo,
            'arquivo_processado' => $arquivoProcessado,
        ];
    }

    public function prepararDownload(PlanilhaCotacao $planilha): string
    {
        $this->garantirConcluida($planilha, 'A planilha ainda está em processamento.');

        if (! $planilha->arquivo_processado || ! Storage::exists($planilha->arquivo_processado)) {
            throw new NotFoundHttpException;
        }

        $this->revalidacao->revalidarPlanilha($planilha);

        return $this->regenerarArquivoProcessado($planilha);
    }

    public function selecionarResultado(PlanilhaCotacao $planilha, PlanilhaCotacaoItem $item, int $resultadoIndex): PlanilhaCotacaoItem
    {
        $this->garantirConcluida(
            $planilha,
            'Aguarde todos os processamentos finalizarem para selecionar itens.',
        );

        $resultados = $item->resultados ?? [];
        $resultado = $resultados[$resultadoIndex] ?? null;

        if (! $resultado) {
            throw new UnprocessableEntityHttpException('Resultado inválido para este item.');
        }

        $item->update([
            'marca_cotada' => $resultado['marca_detectada'] ?? null,
            'preco_loja' => isset($resultado['preco']) ? (float) $resultado['preco'] : null,
            'preco_revalidado' => null,
            'valor_unitario' => $this->aplicarMargem($resultado['preco'] ?? null, $item->margem_percentual),
            'resultado_escolhido' => array_merge($resultado, [
                'preco_original' => $resultado['preco'] ?? null,
                'selecionado_em' => now()->toIso8601String(),
            ]),
            'revalidacao_status' => 'pendente',
            'revalidado_em' => null,
            'revalidacao_mensagem' => null,
        ]);

        $this->regenerarArquivoProcessado($planilha);
        $planilha->load('itens');

        return $item->fresh() ?? $item;
    }

    public function atualizarMargem(PlanilhaCotacao $planilha, PlanilhaCotacaoItem $item, mixed $margemPercentual): PlanilhaCotacaoItem
    {
        $this->garantirConcluida(
            $planilha,
            'Aguarde todos os processamentos finalizarem para alterar a margem.',
        );

        $resultadoEscolhido = $item->resultado_escolhido;

        $item->update([
            'margem_percentual' => $margemPercentual,
            'valor_unitario' => $resultadoEscolhido
                ? $this->aplicarMargem($item->preco_loja ?? $resultadoEscolhido['preco'] ?? null, $margemPercentual)
                : $item->valor_unitario,
        ]);

        $this->regenerarArquivoProcessado($planilha);
        $planilha->load('itens');

        return $item->fresh() ?? $item;
    }

    public function refazerBuscaItem(PlanilhaCotacao $planilha, PlanilhaCotacaoItem $item, string $termoBusca): void
    {
        $this->garantirConcluida(
            $planilha,
            'Aguarde a planilha finalizar para refazer a busca de um item.',
        );

        $identificadores = $this->crawler->getIdentificadores();
        $totalLojas = count($identificadores);

        $item->update([
            'termo_busca' => trim($termoBusca),
            'status' => 'processando',
            'lojas_total' => $totalLojas,
            'lojas_processadas' => 0,
            'marca_cotada' => null,
            'preco_loja' => null,
            'preco_revalidado' => null,
            'valor_unitario' => null,
            'resultado_escolhido' => null,
            'revalidacao_status' => null,
            'revalidado_em' => null,
            'revalidacao_mensagem' => null,
            'resultados' => [],
            'erro_mensagem' => null,
        ]);

        $itensProcessados = $planilha->itens()
            ->whereKeyNot($item->id)
            ->whereIn('status', ['concluido', 'sem_resultado', 'erro'])
            ->count();

        $planilha->update([
            'status' => 'processando',
            'itens_processados' => $itensProcessados,
            'erro_mensagem' => null,
        ]);

        $this->despacharBuscaDoItem($planilha, $item, $identificadores);
        $planilha->load('itens');
    }

    public function limparResultado(PlanilhaCotacao $planilha, PlanilhaCotacaoItem $item): PlanilhaCotacaoItem
    {
        $this->garantirConcluida(
            $planilha,
            'Aguarde todos os processamentos finalizarem para remover seleções.',
        );

        $item->update([
            'marca_cotada' => null,
            'preco_loja' => null,
            'preco_revalidado' => null,
            'valor_unitario' => null,
            'resultado_escolhido' => null,
            'revalidacao_status' => null,
            'revalidado_em' => null,
            'revalidacao_mensagem' => null,
        ]);

        $this->regenerarArquivoProcessado($planilha);
        $planilha->load('itens');

        return $item->fresh() ?? $item;
    }

    public function excluir(PlanilhaCotacao $planilha): void
    {
        Storage::delete(array_filter([
            $planilha->arquivo_original,
            $planilha->arquivo_processado,
        ]));

        $planilha->delete();
    }

    private function despacharBuscaDoItem(PlanilhaCotacao $planilha, PlanilhaCotacaoItem $item, array $identificadores): void
    {
        $jobs = array_map(
            fn (string $identificador) => new ProcessarPlanilhaCotacaoItemLojaJob($item->id, $identificador),
            $identificadores,
        );

        if (empty($jobs)) {
            FinalizarPlanilhaCotacaoJob::dispatch($planilha->id);

            return;
        }

        $planilhaId = $planilha->id;

        Bus::batch($jobs)
            ->name("Planilha #{$planilha->id} item #{$item->id}")
            ->allowFailures()
            ->then(fn () => FinalizarPlanilhaCotacaoJob::dispatch($planilhaId))
            ->catch(function ($batch, \Throwable $e) use ($planilhaId): void {
                Log::warning("Planilha #{$planilhaId} teve falha parcial ao refazer item: ".$e->getMessage());
                FinalizarPlanilhaCotacaoJob::dispatch($planilhaId);
            })
            ->dispatch();
    }

    private function regenerarArquivoProcessado(PlanilhaCotacao $planilha): string
    {
        $planilhaAtualizada = $planilha->fresh('itens') ?? $planilha->load('itens');
        $arquivoProcessado = $this->xlsx->gerarPlanilhaProcessada($planilhaAtualizada);
        $planilha->update(['arquivo_processado' => $arquivoProcessado]);

        return $arquivoProcessado;
    }

    private function garantirConcluida(PlanilhaCotacao $planilha, string $mensagem): void
    {
        if ($planilha->status !== 'concluido') {
            throw new ConflictHttpException($mensagem);
        }
    }

    private function aplicarMargem(mixed $preco, mixed $margemPercentual): ?float
    {
        if ($preco === null || $preco === '') {
            return null;
        }

        $preco = (float) $preco;
        $margemPercentual = (float) ($margemPercentual ?? 0);

        return round($preco * (1 + ($margemPercentual / 100)), 2);
    }
}
