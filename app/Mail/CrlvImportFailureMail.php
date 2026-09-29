<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Aviso interno ao suporte quando um CRLV-e não é lido (App\Services\Crlv\CrlvImportFailureReporter).
 * O contexto vira uma tabela com rótulos em pt-BR.
 */
class CrlvImportFailureMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Chave do contexto => rótulo da tabela. Chave nova sem rótulo aqui aparece com a primeira letra
     * maiúscula e sem o "_".
     *
     * @var array<string, string>
     */
    public const CONTEXT_LABELS = [
        'origem' => 'Origem',
        'arquivo' => 'Arquivo',
        'tamanho_kb' => 'Tamanho (KB)',
        'usuario_id' => 'ID do usuário',
        'usuario_email' => 'E-mail do usuário',
    ];

    /**
     * Tema de resources/views/vendor/mail/html/themes/revisalog.css.
     *
     * @var string
     */
    public $theme = 'revisalog';

    // Falha do provedor vira nova tentativa, não aviso perdido.
    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $reason,
        public array $context = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'CRLV-e não lido — '.$this->reason,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.crlv-import-failure',
            with: [
                'contextRows' => $this->contextRows(),
            ],
        );
    }

    /**
     * Linhas da tabela, na ordem do contexto, sem os valores vazios. O "|" do valor é trocado por
     * "/" para não quebrar a tabela em markdown.
     *
     * @return list<array{label: string, value: string}>
     */
    public function contextRows(): array
    {
        $rows = [];

        foreach ($this->context as $key => $value) {
            if ($value === null || $value === '' || ! is_scalar($value)) {
                continue;
            }

            $rows[] = [
                'label' => self::CONTEXT_LABELS[$key] ?? Str::ucfirst(str_replace('_', ' ', (string) $key)),
                'value' => str_replace(['|', "\n", "\r"], ['/', ' ', ' '], (string) $value),
            ];
        }

        return $rows;
    }
}
