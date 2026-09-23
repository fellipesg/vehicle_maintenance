<?php

namespace App\Services\User;

use App\Models\User;
use App\Support\AppStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeleteUserAccount
{
    /**
     * Anonymize the requesting user's personal data without deleting the row.
     *
     * Hard-deleting the user would cascade-delete maintenances they registered
     * (`maintenances.user_id`), wiping VIN history that other owners still need.
     * Vehicle records, plates, and every maintenance on the chassis stay put;
     * only this user's PII, tokens, FCM devices, and vehicle links are removed.
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->deleteStoredAvatar($user->avatar);

            $user->fcmTokens()->delete();
            $user->tokens()->delete();
            $user->vehicles()->detach();

            $user->forceFill([
                'name' => 'Conta excluída',
                'email' => $this->deletedEmail($user),
                'password' => Str::password(32),
                'phone' => null,
                'document' => null,
                'postal_code' => null,
                'street' => null,
                'number' => null,
                'complement' => null,
                'city' => null,
                'state' => null,
                'country' => 'Brasil',
                'latitude' => null,
                'longitude' => null,
                'provider' => null,
                'provider_id' => null,
                'avatar' => null,
                'remember_token' => null,
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_recovery_codes' => null,
                'is_admin' => false,
                'subscription_active' => false,
            ])->save();
        });
    }

    private function deletedEmail(User $user): string
    {
        return 'deleted.'.$user->id.'.'.Str::lower(Str::random(8)).'@deleted.revisalog.invalid';
    }

    private function deleteStoredAvatar(?string $avatar): void
    {
        if ($avatar === null || $avatar === '') {
            return;
        }

        if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
            return;
        }

        if (AppStorage::disk()->exists($avatar)) {
            AppStorage::disk()->delete($avatar);
        }
    }
}
