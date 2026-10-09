<?php

namespace App\Mail;

use App\Enums\RegistrationSource;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Boas-vindas de toda conta nova. O botão depende de por onde a pessoa entrou:
 *
 * - web: já tem senha e está logada, então o botão leva ao primeiro passo (adicionar o veículo);
 * - app com e-mail e senha: entrar pelo navegador com os mesmos dados;
 * - app com Google ou Facebook (OAuth): a conta tem uma senha aleatória que a pessoa não conhece,
 *   então o botão leva a "Esqueci minha senha" para ela definir uma e poder usar o navegador.
 */
class WelcomeUserMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Tema de resources/views/vendor/mail/html/themes/revisalog.css.
     *
     * @var string
     */
    public $theme = 'revisalog';

    public function __construct(
        public User $user,
        public RegistrationSource $source,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@revisalog.com.br', 'RevisaLog'),
            replyTo: [
                new Address(
                    (string) config('mail.reply_to.address'),
                    'RevisaLog',
                ),
            ],
            subject: 'Bem-vindo à RevisaLog',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: $this->isWorkshop() ? 'emails.welcome-workshop' : 'emails.welcome-user',
            with: [
                'firstName' => $this->firstName(),
                'actionUrl' => $this->actionUrl(),
                'actionLabel' => $this->actionLabel(),
                'needsPassword' => $this->needsPassword(),
                'createdInApp' => $this->source !== RegistrationSource::Web,
                'email' => $this->user->email,
            ],
        );
    }

    public function isWorkshop(): bool
    {
        return $this->user->user_type === 'workshop';
    }

    public function firstName(): string
    {
        $firstName = trim((string) Str::of($this->user->name)->explode(' ')->first());

        return $firstName !== '' ? $firstName : ($this->isWorkshop() ? 'equipe' : 'motorista');
    }

    /**
     * Conta criada com Google ou Facebook: sem senha conhecida, o login do navegador não serve.
     */
    public function needsPassword(): bool
    {
        return $this->source === RegistrationSource::Oauth;
    }

    public function actionUrl(): string
    {
        if ($this->isWorkshop() && $this->source === RegistrationSource::Web) {
            return route('workshop.dashboard');
        }

        return match ($this->source) {
            RegistrationSource::Web => route('user.vehicles.create'),
            RegistrationSource::Api => route('login.usuario'),
            RegistrationSource::Oauth => route('password.request', ['portal' => 'usuario']),
        };
    }

    public function actionLabel(): string
    {
        if ($this->isWorkshop() && $this->source === RegistrationSource::Web) {
            return 'Ir para o Início';
        }

        return match ($this->source) {
            RegistrationSource::Web => 'Adicionar meu veículo',
            RegistrationSource::Api => 'Entrar pelo navegador',
            RegistrationSource::Oauth => 'Definir senha para o navegador',
        };
    }
}
