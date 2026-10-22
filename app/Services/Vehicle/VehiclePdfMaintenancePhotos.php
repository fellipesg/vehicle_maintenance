<?php

namespace App\Services\Vehicle;

use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

/**
 * Fotos das OS no PDF do histórico (.ai/rules/pdfs.md): decide quais entram, e converte cada uma num
 * JPEG enxuto para o Dompdf.
 *
 * Regra: só entram fotos quando o proprietário ATUAL do veículo marcou export_include_photos = true
 * (nulo = nunca perguntado e falso = não querem, ambos sem foto). Quando quem pede o PDF é esse
 * proprietário, cada OS mostra o "antes" e o "depois" (a primeira foto de cada); para qualquer outra
 * pessoa autorizada a exportar (lojista em consignação, admin) entram só as fotos públicas
 * (MaintenancePhoto::isPublicVisible). A forma mínima do MaintenanceRedactor já esvazia as fotos das
 * OS sem dono, e este serviço só lê o que sobrou na relação `photos`: nunca recarrega nem contorna.
 */
class VehiclePdfMaintenancePhotos
{
    /** Fotos por OS: um par antes/depois, ou as duas primeiras do "depois". */
    public const MAX_PER_MAINTENANCE = 2;

    /**
     * Teto de fotos embutidas no PDF inteiro. O e-mail (EmailVehicleMaintenancePdf) anexa o arquivo
     * e os provedores costumam recusar anexos acima de ~10 MB, sobrando ainda as notas fiscais em PDF
     * que o merge cola no fim. 16 fotos de ~900px em JPEG qualidade 75 pesam cerca de 100 a 200 KB
     * cada, então as fotos ficam em até ~3 MB e o PDF segue bem abaixo de 8 MB. As OS mais recentes
     * ganham as fotos primeiro; o que passa do teto vira "+N fotos no RevisaLog".
     */
    public const MAX_PER_PDF = 16;

    /** Maior lado da foto embutida, em pixels: nítida na página A4 sem pesar no arquivo. */
    public const MAX_EDGE = 900;

    public const JPEG_QUALITY = 75;

    /** Caixa, em px do Dompdf, onde a foto cabe na coluna (duas colunas por linha). */
    public const DISPLAY_MAX_WIDTH = 300;

    public const DISPLAY_MAX_HEIGHT = 225;

    /**
     * O proprietário atual, se ele autorizou as fotos no PDF.
     */
    public function ownerAllowingPhotos(Vehicle $vehicle): ?User
    {
        $owner = $vehicle->owners()
            ->whereRaw('user_vehicles.is_current_owner = true')
            ->first();

        return $owner instanceof User && $owner->export_include_photos === true ? $owner : null;
    }

    /**
     * Escolhe as fotos de cada OS, já passadas pelo MaintenanceRedactor (relação `photos` carregada).
     *
     * @param  iterable<int, Maintenance>  $maintenances  na ordem em que o PDF as mostra
     * @return array<int, array{photos: list<MaintenancePhoto>, eligible: int}> por id da OS
     */
    public function select(iterable $maintenances, bool $requesterIsOwner): array
    {
        $budget = self::MAX_PER_PDF;
        $selection = [];

        foreach ($maintenances as $maintenance) {
            if (! $maintenance->relationLoaded('photos')) {
                continue;
            }

            $eligible = $maintenance->photos
                ->filter(fn (MaintenancePhoto $photo): bool => $requesterIsOwner
                    ? $this->isVehicleBeforeOrAfter($photo)
                    : $photo->isPublicVisible())
                ->sortBy([['sort', 'asc'], ['id', 'asc']])
                ->values();

            if ($eligible->isEmpty()) {
                continue;
            }

            $chosen = $requesterIsOwner ? $this->beforeAfterPair($eligible) : $this->firstAfters($eligible);
            $chosen = array_slice($chosen, 0, min(self::MAX_PER_MAINTENANCE, $budget));
            $budget -= count($chosen);

            $selection[$maintenance->id] = ['photos' => $chosen, 'eligible' => $eligible->count()];
        }

        return $selection;
    }

