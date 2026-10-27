<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Str;

/**
 * Transforma o `data` de um webhook event_alert do Sentry em mensagem do Telegram (HTML) e em um
 * stack trace completo em texto puro. Sem I/O: só leitura defensiva do payload.
 */
class SentryTelegramAlertFormatter
{
    public const MAX_MESSAGE_LENGTH = 4096;

    public const MAX_FRAMES = 15;

    public const FALLBACK_FRAMES = 5;

    /**
     * @param  array<string, mixed>  $data
     * @return array{message: string, fullTrace: ?string, filename: string}
     */
    public function format(array $data): array
    {
        $event = is_array($data['event'] ?? null) ? $data['event'] : [];
        $exceptions = $this->exceptionValues($event);
        $primary = $exceptions === [] ? null : $exceptions[array_key_last($exceptions)];

        $frames = $this->newestFirst($primary['frames'] ?? []);
        $inApp = array_values(array_filter($frames, fn (array $frame): bool => (bool) ($frame['in_app'] ?? false)));
        $shown = $inApp !== [] ? $inApp : array_slice($frames, 0, self::FALLBACK_FRAMES);
        $shown = array_slice($shown, 0, self::MAX_FRAMES);
        $hidden = count($inApp !== [] ? $inApp : $frames) - count($shown);

        return [
            'message' => $this->buildMessage($data, $event, $primary, array_map($this->frameLine(...), $shown), max($hidden, 0)),
            'fullTrace' => $this->buildFullTrace($exceptions),
            'filename' => 'stacktrace-'.$this->reference($event).'.txt',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $event
     * @param  ?array{type: string, value: string, frames: array<int, array<string, mixed>>}  $primary
     * @param  array<int, string>  $lines
     */
    private function buildMessage(array $data, array $event, ?array $primary, array $lines, int $hidden): string
    {
        $head = '🚨 <b>'.$this->escape($this->titleFor($event, $primary)).'</b>';

        $details = [];
        $this->addDetail($details, 'Ambiente', $this->text($event['environment'] ?? null));
        $this->addDetail($details, 'Nível', $this->text($event['level'] ?? null));
        $this->addDetail($details, 'Release', $this->text($event['release'] ?? null));

        $method = $this->text(data_get($event, 'request.method'));
        $url = $this->withoutQueryString($this->text(data_get($event, 'request.url')));
        $this->addDetail($details, 'Requisição', trim($method.' '.Str::limit($url, 200, '…')));
        $this->addDetail($details, 'Culprit', $this->withoutQueryString($this->text($event['culprit'] ?? null)));

        $footer = [];
        $webUrl = $this->text($event['web_url'] ?? null);
        if (preg_match('#^https?://#i', $webUrl) === 1) {
            $footer[] = '<a href="'.$this->escape($webUrl).'">Abrir no Sentry</a>';
        }
        $rule = $this->text($data['triggered_rule'] ?? null);
        if ($rule !== '') {
            $footer[] = 'Regra: '.$this->escape(Str::limit($rule, 200, '…'));
        }

        $shownCount = count($lines);

        while (true) {
            $message = $head;

            if ($details !== []) {
                $message .= "\n\n".implode("\n", $details);
            }

            if ($shownCount > 0) {
                $message .= "\n\n<pre>".implode("\n", array_map($this->escape(...), array_slice($lines, 0, $shownCount))).'</pre>';
                $extra = $hidden + count($lines) - $shownCount;

                if ($extra > 0) {
                    $message .= "\n… +{$extra} frames";
                }
            }

            if ($footer !== []) {
                $message .= "\n\n".implode(' · ', $footer);
            }

            if (mb_strlen($message) <= self::MAX_MESSAGE_LENGTH) {
                return $message;
            }

            if ($shownCount === 0) {
                return mb_substr($head, 0, self::MAX_MESSAGE_LENGTH);
            }

            $shownCount--;
        }
    }

    /**
     * @param  array<int, array{type: string, value: string, frames: array<int, array<string, mixed>>}>  $exceptions
     */
    private function buildFullTrace(array $exceptions): ?string
    {
        $blocks = [];

        foreach (array_reverse($exceptions) as $exception) {
            $frames = $this->newestFirst($exception['frames']);

            if ($frames === []) {
                continue;
            }

            $lines = [$this->exceptionTitle($exception)];

            foreach ($frames as $frame) {
                $lines[] = '  '.(($frame['in_app'] ?? false) ? '[app]    ' : '[vendor] ').$this->frameLine($frame);
            }

            $blocks[] = implode("\n", $lines);
        }

        return $blocks === [] ? null : implode("\n\n", $blocks)."\n";
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<int, array{type: string, value: string, frames: array<int, array<string, mixed>>}>
     */
    private function exceptionValues(array $event): array
    {
        $values = data_get($event, 'exception.values');
        $result = [];

        foreach (is_array($values) ? $values : [] as $value) {
            if (! is_array($value)) {
                continue;
            }

            $frames = data_get($value, 'stacktrace.frames');

            $result[] = [
                'type' => $this->text($value['type'] ?? null),
                'value' => $this->withoutQueryString($this->text($value['value'] ?? null)),
                'frames' => array_values(array_filter(is_array($frames) ? $frames : [], 'is_array')),
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  ?array{type: string, value: string, frames: array<int, array<string, mixed>>}  $primary
     */
    private function titleFor(array $event, ?array $primary): string
    {
        $title = $primary !== null ? $this->exceptionTitle($primary) : '';

        if ($title === '') {
            $title = $this->withoutQueryString(
                $this->text(data_get($event, 'logentry.formatted'))
                    ?: $this->text($event['message'] ?? null)
                    ?: $this->text($event['title'] ?? null)
            ) ?: 'Erro sem título';
        }

        return Str::limit($title, 300, '…');
    }

    /**
     * @param  array{type: string, value: string, frames: array<int, array<string, mixed>>}  $exception
     */
    private function exceptionTitle(array $exception): string
    {
        return trim($exception['type'].($exception['type'] !== '' && $exception['value'] !== '' ? ': ' : '').$exception['value']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $frames
     * @return array<int, array<string, mixed>>
     */
    private function newestFirst(array $frames): array
    {
        return array_reverse(array_values($frames));
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    private function frameLine(array $frame): string
    {
        $path = $this->relativePath($frame);
        $line = $this->text($frame['lineno'] ?? null);
        $function = $this->text($frame['function'] ?? null) ?: $this->text($frame['module'] ?? null);

        return ($path !== '' ? $path : '?').($line !== '' ? ':'.$line : '').($function !== '' ? ' in '.$function : '');
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    private function relativePath(array $frame): string
    {
        $path = $this->text($frame['filename'] ?? null) ?: $this->text($frame['abs_path'] ?? null);
        $root = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        $path = str_replace('\\', '/', $path);

        if (str_starts_with($path, $root)) {
            return substr($path, strlen($root));
        }

        if (preg_match('#^/.*?/(app/.+)$#', $path, $matches) === 1 && str_contains($path, '/vendor/') === false) {
            return $matches[1];
        }

        return ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function reference(array $event): string
    {
        $reference = $this->text($event['issue_id'] ?? null)
            ?: $this->text($event['event_id'] ?? null)
            ?: now()->format('YmdHis');

        return preg_replace('/[^A-Za-z0-9_-]/', '', $reference) ?: now()->format('YmdHis');
    }

    /**
     * @param  array<int, string>  $details
     */
    private function addDetail(array &$details, string $label, string $value): void
    {
        if ($value !== '') {
            $details[] = "<b>{$label}:</b> ".$this->escape(Str::limit($value, 300, '…'));
        }
    }

    /**
     * Tira a query string (e o fragmento) de toda URL ou caminho dentro do texto, deixando só o
     * caminho. As rotas assinadas do projeto levam o segredo ali — /excluir-conta/confirmar/{user}
     * com ?hash=&signature=, /convite/descadastrar com o e-mail criptografado — e o grupo do
     * Telegram é lido por gente que não deveria poder executar a ação em nome do usuário. Vale para
     * qualquer token que venha a aparecer numa query, inclusive dentro da mensagem da exceção
     * (o texto de erro do Guzzle traz a URL chamada por inteiro).
     */
    private function withoutQueryString(string $value): string
    {
        return preg_replace('~((?:https?://|/)[^\s?#]*)[?#]\S*~i', '$1', $value) ?? $value;
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
