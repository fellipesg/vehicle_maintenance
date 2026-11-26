<?php

namespace App\Support\Seo;

use App\Models\BlogPost;
use Illuminate\Support\Str;

/**
 * Quem assina um post do blog. Post sem autor gravado leva o nome do responsável pelo RevisaLog,
 * o mesmo que o BlogPosting já declarava; só ele tem página própria (/sobre#autor).
 */
final class BlogAuthor
{
    public const OWNER_NAME = 'Felipe Gonçalves';

    public static function name(BlogPost $post): string
    {
        $name = trim((string) $post->author?->name);

        return $name !== '' ? $name : self::OWNER_NAME;
    }

    public static function isOwner(string $name): bool
    {
        return self::normalize($name) === self::normalize(self::OWNER_NAME);
    }

    /**
     * URL absoluta de /sobre#autor, só quando quem assina é o Felipe.
     */
    public static function profileUrl(BlogPost $post): ?string
    {
        return self::isOwner(self::name($post)) ? self::aboutAuthorUrl() : null;
    }

    public static function aboutAuthorUrl(): string
    {
        return CanonicalUrl::named('about').'#autor';
    }

    private static function normalize(string $name): string
    {
        return Str::lower(Str::ascii(trim((string) preg_replace('/\s+/u', ' ', $name))));
    }
}
