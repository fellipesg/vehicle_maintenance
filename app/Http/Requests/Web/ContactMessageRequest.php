<?php

namespace App\Http\Requests\Web;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ContactMessageRequest extends FormRequest
{
    public const SUBJECTS = [
        'question' => 'Dúvida',
        'support' => 'Suporte',
        'partnership' => 'Oficina ou lojista',
        'privacy' => 'Privacidade e dados pessoais (LGPD)',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['required', 'in:'.implode(',', array_keys(self::SUBJECTS))],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['nullable', 'string'],
        ];

        if (filled(config('services.turnstile.secret_key'))) {
            $rules['cf-turnstile-response'] = ['required', 'string', $this->passesTurnstile(...)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cf-turnstile-response.required' => 'Confirme que você não é um robô.',
        ];
    }

    private function passesTurnstile(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $value,
                    'remoteip' => $this->ip(),
                ]);
        } catch (ConnectionException $exception) {
            report($exception);
            $fail('Não foi possível confirmar que você não é um robô. Tente novamente.');

            return;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            $fail('Não foi possível confirmar que você não é um robô. Tente novamente.');
        }
    }
}
