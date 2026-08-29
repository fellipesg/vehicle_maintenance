<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;

class UploadVehicleCoverRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $vehicle = \App\Models\Vehicle::find($this->route('id'));

        return $vehicle !== null && Gate::allows('update', $vehicle);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cover' => [
                'required',
                File::image(allowSvg: false)
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(5 * 1024),
            ],
        ];
    }
}
