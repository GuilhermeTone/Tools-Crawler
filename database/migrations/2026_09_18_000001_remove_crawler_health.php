<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('crawler_execucoes');

        if (Schema::hasColumn('users', 'tipo')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('tipo');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'tipo')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('tipo', 30)->default('usuario')->after('password');
            });
        }

        if (! Schema::hasTable('crawler_execucoes')) {
            Schema::create('crawler_execucoes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('planilha_cotacao_id')->nullable()->constrained('planilha_cotacoes')->cascadeOnDelete();
                $table->foreignId('planilha_cotacao_item_id')->nullable()->constrained('planilha_cotacao_itens')->cascadeOnDelete();
                $table->string('loja_id', 80);
                $table->string('loja_nome')->nullable();
                $table->text('termo')->nullable();
                $table->enum('status', ['ok', 'sem_resultado', 'erro'])->default('ok');
                $table->unsignedInteger('resultados_count')->default(0);
                $table->unsignedInteger('duracao_ms')->nullable();
                $table->text('erro_mensagem')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
                $table->index(['loja_id', 'created_at']);
                $table->index(['status', 'created_at']);
            });
        }
    }
};
