<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * O índice de .ai/rules é o artefato que se lê: o AGENTS.md manda abrir .ai/rules/index.md, achar a
 * linha cujos globs casam com o caminho em questão e ler aquele arquivo de regra. O front matter de
 * cada regra (`paths:`) é o registro de quem ela cobre. Quando um caminho está no front matter e
 * **não** no índice, quem edita esse caminho nunca chega à regra — e a regra existe justamente por
 * ser algo que não se descobre lendo o código.
 *
 * Isso já regrediu duas vezes (depois do PR #76 e de novo depois dele): comentários do blog
 * (BlogComment + os dois BlogCommentController) e os form requests de modelo de mensagem estavam no
 * front matter e fora do índice. Este teste fecha essa porta.
 *
 * Direção deliberada: só front matter ⊆ índice. O contrário (índice mais largo que o front matter)
 * é comum e intencional — o índice agrupa `resources/views/vehicles/entry/**` em consignment.md,
 * `app/Observers/**` em models.md, `app/Console/Commands/*Prospect*` em outreach.md — e no pior caso
 * faz ler regra demais, que é o lado seguro de errar. Travar as duas direções transformaria cada
 * arquivo novo nessas pastas em teste vermelho sem nada de errado, e um teste que grita à toa é
 * desligado.
 *
 * O custo: a comparação expande os globs contra `git ls-files`, então só vê arquivo que já está
 * versionado — um glob apontando para caminho que ainda não existe passa (não é falso verde que
 * importe: quando o arquivo entrar, ele passa a ser comparado). Precisa de `git` no PATH e roda uma
 * vez por suíte (dois `git ls-files` e um passe de regex por regra: ~50 ms aqui).
 */
class AiRulesIndexCoversFrontMatterTest extends TestCase
{
    private const RULES_DIR = __DIR__.'/../../.ai/rules';

    public function test_every_front_matter_path_is_routed_by_the_index(): void
    {
        $repoFiles = $this->repoFiles();
        $indexGlobs = $this->indexGlobs();

        $missing = [];

        foreach (glob(self::RULES_DIR.'/*.md') as $path) {
            $name = basename($path);

            if ($name === 'index.md') {
                continue;
            }

            $frontMatterGlobs = $this->frontMatterGlobs($path);

            if ($frontMatterGlobs === []) {
                continue;
            }

            $routed = $this->expand($indexGlobs[$name] ?? [], $repoFiles);

            foreach ($this->expand($frontMatterGlobs, $repoFiles) as $file) {
                if (! in_array($file, $routed, true)) {
                    $missing[] = "$file está no front matter de $name, mas nenhuma linha do índice o manda para $name";
                }
            }
        }

        sort($missing);

        $this->assertSame([], $missing, "O índice de .ai/rules não cobre tudo o que os front matters declaram.\n"
            .'Acrescente o glob que falta à linha do índice que aponta para a regra:'."\n  ".implode("\n  ", $missing)."\n");
    }

    /**
     * @return list<string>
     */
    private function repoFiles(): array
    {
        $root = realpath(self::RULES_DIR.'/../..');
        exec('git -C '.escapeshellarg($root).' ls-files 2>/dev/null', $files, $status);

        $this->assertSame(0, $status, 'git ls-files falhou: este teste precisa do git no PATH.');
        $this->assertNotEmpty($files, 'git ls-files não devolveu nada.');

        return $files;
    }

    /**
     * Globs da coluna "Applies to", agrupados pelo arquivo de regra da linha.
     *
     * @return array<string, list<string>>
     */
    private function indexGlobs(): array
    {
        $globs = [];

        foreach (file(self::RULES_DIR.'/index.md') as $line) {
            if (! preg_match('/^\|(.+?)\|\s*(\.ai\/rules\/[A-Za-z0-9._-]+\.md)/', $line, $matches)) {
                continue;
            }

            foreach ($this->splitGlobs($matches[1]) as $glob) {
                $globs[basename(trim($matches[2]))][] = $glob;
            }
        }

        return $globs;
    }

    /**
     * @return list<string>
     */
    private function frontMatterGlobs(string $path): array
    {
        $text = (string) file_get_contents($path);

        if (! preg_match('/^---\R(.*?)\R---/s', $text, $matches)) {
            return [];
        }

        if (! preg_match('/^\s*paths:\s*$(.*?)(?=^\S|\z)/ms', $matches[1]."\n", $block)) {
            return [];
        }

        $globs = [];

        foreach (explode("\n", $block[1]) as $line) {
            if (preg_match('/^\s*-\s*(.+?)\s*$/', $line, $entry)) {
                foreach ($this->splitGlobs($entry[1]) as $glob) {
                    $globs[] = $glob;
                }
            }
        }

        return $globs;
    }

    /**
     * Separa por vírgula sem quebrar uma alternância {a,b}.
     *
     * @return list<string>
     */
    private function splitGlobs(string $value): array
    {
        $parts = array_map(
            static fn (string $part): string => trim($part, " \t'\""),
            preg_split('/,(?![^{]*\})/', $value) ?: [],
        );

        return array_values(array_filter(
            $parts,
            static fn (string $part): bool => $part !== '' && $part !== 'Applies to' && trim($part, '- ') !== '',
        ));
    }

    /**
     * Caminhos versionados que estes globs alcançam. `frontend/` é a regra do app Flutter, que não
     * está neste repositório.
     *
     * @param  list<string>  $globs
     * @param  list<string>  $repoFiles
     * @return list<string>
     */
    private function expand(array $globs, array $repoFiles): array
    {
        $matched = [];

        foreach ($globs as $glob) {
            if (str_starts_with($glob, 'frontend/')) {
                continue;
            }

            $pattern = $this->globToRegex($glob);

            foreach ($repoFiles as $file) {
                if (preg_match($pattern, $file) === 1) {
                    $matched[$file] = true;
                }
            }
        }

        return array_keys($matched);
    }

    /**
     * `**` cruza separadores, `*` e `?` não, `{a,b}` alterna — o mesmo que o front matter espera.
     */
    private function globToRegex(string $glob): string
    {
        $regex = '';
        $index = 0;
        $length = strlen($glob);

        while ($index < $length) {
            $char = $glob[$index];

            if ($char === '*' && ($glob[$index + 1] ?? '') === '*') {
                $index += 2;

                if (($glob[$index] ?? '') === '/') {
                    $regex .= '(?:.*/)?';
                    $index++;
                } else {
                    $regex .= '.*';
                }

                continue;
            }

            if ($char === '*') {
                $regex .= '[^/]*';
                $index++;

                continue;
            }

            if ($char === '?') {
                $regex .= '[^/]';
                $index++;

                continue;
            }

            if ($char === '{' && ($close = strpos($glob, '}', $index)) !== false) {
                $alternatives = explode(',', substr($glob, $index + 1, $close - $index - 1));
                $regex .= '(?:'.implode('|', array_map(
                    static fn (string $alternative): string => preg_quote(trim($alternative), '#'),
                    $alternatives,
                )).')';
                $index = $close + 1;

                continue;
            }

            $regex .= preg_quote($char, '#');
            $index++;
        }

        return '#^'.$regex.'$#';
    }
}
