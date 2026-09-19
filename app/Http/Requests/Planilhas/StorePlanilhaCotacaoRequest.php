<?php

namespace App\Http\Requests\Planilhas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanilhaCotacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('planilha_cotacoes', 'nome')
                    ->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'planilha' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ];
    }
}
