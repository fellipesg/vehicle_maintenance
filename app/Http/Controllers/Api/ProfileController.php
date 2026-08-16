<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AppStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\File;

class ProfileController extends Controller
{
    /**
     * @var list<string>
     */
    private const FORBIDDEN_FIELDS = [
        'email',
        'password',
        'password_confirmation',
        'user_type',
        'is_admin',
    ];

    public function update(Request $request): JsonResponse
    {
        $forbidden = collect(self::FORBIDDEN_FIELDS)
            ->filter(fn (string $field): bool => $request->has($field));

        if ($forbidden->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'errors' => $forbidden->mapWithKeys(fn (string $field): array => [
                    $field => ["The {$field} field cannot be updated here."],
                ])->all(),
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'postal_code' => 'nullable|string|max:10',
            'street' => 'nullable|string|max:255',
            'number' => 'nullable|string|max:20',
            'complement' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:2',
            'country' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $user->update($validator->validated());
        $user->load('currentVehicles');

        return response()->json([
            'success' => true,
            'data' => $user->fresh()->load('currentVehicles'),
            'message' => 'Profile updated successfully',
        ]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'avatar' => [
                'required',
                File::image(allowSvg: false)
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(5 * 1024),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $file = $request->file('avatar');
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $fileName = $user->id.'_'.time().'.'.$extension;
        $filePath = $file->storeAs('avatars', $fileName, AppStorage::diskName());

        $this->deleteStoredAvatar($user->avatar);

        $user->update(['avatar' => $filePath]);
        $user->load('currentVehicles');

        return response()->json([
            'success' => true,
            'data' => $user->fresh()->load('currentVehicles'),
            'message' => 'Avatar uploaded successfully',
        ]);
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
