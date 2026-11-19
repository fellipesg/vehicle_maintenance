<?php

namespace App\Http\Requests\Web;

use App\Models\BlogComment;
use Illuminate\Foundation\Http\FormRequest;

class StoreBlogCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => trim($this->input('body'))]);
        }
    }

    /**
     * Erro de validação volta para a seção de comentários, não para o topo do artigo.
     */
    protected function getRedirectUrl(): string
    {
        return route('blog.show', $this->route('post')).'#comentarios';
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:'.BlogComment::MIN_LENGTH, 'max:'.BlogComment::MAX_LENGTH],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['body' => 'comentário'];
    }
}
