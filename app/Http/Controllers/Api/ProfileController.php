<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\EndsDeletedAccountSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeleteAccountRequest;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Requests\Api\V1\UploadAvatarRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\User\DeleteUserAccount;
use App\Support\ApiResponse;
use App\Support\AppStorage;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Profile', weight: 15)]
class ProfileController extends Controller
{
    use EndsDeletedAccountSession;

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());
        $user->load('currentVehicles');

        return ApiResponse::success(new UserResource($user->fresh()->load('currentVehicles')), 'Profile updated successfully');
    }

    #[Endpoint(
        title: 'Upload avatar',
        description: 'Multipart form upload. Field name: `avatar` (image: jpg, jpeg, png, webp; max 5 MB).',
    )]
    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $request->user();
        $file = $request->file('avatar');
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $fileName = $user->id.'_'.time().'.'.$extension;
        $filePath = $file->storeAs('avatars', $fileName, AppStorage::diskName());

        $this->deleteStoredAvatar($user->avatar);

        $user->update(['avatar' => $filePath]);
        $user->load('currentVehicles');

        return ApiResponse::success(new UserResource($user->fresh()->load('currentVehicles')), 'Avatar uploaded successfully');
    }

    /**
     * Delete the authenticated account and associated personal data.
     *
     * Requires `current_password`: the token alone must not be enough to destroy the account.
     * Accounts that sign in only through Google or Apple never chose a password and use the
     * signed-link flow at `/excluir-conta` instead.
     *
     * Vehicle maintenance history remains attached to the chassis/VIN.
     */
    #[Endpoint(
        title: 'Delete account',
        description: 'Reauthentication required. Send the account password in `current_password`.',
    )]
    public function destroy(DeleteAccountRequest $request, DeleteUserAccount $deleteUserAccount): JsonResponse
    {
        $user = $request->user();

        $deleteUserAccount->handle($user);

        // Chamada do portal pela sessão (EnsureFrontendRequestsAreStateful): o token já saiu, a
        // sessão também tem de sair.
        $this->endWebSessionOf($request, $user);

        return ApiResponse::success(message: 'Account deleted successfully');
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
