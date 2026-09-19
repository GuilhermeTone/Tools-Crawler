<?php

namespace App\Services\Planilhas;

use App\Models\PlanilhaCotacao;
use App\Models\PlanilhaCotacaoItem;
use App\Services\ProductEnrichmentService;

class PlanilhaCotacaoPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function planilhaInicial(PlanilhaCotacao $planilha): array
    {
        $planilha->loadMissing('itens');

        return [
            'statusUrl' => route('planilhas.status', $planilha),
            'revalidarUrl' => route('planilhas.revalidar', $planilha),
            'downloadUrl' => $this->downloadUrl($planilha),
            'status' => $planilha->status,
            'total' => $planilha->total_itens,
            'processados' => $planilha->itens_processados,
            'erro' => $planilha->erro_mensagem,
            'itens' => $this->mapearItens($planilha),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cotacaoFechada(PlanilhaCotacao $planilha): array
    {
        $planilha->loadMissing('itens');

        $itens = $planilha->itens;
        $selecionados = $itens
            ->filter(fn (PlanilhaCotacaoItem $item): bool => ! empty($item->resultado_escolhido))
            ->map(fn (PlanilhaCotacaoItem $item): array => $this->mapearItemFechado($item))
            ->values();

        $lojas = $selecionados
            ->groupBy('loja_nome')
            ->map(function ($itensLoja, string $lojaNome): array {
                return [
                    'nome' => $lojaNome,
                    'itens' => $itensLoja->values(),
                    'quantidade_itens' => $itensLoja->count(),
                    'subtotal_compra' => round($itensLoja->sum('total_compra'), 2),
                    'subtotal_planilha' => round($itensLoja->sum('total_planilha'), 2),
                ];
            })
            ->sortBy('nome')
            ->values();

        return [
            'lojas' => $lojas,
            'resumo' => [
                'total_itens' => $itens->count(),
                'selecionados' => $selecionados->count(),
                'lojas' => $lojas->count(),
                'total_compra' => round($selecionados->sum('total_compra'), 2),
                'total_planilha' => round($selecionados->sum('total_planilha'), 2),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function statusPayload(PlanilhaCotacao $planilha): array
    {
        $planilha->loadMissing('itens');

        return [
            'status' => $planilha->status,
            'total_itens' => $planilha->total_itens,
            'itens_processados' => $planilha->itens_processados,
            'erro_mensagem' => $planilha->erro_mensagem,
            'revalidar_url' => route('planilhas.revalidar', $planilha),
            'download_url' => $this->downloadUrl($planilha),
            'itens' => $this->mapearItens($planilha),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function revalidacaoPayload(PlanilhaCotacao $planilha, array $resumo): array
    {
        $planilha->loadMissing('itens');

        return [
            'ok' => true,
            'resumo' => $resumo,
            'download_url' => route('planilhas.download', $planilha),
            'itens' => $this->mapearItens($planilha),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function itemAtualizadoPayload(PlanilhaCotacao $planilha, PlanilhaCotacaoItem $item): array
    {
        $planilha->loadMissing('itens');

        return [
            'ok' => true,
            'download_url' => route('planilhas.download', $planilha),
            'item' => $this->mapearItem($item),
            'itens' => $this->mapearItens($planilha),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function refazerBuscaPayload(PlanilhaCotacao $planilha): array
    {
        $planilha->loadMissing('itens');

        return [
            'ok' => true,
            'status' => $planilha->status,
            'total_itens' => $planilha->total_itens,
            'itens_processados' => $planilha->itens_processados,
            'erro_mensagem' => $planilha->erro_mensagem,
            'download_url' => null,
            'itens' => $this->mapearItens($planilha),
        ];
    }

    public function nomeDownload(PlanilhaCotacao $planilha): string
    {
        $nomeBase = $planilha->nome ?: pathinfo($planilha->nome_arquivo, PATHINFO_FILENAME);

        return (str($nomeBase)->slug()->toString() ?: 'planilha').'-cotada.xlsx';
    }

    public function downloadUrl(PlanilhaCotacao $planilha): ?string
    {
        if ($planilha->status !== 'concluido' || ! $planilha->arquivo_processado) {
            return null;
        }

        return route('planilhas.download', $planilha);
    }

    private function mapearItens(PlanilhaCotacao $planilha)
    {
        return $planilha->itens->map(fn ($item): array => $this->mapearItem($item))->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapearItem(PlanilhaCotacaoItem $item): array
    {
        return [
            'id' => $item->id,
            'linha' => $item->linha,
            'descricao' => $item->descricao,
            'termo_busca' => $item->termo_busca,
            'termo_busca_efetivo' => $item->termo_busca ?: $item->descricao,
            'codigos_busca' => ProductEnrichmentService::extrairCodigosDaBusca($item->termo_busca ?: $item->descricao),
            'quantidade' => $item->quantidade,
            'status' => $item->status,
            'lojas_total' => (int) ($item->lojas_total ?? 0),
            'lojas_processadas' => (int) ($item->lojas_processadas ?? 0),
            'marca_cotada' => $item->marca_cotada,
            'preco_loja' => $item->preco_loja !== null ? (float) $item->preco_loja : null,
            'preco_revalidado' => $item->preco_revalidado !== null ? (float) $item->preco_revalidado : null,
            'valor_unitario' => $item->valor_unitario !== null ? (float) $item->valor_unitario : null,
            'margem_percentual' => (float) ($item->margem_percentual ?? 0),
            'resultados' => $item->resultados ?? [],
            'resultado_escolhido' => $item->resultado_escolhido,
            'revalidacao_status' => $item->revalidacao_status,
            'revalidado_em' => $item->revalidado_em?->toIso8601String(),
            'revalidacao_mensagem' => $item->revalidacao_mensagem,
            'erro_mensagem' => $item->erro_mensagem,
            'selecionar_url' => route('planilhas.itens.selecionar', [$item->planilha_cotacao_id, $item->id]),
            'limpar_url' => route('planilhas.itens.limpar', [$item->planilha_cotacao_id, $item->id]),
            'margem_url' => route('planilhas.itens.margem', [$item->planilha_cotacao_id, $item->id]),
            'refazer_busca_url' => route('planilhas.itens.refazer-busca', [$item->planilha_cotacao_id, $item->id]),
            'revalidar_url' => route('planilhas.revalidar', $item->planilha_cotacao_id),
            'aberto' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapearItemFechado(PlanilhaCotacaoItem $item): array
    {
        $resultado = $item->resultado_escolhido ?? [];
        $quantidade = max(1.0, (float) ($item->quantidade ?? 1));
        $precoCompra = $item->preco_revalidado ?? $item->preco_loja ?? ($resultado['preco'] ?? null);
        $valorPlanilha = $item->valor_unitario ?? $precoCompra;
        $lojaNome = $resultado['nome_site'] ?? $resultado['site'] ?? 'Loja sem nome';

        return [
            'linha' => $item->linha,
            'descricao' => $item->descricao,
            'quantidade' => $quantidade,
            'unidade' => $item->unidade,
            'produto' => $resultado['nome'] ?? 'Produto selecionado',
            'loja_nome' => $lojaNome,
            'site' => $resultado['site'] ?? null,
            'marca' => $item->marca_cotada ?? $resultado['marca_detectada'] ?? null,
            'preco_compra' => $precoCompra !== null ? (float) $precoCompra : null,
            'valor_planilha' => $valorPlanilha !== null ? (float) $valorPlanilha : null,
            'total_compra' => $precoCompra !== null ? round((float) $precoCompra * $quantidade, 2) : 0.0,
            'total_planilha' => $valorPlanilha !== null ? round((float) $valorPlanilha * $quantidade, 2) : 0.0,
            'margem_percentual' => (float) ($item->margem_percentual ?? 0),
            'url' => $resultado['url'] ?? null,
            'imagem' => $resultado['imagem'] ?? null,
            'revalidacao_status' => $item->revalidacao_status,
            'revalidacao_mensagem' => $item->revalidacao_mensagem,
            'revalidado_em' => $item->revalidado_em,
        ];
    }
}
