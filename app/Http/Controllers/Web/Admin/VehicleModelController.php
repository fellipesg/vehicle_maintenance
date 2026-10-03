<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Services\VehicleCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleModelController extends Controller
{
    /**
     * Error bag do diálogo "Novo modelo" da página da marca.
     */
    public const CREATE_BAG = 'createModel';

    /**
     * Error bag do diálogo de edição de um modelo: cada linha tem o seu, então o erro e o texto
     * digitado voltam só no diálogo que foi enviado.
     */
    public static function updateBag(VehicleModel|int $model): string
    {
        return 'updateModel'.($model instanceof VehicleModel ? $model->id : $model);
    }

    public function store(Request $request, VehicleBrand $brand): RedirectResponse
    {
        $data = $request->validateWithBag(self::CREATE_BAG, [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('vehicle_models', 'name')->where('vehicle_brand_id', $brand->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $brand->models()->create([
            'name' => trim($data['name']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        VehicleCatalogService::clearCache();

        return redirect()->route('admin.brands.show', $brand)
            ->with('success', 'Modelo cadastrado.');
    }

    public function update(Request $request, VehicleModel $model): RedirectResponse
    {
        $data = $request->validateWithBag(self::updateBag($model), [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('vehicle_models', 'name')
                    ->where('vehicle_brand_id', $model->vehicle_brand_id)
                    ->ignore($model->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $model->update([
            'name' => trim($data['name']),
            'is_active' => $request->boolean('is_active'),
        ]);

        VehicleCatalogService::clearCache();

        return redirect()->route('admin.brands.show', $model->brand)
            ->with('success', 'Modelo atualizado.');
    }

    public function destroy(VehicleModel $model): RedirectResponse
    {
        $brand = $model->brand;
        $model->delete();

        VehicleCatalogService::clearCache();

        return redirect()->route('admin.brands.show', $brand)
            ->with('success', 'Modelo excluído.');
    }
}
