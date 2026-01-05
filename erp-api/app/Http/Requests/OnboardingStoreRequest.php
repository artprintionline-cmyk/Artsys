<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_nome' => 'required|string|min:2|max:120',
            'empresa_email' => 'required|email|max:190',
            'empresa_telefone' => 'nullable|string|max:40',
            'admin_nome' => 'required|string|min:2|max:120',
            'admin_email' => 'required|email|max:190',
            'admin_password' => 'required|string|min:6|max:190',
        ];
    }
}