    /**
     * Caminhos a buscar no storage junto com as demais imagens do PDF.
     *
     * @param  array<int, array{photos: list<MaintenancePhoto>, eligible: int}>  $selection
     * @return list<string>
     */
    public function paths(array $selection): array
    {
        $paths = [];

        foreach ($selection as $entry) {
            foreach ($entry['photos'] as $photo) {
                $paths[] = $this->pathFor($photo);
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Converte as fotos escolhidas, uma de cada vez (memória), e solta os bytes de cada cópia assim
     * que o JPEG sai. Os temporários de cópias remotas entram em $temps para o cleanupTemps.
     *
     * @param  array<int, array{photos: list<MaintenancePhoto>, eligible: int}>  $selection
     * @param  array<string, array{path: string, temporary: bool, content?: string}>  $copies
     * @param  list<string>  $temps
     * @return array<int, array{items: list<array{src: string, width: int, height: int, label: string}>, extra: int}>
     */
    public function embed(array $selection, array &$copies, array &$temps): array
    {
        $embedded = [];

        foreach ($selection as $maintenanceId => $entry) {
            $items = [];

            foreach ($entry['photos'] as $photo) {
                $path = $this->pathFor($photo);
                $copy = $copies[$path] ?? null;
                unset($copies[$path]);

                if ($copy === null) {
                    continue;
                }

                if ($copy['temporary']) {
                    $temps[] = $copy['path'];
                }

                $bytes = $copy['content'] ?? (is_file($copy['path']) ? file_get_contents($copy['path']) : false);
                unset($copy);

                $jpeg = is_string($bytes) && $bytes !== '' ? $this->toJpeg($bytes) : null;
                unset($bytes);

                if ($jpeg === null) {
                    continue;
                }

                $items[] = [
                    'src' => 'data:image/jpeg;base64,'.base64_encode($jpeg['bytes']),
                    'width' => $jpeg['width'],
                    'height' => $jpeg['height'],
                    'label' => $photo->stage === MaintenancePhoto::STAGE_BEFORE ? 'Antes' : 'Depois',
                ];
            }

            if ($items !== []) {
                $embedded[$maintenanceId] = ['items' => $items, 'extra' => max(0, $entry['eligible'] - count($items))];
            }
        }

        return $embedded;
    }

    /**
     * Prefere a variante de exibição (display_path, quando a coluna existe e está preenchida) ao
     * original. Lê pelos atributos crus: sem a coluna, nada de acesso a atributo ausente.
     */
    public function pathFor(MaintenancePhoto $photo): string
    {
        $displayPath = $photo->getAttributes()['display_path'] ?? null;

        return is_string($displayPath) && $displayPath !== '' ? $displayPath : (string) $photo->path;
    }

    private function isVehicleBeforeOrAfter(MaintenancePhoto $photo): bool
    {
        return $photo->subject === MaintenancePhoto::SUBJECT_VEHICLE
            && in_array($photo->stage, [MaintenancePhoto::STAGE_BEFORE, MaintenancePhoto::STAGE_AFTER], true);
    }

    /**
     * @param  Collection<int, MaintenancePhoto>  $photos
     * @return list<MaintenancePhoto>
     */
    private function beforeAfterPair(Collection $photos): array
    {
        $before = $photos->first(fn (MaintenancePhoto $photo): bool => $photo->stage === MaintenancePhoto::STAGE_BEFORE);
        $after = $photos->first(fn (MaintenancePhoto $photo): bool => $photo->stage === MaintenancePhoto::STAGE_AFTER);

        if ($before !== null && $after !== null) {
            return [$before, $after];
        }

        return $this->firstAfters($photos);
    }

    /**
     * @param  Collection<int, MaintenancePhoto>  $photos
     * @return list<MaintenancePhoto>
     */
    private function firstAfters(Collection $photos): array
    {
        return $photos
            ->filter(fn (MaintenancePhoto $photo): bool => $photo->stage === MaintenancePhoto::STAGE_AFTER)
            ->take(self::MAX_PER_MAINTENANCE)
            ->values()
            ->all();
    }

    /**
     * JPEG de até MAX_EDGE px no maior lado, sobre fundo branco (PNG/WebP com transparência) e já na
     * orientação certa. O Dompdf não lida bem com WebP, então tudo sai JPEG.
     *
     * @return array{bytes: string, width: int, height: int}|null largura/altura já ajustadas à caixa de exibição
     */
    private function toJpeg(string $bytes): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return null;
        }

        $source = $this->orientedLikeCamera($source, $bytes);
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($source);

            return null;
        }

        $scale = min(1.0, self::MAX_EDGE / max($sourceWidth, $sourceHeight));
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));

        $canvas = imagecreatetruecolor($width, $height);
        imagefilledrectangle($canvas, 0, 0, $width - 1, $height - 1, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
        imagedestroy($source);

        ob_start();
        imagejpeg($canvas, null, self::JPEG_QUALITY);
        imagedestroy($canvas);
        $jpeg = ob_get_clean();

        if (! is_string($jpeg) || $jpeg === '') {
            return null;
        }

        $fit = min(self::DISPLAY_MAX_WIDTH / $width, self::DISPLAY_MAX_HEIGHT / $height);

        return [
            'bytes' => $jpeg,
            'width' => max(1, (int) round($width * $fit)),
            'height' => max(1, (int) round($height * $fit)),
        ];
    }

    /**
     * Celular grava a foto "deitada" com a orientação no EXIF; o GD ignora isso.
     */
    private function orientedLikeCamera(\GdImage $image, string $bytes): \GdImage
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($bytes, "\xFF\xD8")) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }
}
