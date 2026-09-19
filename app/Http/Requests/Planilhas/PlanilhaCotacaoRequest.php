<?php

namespace App\Http\Requests\Planilhas;

use App\Models\PlanilhaCotacao;
use Illuminate\Foundation\Http\FormRequest;

class PlanilhaCotacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $planilha = $this->route('planilha');

        return $planilha instanceof PlanilhaCotacao
            && (int) $planilha->user_id === (int) $this->user()?->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
