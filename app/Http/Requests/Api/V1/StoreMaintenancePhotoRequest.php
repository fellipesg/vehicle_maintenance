<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Maintenance;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;

class StoreMaintenancePhotoRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $maintenance = $this->route('maintenance');

        return $maintenance instanceof Maintenance
            && Gate::allows('update', $maintenance);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                File::image(allowSvg: false)
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(5 * 1024),
            ],
            'subject' => ['required', 'in:vehicle,part'],
            'stage' => ['required', 'in:before,after,during'],
            'maintenance_item_id' => ['nullable', 'exists:maintenance_items,id'],
        ];
    }
}
