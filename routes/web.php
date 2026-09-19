<?php

use App\Http\Controllers\AssinaturaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FerramentaController;
use App\Http\Controllers\PlanilhaCotacaoController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('planilhas.index');
});

// Rotas do Breeze (perfil)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Assinatura (autenticado, mas sem exigir assinatura ativa)
Route::middleware('auth')->prefix('assinatura')->name('assinatura.')->group(function () {
    Route::get('/', [AssinaturaController::class, 'index'])->name('index');
    Route::post('/checkout', [AssinaturaController::class, 'checkout'])->name('checkout');
    Route::get('/sucesso', [AssinaturaController::class, 'sucesso'])->name('sucesso');
    Route::post('/portal', [AssinaturaController::class, 'portal'])->name('portal');
});

// Rotas da aplicação (requerem autenticação + assinatura ativa)
Route::middleware(['auth', 'subscribed'])->group(function () {
    Route::redirect('/ferramentas', '/buscas-especificas');
    Route::redirect('/orcamentos', '/planilhas');
    Route::redirect('/orcamentos/{any}', '/planilhas')->where('any', '.*');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    Route::prefix('buscas-especificas')->name('ferramentas.')->group(function () {
        Route::get('/', [FerramentaController::class, 'index'])->name('index');
        Route::post('/buscar', [FerramentaController::class, 'buscar'])->name('buscar');
        Route::get('/{id}/status', [FerramentaController::class, 'status'])->name('status');
        Route::delete('/{id}', [FerramentaController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('planilhas')->name('planilhas.')->group(function () {
        Route::get('/', [PlanilhaCotacaoController::class, 'index'])->name('index');
        Route::post('/', [PlanilhaCotacaoController::class, 'store'])->name('store');
        Route::get('/{planilha}', [PlanilhaCotacaoController::class, 'show'])->name('show');
        Route::get('/{planilha}/cotacao-fechada', [PlanilhaCotacaoController::class, 'cotacaoFechada'])->name('cotacao-fechada');
        Route::get('/{planilha}/status', [PlanilhaCotacaoController::class, 'status'])->name('status');
        Route::get('/{planilha}/download', [PlanilhaCotacaoController::class, 'download'])->name('download');
        Route::post('/{planilha}/revalidar', [PlanilhaCotacaoController::class, 'revalidar'])->name('revalidar');
        Route::post('/{planilha}/itens/{item}/selecionar', [PlanilhaCotacaoController::class, 'selecionarResultado'])->name('itens.selecionar');
        Route::post('/{planilha}/itens/{item}/refazer-busca', [PlanilhaCotacaoController::class, 'refazerBuscaItem'])->name('itens.refazer-busca');
        Route::patch('/{planilha}/itens/{item}/margem', [PlanilhaCotacaoController::class, 'atualizarMargem'])->name('itens.margem');
        Route::delete('/{planilha}/itens/{item}/selecionar', [PlanilhaCotacaoController::class, 'limparResultado'])->name('itens.limpar');
        Route::delete('/{planilha}', [PlanilhaCotacaoController::class, 'destroy'])->name('destroy');
    });
});

require __DIR__.'/auth.php';
