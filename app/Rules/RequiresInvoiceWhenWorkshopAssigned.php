<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class RequiresInvoiceWhenWorkshopAssigned implements ValidationRule
{
    public function __construct(
        private readonly ?int $workshopId,
        private readonly bool $isWorkshopPortal = false,
        private readonly int $existingInvoiceCount = 0,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->isWorkshopPortal || $this->workshopId === null) {
            return;
        }

        $uploads = is_array($value) ? $value : [];
        $newUploads = array_filter($uploads, fn ($file) => $file instanceof UploadedFile);

        if ($this->existingInvoiceCount > 0 || count($newUploads) > 0) {
            return;
        }

        $fail('Informe ao menos uma nota fiscal (PDF ou XML) ao vincular uma oficina cadastrada.');
    }
}
