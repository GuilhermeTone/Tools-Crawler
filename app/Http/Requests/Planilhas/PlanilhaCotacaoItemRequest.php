<?php

namespace App\Http\Requests\Planilhas;

use App\Models\PlanilhaCotacao;
use App\Models\PlanilhaCotacaoItem;

class PlanilhaCotacaoItemRequest extends PlanilhaCotacaoRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }

        $planilha = $this->route('planilha');
        $item = $this->route('item');

        abort_unless(
            $planilha instanceof PlanilhaCotacao
                && $item instanceof PlanilhaCotacaoItem
                && (int) $item->planilha_cotacao_id === (int) $planilha->id,
            404,
        );

        return true;
    }
}
