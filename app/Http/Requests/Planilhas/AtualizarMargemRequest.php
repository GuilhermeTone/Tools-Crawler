<?php

namespace App\Http\Requests\Planilhas;

class AtualizarMargemRequest extends PlanilhaCotacaoItemRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'margem_percentual' => ['required', 'numeric', 'min:0', 'max:999.99'],
        ];
    }
}
