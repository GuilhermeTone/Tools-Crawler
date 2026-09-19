<?php

namespace App\Http\Controllers;

use App\Http\Requests\VisualizarCrawlerSaudeRequest;
use App\Services\CrawlerSaudeService;

class CrawlerSaudeController extends Controller
{
    public function __construct(private readonly CrawlerSaudeService $crawlerSaude) {}

    public function index(VisualizarCrawlerSaudeRequest $request)
    {
        [
            'lojas' => $lojas,
            'resumo' => $resumo,
            'ultimosErros' => $ultimosErros,
        ] = $this->crawlerSaude->dados();

        return view('crawlers.saude', compact('lojas', 'resumo', 'ultimosErros'));
    }
}
