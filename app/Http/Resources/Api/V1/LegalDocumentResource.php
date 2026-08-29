<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LegalDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'version' => $this->resource['version'] ?? config('legal.terms_version'),
            'content' => $this->resource['content'] ?? config('legal.terms_of_use'),
        ];
    }
}
