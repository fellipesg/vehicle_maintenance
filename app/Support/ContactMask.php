<?php

namespace App\Support;

/**
 * Hides most of a contact so a garage can confirm it is talking about the right person
 * without us handing over a third party's e-mail or phone number.
 */
final class ContactMask
{
    public static function email(?string $email): ?string
    {
        if ($email === null || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).str_repeat('*', max(mb_strlen($local) - 1, 1)).'@'.$domain;
    }

    public static function phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone) ?? '';

        if (mb_strlen($digits) < 6) {
            return null;
        }

        return '('.mb_substr($digits, 0, 2).') *****-'.mb_substr($digits, -4);
    }
}
