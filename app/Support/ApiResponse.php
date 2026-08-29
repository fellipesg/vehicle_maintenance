<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResponse
{
    public static function success(mixed $data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        return response()->json(self::envelope(
            success: true,
            data: $data,
            message: $message,
        ), $code);
    }

    public static function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    /**
     * @param  class-string<JsonResource>  $resourceClass
     */
    public static function paginated(LengthAwarePaginator $paginator, string $resourceClass, ?string $message = null): JsonResponse
    {
        $collection = $resourceClass::collection($paginator);
        $payload = $collection->response()->getData(true);

        return response()->json(self::envelope(
            success: true,
            data: $payload['data'] ?? [],
            message: $message,
            meta: $payload['meta'] ?? null,
            links: $payload['links'] ?? null,
        ));
    }

    /**
     * @param  array<string, list<string>>|null  $errors
     */
    public static function error(string $message, int $code = 400, ?array $errors = null): JsonResponse
    {
        return response()->json(self::envelope(
            success: false,
            message: $message,
            errors: $errors,
        ), $code);
    }

    /**
     * @param  array<string, list<string>>|\Illuminate\Support\MessageBag  $errors
     */
    public static function validation($errors): JsonResponse
    {
        $normalized = $errors instanceof \Illuminate\Support\MessageBag
            ? $errors->toArray()
            : $errors;

        return self::error('The given data was invalid.', 422, $normalized);
    }

    /**
     * @return array<string, mixed>
     */
    private static function envelope(
        bool $success,
        mixed $data = null,
        ?string $message = null,
        mixed $meta = null,
        mixed $links = null,
        ?array $errors = null,
    ): array {
        $payload = ['success' => $success];

        if ($data !== null) {
            $payload['data'] = $data instanceof JsonResource ? $data->resolve() : $data;
        }

        if ($message !== null) {
            $payload['message'] = $message;
        }

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        if ($links !== null) {
            $payload['links'] = $links;
        }

        return $payload;
    }
}
