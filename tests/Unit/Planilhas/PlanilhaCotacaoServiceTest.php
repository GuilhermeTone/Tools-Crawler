<?php

namespace Tests\Unit\Planilhas;

use App\Models\PlanilhaCotacao;
use App\Models\PlanilhaCotacaoItem;
use App\Services\CrawlerService;
use App\Services\Planilhas\PlanilhaCotacaoService;
use App\Services\Planilhas\PlanilhaRevalidacaoService;
use App\Services\Planilhas\XlsxCotacaoService;
use Mockery;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Tests\TestCase;

class PlanilhaCotacaoServiceTest extends TestCase
{
    public function test_revalidacao_exige_planilha_concluida(): void
    {
        $planilha = new PlanilhaCotacao(['status' => 'processando']);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Aguarde todos os processamentos finalizarem para revalidar.');

        $this->service()->revalidar($planilha);
    }

    public function test_selecao_rejeita_indice_sem_resultado_com_422(): void
    {
        $planilha = new PlanilhaCotacao(['status' => 'concluido']);
        $item = new PlanilhaCotacaoItem(['resultados' => []]);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Resultado inválido para este item.');

        $this->service()->selecionarResultado($planilha, $item, 0);
    }

    private function service(): PlanilhaCotacaoService
    {
        return new PlanilhaCotacaoService(
            Mockery::mock(XlsxCotacaoService::class),
            Mockery::mock(PlanilhaRevalidacaoService::class),
            Mockery::mock(CrawlerService::class),
        );
    }
}
