<?php

namespace App\Console\Commands;

use App\Support\AppStorage;
use App\Support\UploadedImageExtension;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Conserta os caminhos gravados quando a extensão ainda saía do nome enviado pelo cliente.
 *
 * Até `UploadedImageExtension` entrar em cena, logo, capa e avatar eram gravados com
 * `getClientOriginalExtension()`: um PNG de verdade chamado `logo.html` virava
 * `workshop-logos/1_123.html`. Esses caminhos são servidos direto pelo servidor web ou pelo CDN do
 * bucket, onde a **extensão** decide o Content-Type, então o objeto antigo continua saindo como
 * `text/html` — a correção de gravação não alcança o que já está lá.
 *
 * O comando lê o conteúdo de cada caminho, deduz a extensão certa pelos bytes e, quando difere,
 * grava o objeto no caminho corrigido, confere que ele existe, aponta a coluna para ele e só então
 * apaga o antigo. Nessa ordem: se qualquer passo falhar, a coluna ainda aponta para um objeto que
 * existe. Rodar de novo é seguro — o que já está certo é contado e não tocado.
 *
 * Não mexe no que não consegue identificar: conteúdo fora de jpg/png/webp é relatado e fica como
 * está, porque renomear no escuro seria chutar. `users.avatar` também guarda URL de provedor
 * (Google/Apple, ver `User::resolveAvatarUrl`), e nenhum caminho fora do prefixo esperado é tocado.
 */
class FixImageExtensionsCommand extends Command
{
    protected $signature = 'storage:fix-image-extensions
        {--dry-run : Só lista o que mudaria}
        {--limit=0 : Para depois de N caminhos corrigidos (0 = sem limite)}';

    protected $description = 'Corrige a extensão de logos, capas e avatares gravados com a extensão enviada pelo cliente';

    /**
     * Coluna => prefixo esperado no storage. Fora do prefixo nada é tocado.
     *
     * A miniatura da capa (`cover_photo_thumb_path`) fica de fora porque sempre nasceu `.jpg`, pelo
     * `VehicleCoverService::storeThumbBytes`, e nunca passou pela extensão do cliente.
     *
     * @var list<array{table: string, column: string, prefix: string}>
     */
    private const TARGETS = [
        ['table' => 'workshops', 'column' => 'logo_path', 'prefix' => AppStorage::WORKSHOP_LOGOS_PREFIX],
        ['table' => 'vehicles', 'column' => 'cover_photo_path', 'prefix' => AppStorage::COVERS_PREFIX],
        ['table' => 'vehicles', 'column' => 'cover_photo_portrait_path', 'prefix' => AppStorage::COVERS_PREFIX],
        ['table' => 'blog_posts', 'column' => 'cover_photo_path', 'prefix' => AppStorage::BLOG_COVERS_PREFIX],
        ['table' => 'users', 'column' => 'avatar', 'prefix' => 'avatars/'],
    ];

    private int $fixed = 0;

    private int $alreadyRight = 0;

    private int $unreadable = 0;

    private int $unidentified = 0;

    private int $collisions = 0;

    private int $failures = 0;

