<?php

namespace App\Http\Requests\Planilhas;

class RefazerBuscaItemRequest extends PlanilhaCotacaoItemRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'termo_busca' => ['required', 'string', 'min:2', 'max:500'],
        ];
    }
}
