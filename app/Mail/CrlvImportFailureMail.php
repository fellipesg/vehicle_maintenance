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
     * maiúscula e sem o "_". Não há rótulo para o e-mail de quem enviou nem para o nome do arquivo:
     * o aviso identifica a conta pelo id e o documento pela extensão, pelo tamanho e por um hash
     * curto do conteúdo (App\Services\Crlv\CrlvImportFailureReporter), e o que falta ao suporte é o
     * layout do documento, não a pessoa.
     *
     * @var array<string, string>
     */
    public const CONTEXT_LABELS = [
        'origem' => 'Origem',
        'extensao' => 'Extensão',
        'tamanho_kb' => 'Tamanho (KB)',
        'hash_arquivo' => 'Hash do arquivo',
        'usuario_id' => 'ID do usuário',
    ];

    /**
     * Chaves que nunca vão para a tabela, mesmo que cheguem no contexto: o nome escolhido pelo
     * cliente pode trazer nome ou CPF da pessoa (LGPD, .ai/rules/models.md) e o e-mail da conta não
     * ajuda a cobrir um layout de DETRAN. O corte fica aqui, na borda que manda o aviso para fora.
     *
     * @var list<string>
     */
    public const BLOCKED_CONTEXT_KEYS = [
        'arquivo',
        'nome_arquivo',
        'usuario_email',
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
     * Linhas da tabela, na ordem do contexto, sem os valores vazios e sem as chaves bloqueadas. O
     * "|" do valor é trocado por "/" para não quebrar a tabela em markdown.
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

            if (in_array((string) $key, self::BLOCKED_CONTEXT_KEYS, true)) {
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
