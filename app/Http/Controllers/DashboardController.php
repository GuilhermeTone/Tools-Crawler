<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index()
    {
        [
            'stats' => $stats,
            'statusItens' => $statusItens,
            'planilhasRecentes' => $planilhasRecentes,
        ] = $this->dashboard->dados(auth()->id());

        return view('dashboard.index', compact('stats', 'statusItens', 'planilhasRecentes'));
    }
}
