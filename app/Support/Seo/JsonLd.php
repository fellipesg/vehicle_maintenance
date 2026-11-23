<?php

namespace App\Support\Seo;

final class JsonLd
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function encode(array $data): string
    {
        return json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_THROW_ON_ERROR,
        );
    }
}
