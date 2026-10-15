<?php

namespace App\Support\Maintenance;

use App\Models\MaintenancePhoto;
use App\Support\AppStorage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Serve o arquivo de uma foto de manutenção para quem já passou pela MaintenancePhotoPolicy::view.
 * A web (MaintenancePhotoController) e a API (Api\MaintenancePhotoController::show) respondem a
 * mesma coisa, então os cabeçalhos defensivos e a armadilha do Cache-Control ficam num lugar só.
 *
 * O arquivo é do cliente, então sai com os mesmos cabeçalhos do servidor de arquivos do Laravel
 * (sandbox + nosniff). Imagem conhecida abre na aba (miniatura e lightbox são <img>); qualquer
 * outra extensão baixa.
 */
class MaintenancePhotoFileResponse
{
    /**
     * Extensões que o navegador pode abrir na aba, com o tipo que vai no Content-Type. É a lista
     * aceita no upload (MaintenanceController::photoFileRules e StoreMaintenancePhotoRequest),
     * mais o jpg que o MaintenancePhotoService assume quando o arquivo chega sem extensão.
     */
    private const INLINE_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    /**
     * A galeria da OS pede várias fotos por tela, então o cache do navegador vale a pena — privado
     * e curto, para o aceite revogado (attachments_status) deixar de abrir logo.
     */
    private const CACHE_MAX_AGE = 300;

    /**
     * Responde 404 (abort) quando a foto não tem caminho ou o arquivo não está mais no storage.
     */
    public static function for(MaintenancePhoto $photo): BinaryFileResponse
    {
        $path = (string) $photo->path;

        if ($path === '') {
            abort(404);
        }

        $copy = AppStorage::localCopy($path);

        if ($copy === null) {
            abort(404);
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $inline = array_key_exists($extension, self::INLINE_TYPES);

        $response = response()->download(
            $copy['path'],
            self::downloadName($photo, $path),
            [
                'Content-Type' => self::INLINE_TYPES[$extension] ?? 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            ],
            $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        );

        // O BinaryFileResponse do response()->download() nasce público (e um Cache-Control
        // 'private' no array de cabeçalhos é descartado), então o cache privado é marcado aqui.
        $response->setPrivate();
        $response->setMaxAge(self::CACHE_MAX_AGE);
        $response->headers->addCacheControlDirective('must-revalidate');

        if ($copy['temporary']) {
            $response->deleteFileAfterSend();
        }

        return $response;
    }

    /**
     * Nome que o cliente enviou, sem o que o Content-Disposition não aceita (separadores de
     * caminho). O caminho no storage não mostra nada, então ele serve só para exibir.
     */
    private static function downloadName(MaintenancePhoto $photo, string $path): string
    {
        $name = trim(str_replace(['/', '\\'], '', (string) $photo->original_name));

        return $name !== '' ? $name : basename($path);
    }
}
