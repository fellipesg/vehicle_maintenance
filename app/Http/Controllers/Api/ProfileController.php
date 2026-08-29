<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Requests\Api\V1\UploadAvatarRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Support\ApiResponse;
use App\Support\AppStorage;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Profile', weight: 15)]
class ProfileController extends Controller
{
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
