<?php

namespace App\Http\Requests\Planilhas;

class SelecionarResultadoRequest extends PlanilhaCotacaoItemRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resultado_index' => ['required', 'integer', 'min:0'],
        ];
    }
}
