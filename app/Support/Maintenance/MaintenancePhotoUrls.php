<?php

namespace App\Support\Maintenance;

use App\Models\MaintenancePhoto;
use App\Support\AppStorage;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * URLs temporárias das fotos de manutenção. Só chame depois da MaintenancePhotoPolicy::view: quem
 * tem a URL abre o arquivo até ela expirar, sem outra checagem.
 *
 * A vida é de 10 minutos, com a expiração arredondada para janelas fixas (fim da próxima janela de
 * 10 minutos): requisições dentro da mesma janela recebem a MESMA URL, então a resposta da linha do
 * tempo não muda a cada chamada e o cache/ETag do app continua devolvendo 304. No S3/R2 a URL
 * assinada carrega o horário da assinatura (X-Amz-Date), que mudaria a cada chamada mesmo com a
 * mesma expiração; por isso a URL de cada foto/variante/janela fica no cache da aplicação.
 *
 * Nunca devolve o caminho no storage como campo: a URL é a única coisa que sai.
 */
final class MaintenancePhotoUrls
{
    public const VARIANT_MASTER = 'master';

    public const VARIANT_DISPLAY = 'display';

    public const VARIANT_THUMB = 'thumb';

    public const VARIANTS = [self::VARIANT_THUMB, self::VARIANT_DISPLAY, self::VARIANT_MASTER];

    /**
     * Tamanho da janela e vida mínima da URL, em segundos.
     */
    public const TTL_SECONDS = 600;

    /**
     * Fim da próxima janela: floor(agora / 600) * 600 + 1200. Quem pede no começo da janela recebe
     * uma URL de 20 minutos, quem pede no fim, de 10.
     */
    public static function expiresAt(?CarbonInterface $now = null): CarbonImmutable
    {
        $timestamp = ($now ?? now())->getTimestamp();

        return CarbonImmutable::createFromTimestamp(
            intdiv($timestamp, self::TTL_SECONDS) * self::TTL_SECONDS + 2 * self::TTL_SECONDS,
            config('app.timezone'),
        );
    }

    /**
     * thumb, display e master, numa leitura de cache por foto e janela. Foto ainda sem processar (ou
     * antiga) devolve a URL do original no lugar de thumb e display.
     *
     * @return array{thumb: string, display: string, master: string}
     */
    public static function variants(MaintenancePhoto $photo): array
    {
        $expiresAt = self::expiresAt();
        $remainingInWindow = max(1, $expiresAt->getTimestamp() - self::TTL_SECONDS - now()->getTimestamp());

        $key = sprintf(
            'maintenance-photo-urls:%d:%s:%d',
            $photo->id,
            md5(implode('|', array_map(fn (string $variant): string => (string) self::variantPath($photo, $variant), self::VARIANTS))),
            $expiresAt->getTimestamp(),
        );

        return Cache::remember($key, $remainingInWindow, function () use ($photo, $expiresAt): array {
            $urls = [];

            foreach (self::VARIANTS as $variant) {
                $effective = self::effectiveVariant($photo, $variant);
                $urls[$effective] ??= self::signedUrl($photo, $effective, $expiresAt);
                $urls[$variant] = $urls[$effective];
            }

            return [
                self::VARIANT_THUMB => $urls[self::VARIANT_THUMB],
                self::VARIANT_DISPLAY => $urls[self::VARIANT_DISPLAY],
                self::VARIANT_MASTER => $urls[self::VARIANT_MASTER],
            ];
        });
    }

    /**
     * Variante que de fato existe: sem o arquivo processado, a pedida cai para o master.
     */
    public static function effectiveVariant(MaintenancePhoto $photo, string $variant): string
    {
        return self::variantPath($photo, $variant) === null ? self::VARIANT_MASTER : $variant;
    }

    /**
     * Caminho no storage da variante pedida, ou nulo quando ela não existe (ainda).
     */
    public static function variantPath(MaintenancePhoto $photo, string $variant): ?string
    {
        $path = match ($variant) {
            self::VARIANT_THUMB => $photo->isProcessed() ? $photo->thumb_path : null,
            self::VARIANT_DISPLAY => $photo->isProcessed() ? $photo->display_path : null,
            self::VARIANT_MASTER => $photo->path,
            default => null,
        };

        return is_string($path) && $path !== '' ? $path : null;
    }

    public static function temporaryUrl(MaintenancePhoto $photo, string $variant): string
    {
        return self::variants($photo)[$variant];
    }

    private static function signedUrl(MaintenancePhoto $photo, string $variant, CarbonImmutable $expiresAt): string
    {
        $path = (string) self::variantPath($photo, $variant);

        /** @var FilesystemAdapter $disk */
        $disk = AppStorage::disk();

        if (AppStorage::isRemote()) {
            return $disk->temporaryUrl($path, $expiresAt);
        }

        self::registerLocalTemporaryUrls($disk);

        return $disk->temporaryUrl($path, $expiresAt, ['photo' => $photo->id, 'variant' => $variant]);
    }

    /**
     * Ensina o disco local a assinar URLs (o disco `public` não gera URL temporária sozinho):
     * `buildTemporaryUrlsUsing` aponta para a rota assinada de uma foto e uma variante. O
     * AppServiceProvider chama no boot; signedUrl chama de novo porque Storage::fake troca a
     * instância do disco (e já traz um gerador próprio, que aqui é substituído).
     */
    public static function registerLocalTemporaryUrls(Filesystem $disk): void
    {
        if (! $disk instanceof FilesystemAdapter) {
            return;
        }

        $disk->buildTemporaryUrlsUsing(function (string $path, DateTimeInterface $expiration, array $options): string {
            if (! isset($options['photo'], $options['variant'])) {
                throw new RuntimeException('Local temporary URLs are only available for maintenance photos.');
            }

            return URL::temporarySignedRoute('api.maintenance-photos.signed', $expiration, [
                'photo' => $options['photo'],
                'variant' => $options['variant'],
            ]);
        });
    }
}
