<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VisualizarCrawlerSaudeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
