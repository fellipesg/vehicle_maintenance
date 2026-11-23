<?php

namespace App\Services\Crlv;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * O CRLV-e lido pela API, entre a leitura (POST /api/v1/vehicles/crlv) e a gravação do veículo. É o
 * papel que `crlv_verification` faz na sessão do site: o app não tem sessão, então a leitura fica no
 * cache, presa à conta que enviou o PDF, e o app só devolve o token. O app nunca manda os dados do
 * documento de volta como prova: o que vale é o que o servidor leu.
 */
class CrlvApiImportStore
{
    public const TTL_MINUTES = 30;

    public function put(User $user, CrlvParseResult $crlv): string
    {
        $token = $crlv->verificationToken();

        Cache::put($this->key($user, $token), $crlv->toPreview(), now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    public function find(User $user, ?string $token): ?CrlvParseResult
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        $preview = Cache::get($this->key($user, $token));

        return is_array($preview) ? CrlvParseResult::fromPreview($preview) : null;
    }

    public function forget(User $user, string $token): void
    {
        Cache::forget($this->key($user, $token));
    }

    private function key(User $user, string $token): string
    {
        return 'crlv-api-import:'.$user->id.':'.$token;
    }
}
