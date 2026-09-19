<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuscarFerramentaRequest;
use App\Services\FerramentaBuscaService;

class FerramentaController extends Controller
{
    public function __construct(private readonly FerramentaBuscaService $buscas) {}

    public function index()
    {
        $marcasTrabalhadas = $this->buscas->marcasTrabalhadas();
        $buscasRecentes = $this->buscas->recentesDoUsuario(auth()->id());
        $buscasJson = $this->buscas->mapearBuscas($buscasRecentes);

        return view('ferramentas.index', compact('buscasRecentes', 'buscasJson', 'marcasTrabalhadas'));
    }

    public function buscar(BuscarFerramentaRequest $request)
    {
        $data = $request->validated();

        $busca = $this->buscas->criar($request->user()->id, $data['termo']);

        return response()->json([
            'busca_id' => $busca->id,
            'status' => $busca->status,
            'message' => 'Busca iniciada',
        ]);
    }

    public function status(int $id)
    {
        $busca = $this->buscas->buscarDoUsuario(auth()->id(), $id);

        return response()->json($this->buscas->statusPayload($busca));
    }

    public function destroy(int $id)
    {
        $this->buscas->excluir(auth()->id(), $id);

        return response()->json(['ok' => true]);
    }
}
