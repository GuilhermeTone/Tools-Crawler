<?php

namespace App\Http\Controllers;

use App\Http\Requests\Planilhas\AtualizarMargemRequest;
use App\Http\Requests\Planilhas\PlanilhaCotacaoItemRequest;
use App\Http\Requests\Planilhas\PlanilhaCotacaoRequest;
use App\Http\Requests\Planilhas\RefazerBuscaItemRequest;
use App\Http\Requests\Planilhas\SelecionarResultadoRequest;
use App\Http\Requests\Planilhas\StorePlanilhaCotacaoRequest;
use App\Models\PlanilhaCotacao;
use App\Models\PlanilhaCotacaoItem;
use App\Services\Planilhas\PlanilhaCotacaoPresenter;
use App\Services\Planilhas\PlanilhaCotacaoService;
use Illuminate\Support\Facades\Storage;

class PlanilhaCotacaoController extends Controller
{
    public function __construct(
        private readonly PlanilhaCotacaoService $planilhas,
        private readonly PlanilhaCotacaoPresenter $presenter,
    ) {}

    public function index()
    {
        $planilhas = $this->planilhas->listarDoUsuario(auth()->id());

        return view('planilhas.index', compact('planilhas'));
    }

    public function store(StorePlanilhaCotacaoRequest $request)
    {
        $data = $request->validated();

        $planilha = $this->planilhas->criar($request->user()->id, $data['nome'], $data['planilha']);

        return redirect()->route('planilhas.show', $planilha);
    }

    public function show(PlanilhaCotacaoRequest $request, PlanilhaCotacao $planilha)
    {
        $planilhaInicial = $this->presenter->planilhaInicial($planilha);

        return view('planilhas.show', compact('planilha', 'planilhaInicial'));
    }

    public function cotacaoFechada(PlanilhaCotacaoRequest $request, PlanilhaCotacao $planilha)
    {
        ['lojas' => $lojas, 'resumo' => $resumo] = $this->presenter->cotacaoFechada($planilha);

        return view('planilhas.cotacao-fechada', compact('planilha', 'lojas', 'resumo'));
    }

    public function status(PlanilhaCotacaoRequest $request, PlanilhaCotacao $planilha)
    {
        return response()->json($this->presenter->statusPayload($planilha));
    }

    public function download(PlanilhaCotacaoRequest $request, PlanilhaCotacao $planilha)
    {
        $arquivoProcessado = $this->planilhas->prepararDownload($planilha);

        return Storage::download($arquivoProcessado, $this->presenter->nomeDownload($planilha));
    }

    public function revalidar(PlanilhaCotacaoRequest $request, PlanilhaCotacao $planilha)
    {
        $resultado = $this->planilhas->revalidar($planilha);

        return response()->json($this->presenter->revalidacaoPayload($planilha, $resultado['resumo']));
    }

    public function selecionarResultado(SelecionarResultadoRequest $request, PlanilhaCotacao $planilha, PlanilhaCotacaoItem $item)
    {
        $data = $request->validated();
        $item = $this->planilhas->selecionarResultado($planilha, $item, $data['resultado_index']);

        return response()->json($this->presenter->itemAtualizadoPayload($planilha, $item));
    }

    public function atualizarMargem(AtualizarMargemRequest $request, PlanilhaCotacao $planilha, PlanilhaCotacaoItem $item)
    {
        $data = $request->validated();

        $item = $this->planilhas->atualizarMargem($planilha, $item, $data['margem_percentual']);

        return response()->json($this->presenter->itemAtualizadoPayload($planilha, $item));
    }

    public function refazerBuscaItem(RefazerBuscaItemRequest $request, PlanilhaCotacao $planilha, PlanilhaCotacaoItem $item)
    {
        $data = $request->validated();

        $this->planilhas->refazerBuscaItem($planilha, $item, $data['termo_busca']);

        return response()->json($this->presenter->refazerBuscaPayload($planilha));
    }

    public function limparResultado(PlanilhaCotacaoItemRequest $request, PlanilhaCotacao $planilha, PlanilhaCotacaoItem $item)
    {
        $item = $this->planilhas->limparResultado($planilha, $item);

        return response()->json($this->presenter->itemAtualizadoPayload($planilha, $item));
    }

    public function destroy(PlanilhaCotacaoRequest $request, PlanilhaCotacao $planilha)
    {
        $this->planilhas->excluir($planilha);

        return redirect()->route('planilhas.index');
    }
}
