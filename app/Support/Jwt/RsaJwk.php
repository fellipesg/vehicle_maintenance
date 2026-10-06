<?php

namespace App\Support\Jwt;

/**
 * Converte a chave pública RSA de um JWK (n, e) em PEM para openssl_verify.
 */
final class RsaJwk
{
    public static function base64UrlDecode(string $value): ?string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    public static function toPem(string $modulus, string $exponent): string
    {
        $modulus = ltrim($modulus, "\x00");
        $exponent = ltrim($exponent, "\x00");

        if ((ord($modulus[0] ?? "\x00") & 0x80) !== 0) {
            $modulus = "\x00".$modulus;
        }

        if ((ord($exponent[0] ?? "\x00") & 0x80) !== 0) {
            $exponent = "\x00".$exponent;
        }

        $rsaPublicKey = self::sequence(self::integer($modulus).self::integer($exponent));
        $bitString = "\x03".self::length(strlen($rsaPublicKey) + 1)."\x00".$rsaPublicKey;
        $algorithm = (string) hex2bin('300d06092a864886f70d0101010500');

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode(self::sequence($algorithm.$bitString)), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    private static function integer(string $value): string
    {
        return "\x02".self::length(strlen($value)).$value;
    }

    private static function sequence(string $value): string
    {
        return "\x30".self::length(strlen($value)).$value;
    }

    private static function length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $encoded = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($encoded)).$encoded;
    }
}
