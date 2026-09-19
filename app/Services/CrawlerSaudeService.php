<?php

namespace App\Services;

use App\Models\CrawlerExecucao;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CrawlerSaudeService
{
    public function __construct(private readonly CrawlerService $crawler) {}

    /**
     * @return array{lojas: mixed, resumo: array<string, int>, ultimosErros: mixed}
     */
    public function dados(): array
    {
        $desde24h = Carbon::now()->subDay();
        $desde7d = Carbon::now()->subDays(7);

        $execucoes24h = CrawlerExecucao::where('created_at', '>=', $desde24h)
            ->select(
                'loja_id',
                DB::raw('max(loja_nome) as loja_nome'),
                DB::raw('count(*) as total'),
                DB::raw("sum(case when status = 'ok' then 1 else 0 end) as ok_total"),
                DB::raw("sum(case when status = 'erro' then 1 else 0 end) as erros_total"),
                DB::raw("sum(case when status = 'sem_resultado' then 1 else 0 end) as sem_resultado_total"),
                DB::raw('avg(duracao_ms) as duracao_media_ms'),
                DB::raw('max(created_at) as ultima_execucao')
            )
            ->groupBy('loja_id')
            ->get()
            ->keyBy('loja_id');

        $execucoes7d = CrawlerExecucao::where('created_at', '>=', $desde7d)
            ->select(
                'loja_id',
                DB::raw('count(*) as total_7d'),
                DB::raw("sum(case when status = 'erro' then 1 else 0 end) as erros_7d")
            )
            ->groupBy('loja_id')
            ->get()
            ->keyBy('loja_id');

        $ultimosErros = CrawlerExecucao::where('status', 'erro')
            ->latest()
            ->limit(12)
            ->get();

        $lojas = collect($this->crawler->getListaLojas())
            ->map(function (array $loja) use ($execucoes24h, $execucoes7d): array {
                $dados24h = $execucoes24h->get($loja['id']);
                $dados7d = $execucoes7d->get($loja['id']);
                $total24h = (int) ($dados24h?->total ?? 0);
                $erros24h = (int) ($dados24h?->erros_total ?? 0);
                $semResultado24h = (int) ($dados24h?->sem_resultado_total ?? 0);
                $ok24h = (int) ($dados24h?->ok_total ?? 0);

                return [
                    'id' => $loja['id'],
                    'nome' => $dados24h?->loja_nome ?: $loja['nome'],
                    'status' => $this->statusLoja($total24h, $erros24h, $semResultado24h),
                    'total_24h' => $total24h,
                    'ok_24h' => $ok24h,
                    'erros_24h' => $erros24h,
                    'sem_resultado_24h' => $semResultado24h,
                    'taxa_erro_24h' => $total24h > 0 ? round(($erros24h / $total24h) * 100, 1) : null,
                    'duracao_media_ms' => $dados24h?->duracao_media_ms ? (int) round((float) $dados24h->duracao_media_ms) : null,
                    'ultima_execucao' => $dados24h?->ultima_execucao,
                    'total_7d' => (int) ($dados7d?->total_7d ?? 0),
                    'erros_7d' => (int) ($dados7d?->erros_7d ?? 0),
                ];
            })
            ->sortBy(fn (array $loja): string => match ($loja['status']) {
                'critico' => '0'.$loja['nome'],
                'atencao' => '1'.$loja['nome'],
                'sem_dados' => '2'.$loja['nome'],
                default => '3'.$loja['nome'],
            })
            ->values();

        return [
            'lojas' => $lojas,
            'resumo' => [
                'lojas_total' => $lojas->count(),
                'saudaveis' => $lojas->where('status', 'saudavel')->count(),
                'atencao' => $lojas->where('status', 'atencao')->count(),
                'criticas' => $lojas->where('status', 'critico')->count(),
                'sem_dados' => $lojas->where('status', 'sem_dados')->count(),
            ],
            'ultimosErros' => $ultimosErros,
        ];
    }

    private function statusLoja(int $total, int $erros, int $semResultado): string
    {
        if ($total === 0) {
            return 'sem_dados';
        }

        $taxaErro = $erros / $total;
        $taxaSemResultado = $semResultado / $total;

        if ($taxaErro >= 0.40 || $erros >= 8) {
            return 'critico';
        }

        if ($taxaErro >= 0.15 || $taxaSemResultado >= 0.70) {
            return 'atencao';
        }

        return 'saudavel';
    }
}
