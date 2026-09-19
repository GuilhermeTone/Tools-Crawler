<?php

namespace App\Services;

use App\Models\PlanilhaCotacao;
use App\Models\PlanilhaCotacaoItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * @return array{stats: array<string, mixed>, statusItens: array<string, int>, planilhasRecentes: mixed}
     */
    public function dados(int $userId): array
    {
        $hoje = Carbon::today();
        $desde30Dias = Carbon::now()->subDays(30);

        $planilhasBase = PlanilhaCotacao::where('user_id', $userId);
        $itensBase = PlanilhaCotacaoItem::whereHas('planilha', fn ($query) => $query->where('user_id', $userId));

        $stats = [
            'planilhas_total' => (clone $planilhasBase)->count(),
            'planilhas_hoje' => (clone $planilhasBase)->where('created_at', '>=', $hoje)->count(),
            'planilhas_processando' => (clone $planilhasBase)->whereIn('status', ['pendente', 'processando'])->count(),
            'planilhas_concluidas' => (clone $planilhasBase)->where('status', 'concluido')->count(),
            'itens_total' => (clone $itensBase)->count(),
            'itens_sem_resultado' => (clone $itensBase)->where('status', 'sem_resultado')->count(),
            'itens_com_resultado' => (clone $itensBase)->where('status', 'concluido')->count(),
        ];

        $stats['taxa_com_resultado'] = $stats['itens_total'] > 0
            ? round(($stats['itens_com_resultado'] / $stats['itens_total']) * 100, 1)
            : 0;

        $stats['tempo_medio_processamento'] = $this->tempoMedioProcessamento($userId, $desde30Dias);

        return [
            'stats' => $stats,
            'statusItens' => (clone $itensBase)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all(),
            'planilhasRecentes' => PlanilhaCotacao::withCount('itens')
                ->where('user_id', $userId)
                ->latest()
                ->limit(6)
                ->get(),
        ];
    }

    private function tempoMedioProcessamento(int $userId, Carbon $desde): ?float
    {
        $segundos = PlanilhaCotacao::where('user_id', $userId)
            ->where('status', 'concluido')
            ->where('created_at', '>=', $desde)
            ->selectRaw('avg(timestampdiff(second, created_at, updated_at)) as media')
            ->value('media');

        return $segundos !== null ? round((float) $segundos / 60, 1) : null;
    }
}
