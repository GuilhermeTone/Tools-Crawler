<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\BuscarFerramentaRequest;
use App\Http\Requests\Planilhas\AtualizarMargemRequest;
use App\Http\Requests\Planilhas\PlanilhaCotacaoItemRequest;
use App\Http\Requests\Planilhas\PlanilhaCotacaoRequest;
use App\Http\Requests\Planilhas\RefazerBuscaItemRequest;
use App\Http\Requests\Planilhas\SelecionarResultadoRequest;
use App\Models\PlanilhaCotacao;
use App\Models\PlanilhaCotacaoItem;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class PlanilhaCotacaoRequestTest extends TestCase
{
    public function test_autoriza_apenas_o_dono_da_planilha(): void
    {
        $planilha = new PlanilhaCotacao(['user_id' => 10]);

        $requestDoDono = $this->requestComContext(new PlanilhaCotacaoRequest, 10, [
            'planilha' => $planilha,
        ]);
        $requestDeOutroUsuario = $this->requestComContext(new PlanilhaCotacaoRequest, 20, [
            'planilha' => $planilha,
        ]);

        $this->assertTrue($requestDoDono->authorize());
        $this->assertFalse($requestDeOutroUsuario->authorize());
    }

    public function test_rejeita_item_que_nao_pertence_a_planilha_com_404(): void
    {
        $planilha = new PlanilhaCotacao(['user_id' => 10]);
        $planilha->id = 30;

        $item = new PlanilhaCotacaoItem;
        $item->planilha_cotacao_id = 40;

        $request = $this->requestComContext(new PlanilhaCotacaoItemRequest, 10, [
            'planilha' => $planilha,
            'item' => $item,
        ]);

        $this->expectException(NotFoundHttpException::class);

        $request->authorize();
    }

    public function test_requests_de_entrada_expoem_as_regras_das_actions(): void
    {
        $this->assertSame(
            ['required', 'integer', 'min:0'],
            (new SelecionarResultadoRequest)->rules()['resultado_index'],
        );
        $this->assertSame(
            ['required', 'numeric', 'min:0', 'max:999.99'],
            (new AtualizarMargemRequest)->rules()['margem_percentual'],
        );
        $this->assertSame(
            ['required', 'string', 'min:2', 'max:500'],
            (new RefazerBuscaItemRequest)->rules()['termo_busca'],
        );
        $this->assertSame(
            ['required', 'string', 'min:2', 'max:100'],
            (new BuscarFerramentaRequest)->rules()['termo'],
        );
    }

    /**
     * @param  array<string, mixed>  $parametrosRota
     */
    private function requestComContext(FormRequest $request, int $userId, array $parametrosRota): FormRequest
    {
        $usuario = new User;
        $usuario->id = $userId;

        $rota = new class($parametrosRota)
        {
            /**
             * @param  array<string, mixed>  $parametros
             */
            public function __construct(private readonly array $parametros) {}

            public function parameter(string $nome, mixed $padrao = null): mixed
            {
                return $this->parametros[$nome] ?? $padrao;
            }
        };

        $request->setUserResolver(fn () => $usuario);
        $request->setRouteResolver(fn () => $rota);

        return $request;
    }
}
