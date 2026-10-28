<?php

namespace App\Http\Requests\Web;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StoreContactRequest extends FormRequest
{
    private const VERIFICATION_FAILED_MESSAGE = 'Não foi possível confirmar que você não é um robô. Tente novamente.';

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'in:'.implode(',', array_keys(self::SUBJECTS))],
            'message' => ['required', 'string', 'max:4000'],
            'website' => ['nullable', 'string', 'max:255'],
            'ref' => ['nullable', 'string', 'max:64'],
        ];

        if ($this->turnstileIsRequired()) {
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

    /**
     * Em produção o captcha é sempre exigido, mesmo sem TURNSTILE_SECRET_KEY: faltar a variável
     * passa a recusar o envio (passesTurnstile), em vez de desligar a proteção em silêncio junto
     * com ela. Fora de produção, sem a chave, o captcha continua desligado — honeypot e throttle
     * seguem valendo, e nenhum ambiente local precisa de conta na Cloudflare.
     */
    private function turnstileIsRequired(): bool
    {
        return App::isProduction() || filled(config('services.turnstile.secret_key'));
    }

    private function passesTurnstile(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = (string) config('services.turnstile.secret_key');

        // Só cai aqui em produção, por turnstileIsRequired(). Recusar e reportar é o lado seguro:
        // um formulário fechado aparece no suporte em minutos, um formulário aberto não aparece.
        if ($secret === '') {
            report(new RuntimeException(
                'TURNSTILE_SECRET_KEY não está definida: o captcha do /contato recusa todo envio até a chave voltar.'
            ));
            $fail(self::VERIFICATION_FAILED_MESSAGE);

            return;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => $value,
                    'remoteip' => $this->ip(),
                ]);
        } catch (ConnectionException $exception) {
            report($exception);
            $fail(self::VERIFICATION_FAILED_MESSAGE);

            return;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            $fail(self::VERIFICATION_FAILED_MESSAGE);
        }
    }
}
