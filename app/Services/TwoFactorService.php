<?php

namespace App\Services;

use App\Models\Identity;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * O 2FA é da identidade, não do papel: quem liga o segundo fator o liga uma vez e ele vale para
 * todas as contas irmãs — senão a pessoa configuraria o aplicativo autenticador uma vez por papel.
 * As leituras continuam partindo do App\Models\User (que delega para a identidade); as escritas
 * passam todas por aqui.
 */
class TwoFactorService
{
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(private Google2FA $google2fa) {}

    public function generateSecretKey(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function otpauthUri(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );
    }

    public function verifyTotp(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null) {
            return false;
        }

        return $this->google2fa->verifyKey($user->two_factor_secret, $code);
    }

    /**
     * @return array{matched: bool, remaining_hashes: list<string>}
     */
    public function consumeRecoveryCode(User $user, string $recoveryCode): array
    {
        $hashes = $user->twoFactorRecoveryCodeHashes();
        $normalized = strtoupper(trim($recoveryCode));

        foreach ($hashes as $index => $hash) {
            if (! Hash::check($normalized, $hash)) {
                continue;
            }

            unset($hashes[$index]);

            return [
                'matched' => true,
                'remaining_hashes' => array_values($hashes),
            ];
        }

        return [
            'matched' => false,
            'remaining_hashes' => $hashes,
        ];
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        $plainCodes = [];

        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $plainCodes[] = strtoupper(Str::random(10));
        }

        return $plainCodes;
    }

    /**
     * @param  list<string>  $plainCodes
     * @return list<string>
     */
    public function hashRecoveryCodes(array $plainCodes): array
    {
        return array_map(
            fn (string $code): string => Hash::make(strtoupper($code)),
            $plainCodes,
        );
    }

    /**
     * Guarda o segredo e zera a confirmação: o 2FA fica pendente até o código conferir.
     */
    public function beginSetup(User $user, string $secret): void
    {
        $this->identityOf($user)->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();
    }

    /**
     * @param  list<string>  $hashes
     */
    public function confirmSetup(User $user, array $hashes): void
    {
        $this->identityOf($user)->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => json_encode(array_values($hashes)),
        ])->save();
    }

    /**
     * @param  list<string>  $hashes
     */
    public function persistRecoveryCodeHashes(User $user, array $hashes): void
    {
        $this->identityOf($user)->forceFill([
            'two_factor_recovery_codes' => json_encode(array_values($hashes)),
        ])->save();
    }

    public function clearTwoFactor(User $user): void
    {
        $this->identityOf($user)->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();
    }

    private function identityOf(User $user): Identity
    {
        return $user->loadMissing('identity')->identity;
    }
}
