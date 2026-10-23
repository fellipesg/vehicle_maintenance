<?php

namespace App\Http\Requests\Web\Account;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Preferências do proprietário em "Minha conta": hoje só se o PDF do histórico leva as fotos das OS
 * (users.export_include_photos, o mesmo campo do PUT /api/v1/me).
 */
class UpdateAccountPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isUser();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'export_include_photos' => ['required', 'boolean'],
        ];
    }
}
