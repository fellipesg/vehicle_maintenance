<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Mime\MimeTypes;

/**
 * Extensão do arquivo gravado no storage, decidida pelo conteúdo e nunca pelo nome que o cliente
 * enviou.
 *
 * Logos, capas e avatares vão para caminho público servido direto pelo servidor web
 * (`asset('storage/...')`) ou pelo CDN do bucket, e nos dois casos é a **extensão** que decide o
 * Content-Type. Com a extensão vinda de `getClientOriginalExtension()`, um PNG de verdade chamado
 * `logo.html` passava a validação (`File::image()` confere o conteúdo, não o nome) e era gravado como
 * `workshop-logos/1_123.html`: servido como `text/html`, e um PNG aceita texto arbitrário num chunk
 * tEXt, então o navegador executaria o que estivesse lá, na origem do app. `.svg` tinha o mesmo
 * caminho aberto. Só `.php` e parentes eram barrados, pelo `shouldBlockPhpUpload` do próprio Laravel.
 *
 * `guessExtension()` lê o conteúdo (finfo), então a extensão deixa de ser escolha de quem envia. A
 * validação de todas as superfícies aceita jpg, jpeg, png e webp, que é a lista daqui; qualquer outra
 * coisa cai no fallback em vez de ir para o caminho.
 *
 * O nome original continua podendo ser guardado em coluna, para exibir a quem tem acesso — nunca no
 * caminho (LGPD, `.ai/rules/models.md`).
 */
class UploadedImageExtension
{
    /**
     * A mesma lista do `->types([...])` das regras de upload.
     *
     * @var list<string>
     */
    public const ALLOWED = ['jpg', 'jpeg', 'png', 'webp'];

    public const FALLBACK = 'jpg';

    public static function for(UploadedFile $file): string
    {
        $extension = strtolower((string) $file->guessExtension());

        return in_array($extension, self::ALLOWED, true) ? $extension : self::FALLBACK;
    }

    /**
     * A extensão do conteúdo que já está no storage, ou null quando ele não é uma das quatro.
     *
     * Serve ao `storage:fix-image-extensions`, que conserta os caminhos gravados antes de a extensão
     * passar a sair do conteúdo. Aqui **não** existe o fallback de `for()`: renomear um objeto que não
     * se consegue identificar seria chutar, então o comando relata e não mexe.
     */
    public static function forBytes(string $bytes): ?string
    {
        if ($bytes === '') {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        if (! is_string($mime) || $mime === '') {
            return null;
        }

        foreach (MimeTypes::getDefault()->getExtensions($mime) as $extension) {
            $extension = strtolower($extension);

            if (in_array($extension, self::ALLOWED, true)) {
                return $extension;
            }
        }

        return null;
    }
}