    public function handle(): int
    {
        // O Artisan reusa a instância do comando dentro do mesmo processo, então os contadores
        // precisam zerar aqui: chamado duas vezes numa só execução (teste, agendador,
        // Artisan::call seguido de outro), o resumo da segunda vinha somado com o da primeira.
        $this->fixed = 0;
        $this->alreadyRight = 0;
        $this->unreadable = 0;
        $this->unidentified = 0;
        $this->collisions = 0;
        $this->failures = 0;

        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));

        if ($dryRun) {
            $this->warn('Simulação: nada é gravado nem apagado.');
            $this->newLine();
        }

        foreach (self::TARGETS as $target) {
            // O limite também sai do laço de fora: sem isto, cada tabela restante abria o seu
            // cabeçalho e repetia o aviso de limite, cinco vezes num relatório de uma linha útil.
            if ($this->reachedLimit($limit)) {
                break;
            }

            $this->line("{$target['table']}.{$target['column']}");

            DB::table($target['table'])
                ->whereNotNull($target['column'])
                ->where($target['column'], '!=', '')
                ->orderBy('id')
                ->select(['id', $target['column']])
                ->chunkById(200, function ($rows) use ($target, $dryRun, $limit): bool {
                    foreach ($rows as $row) {
                        if ($this->reachedLimit($limit)) {
                            return false;
                        }

                        $this->handlePath($target, (int) $row->id, (string) $row->{$target['column']}, $dryRun);
                    }

                    return true;
                });
        }

        $this->newLine();

        if ($this->reachedLimit($limit)) {
            $this->warn("Limite de {$limit} alcançado: rode de novo para seguir de onde parou.");
        }

        $this->line($dryRun ? 'A corrigir: '.$this->fixed : 'Corrigidos: '.$this->fixed);
        $this->line('Já certos: '.$this->alreadyRight);

        foreach ([
            'Sem leitura no storage' => $this->unreadable,
            'Conteúdo não identificado' => $this->unidentified,
            'Caminho de destino já ocupado' => $this->collisions,
            'Falhas' => $this->failures,
        ] as $label => $count) {
            if ($count > 0) {
                $this->warn("{$label}: {$count}");
            }
        }

        return $this->failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function reachedLimit(int $limit): bool
    {
        return $limit > 0 && $this->fixed >= $limit;
    }

    /**
     * @param  array{table: string, column: string, prefix: string}  $target
     */
    private function handlePath(array $target, int $id, string $path, bool $dryRun): void
    {
        // Avatar também guarda URL de provedor, e um caminho fora do prefixo não é desta família.
        if (! str_starts_with($path, $target['prefix'])) {
            return;
        }

        $disk = AppStorage::diskForPath($path);

        // Lê pela mesma disk em que vai gravar, e não por AppStorage::contents(): aquele caminho
        // busca por HTTP e guarda os bytes em cache por sete dias, que não é o que uma rotina de
        // manutenção quer. A disk do app roda com `throw => false`, então um erro de leitura volta
        // vazio — e vazio aqui é relatado, não ignorado.
        try {
            $bytes = $disk->get($path);
        } catch (\Throwable $exception) {
            $bytes = null;
            $this->warn('  '.$exception->getMessage());
        }

        if (! is_string($bytes) || $bytes === '') {
            $this->unreadable++;
            $this->warn("  sem leitura  {$path}");

            return;
        }

        $correct = UploadedImageExtension::forBytes($bytes);

        if ($correct === null) {
            $this->unidentified++;
            $this->warn("  conteúdo ?   {$path}");

            return;
        }

        if ($this->extensionOf($path) === $correct) {
            $this->alreadyRight++;

            return;
        }

        $newPath = $this->pathWithExtension($path, $correct);

        if ($disk->exists($newPath)) {
            $this->collisions++;
            $this->warn("  destino ocupado  {$path} -> {$newPath}");

            return;
        }

        if ($dryRun) {
            $this->fixed++;
            $this->line("  mudaria      {$path} -> {$newPath}");

            return;
        }

        try {
            // Grava, confere, aponta a coluna e só então apaga: falhando no meio, a coluna segue
            // apontando para um objeto que existe.
            $options = AppStorage::isPublicCacheEligiblePath($newPath) ? AppStorage::publicObjectOptions() : [];
            $disk->put($newPath, $bytes, $options);

            if (! $disk->exists($newPath)) {
                throw new \RuntimeException('o storage não confirmou a gravação');
            }

            DB::table($target['table'])->where('id', $id)->update([$target['column'] => $newPath]);

            $disk->delete($path);
        } catch (\Throwable $exception) {
            $this->failures++;
            $this->error("  falhou       {$path}: ".$exception->getMessage());

            return;
        }

        $this->fixed++;
        $this->line("  corrigido    {$path} -> {$newPath}");
    }

    private function extensionOf(string $path): string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        // jpeg e jpg são o mesmo conteúdo: não vale renomear por isso.
        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    private function pathWithExtension(string $path, string $extension): string
    {
        $directory = (string) pathinfo($path, PATHINFO_DIRNAME);
        $name = (string) pathinfo($path, PATHINFO_FILENAME);

        return ($directory === '' || $directory === '.' ? '' : $directory.'/').$name.'.'.$extension;
    }
}
