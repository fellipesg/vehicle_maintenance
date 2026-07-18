<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\VehicleBrand;
use App\Services\VehicleCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VehicleBrandController extends Controller
{
    public function index(): View
    {
        $brands = VehicleBrand::withCount('models')
            ->orderBy('name')
            ->get();

        return view('admin.brands.index', compact('brands'));
    }

    public function create(): View
    {
        return view('admin.brands.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:vehicle_brands,name'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        VehicleBrand::create([
            'name' => trim($data['name']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        VehicleCatalogService::clearCache();

        return redirect()->route('admin.brands.index')
            ->with('success', 'Marca cadastrada com sucesso!');
    }

    public function show(VehicleBrand $brand): View
    {
        $brand->load(['models' => fn ($query) => $query->orderBy('name')]);

        return view('admin.brands.show', compact('brand'));
    }

    public function edit(VehicleBrand $brand): View
    {
        return view('admin.brands.edit', compact('brand'));
    }

    public function update(Request $request, VehicleBrand $brand): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:vehicle_brands,name,' . $brand->id],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $brand->update([
            'name' => trim($data['name']),
            'is_active' => $request->boolean('is_active'),
        ]);

        VehicleCatalogService::clearCache();

        return redirect()->route('admin.brands.show', $brand)
            ->with('success', 'Marca atualizada com sucesso!');
    }

    public function destroy(VehicleBrand $brand): RedirectResponse
    {
        $brand->delete();

        VehicleCatalogService::clearCache();

        return redirect()->route('admin.brands.index')
            ->with('success', 'Marca removida com sucesso!');
    }
}
