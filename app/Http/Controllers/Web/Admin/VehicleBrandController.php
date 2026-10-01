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
    /**
     * Colunas ordenáveis (?ordenar=) => coluna ou contagem no SQL.
     *
     * @var array<string, string>
     */
    private const SORTS = [
        'marca' => 'name',
        'modelos' => 'models_count',
    ];

    /**
     * Marcas do catálogo: busca por nome (?q=), ordenação e 25 por página.
     */
    public function index(Request $request): View
    {
        $search = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $sort = is_string($request->query('ordenar')) && array_key_exists($request->query('ordenar'), self::SORTS)
            ? $request->query('ordenar')
            : 'marca';
        $direction = in_array($request->query('direcao'), ['asc', 'desc'], true)
            ? $request->query('direcao')
            : ($sort === 'modelos' ? 'desc' : 'asc');

        $brands = VehicleBrand::query()
            ->withCount('models')
            ->when($search !== '', fn ($query) => $query->whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%']))
            ->orderBy(self::SORTS[$sort], $direction)
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.brands.index', [
            'brands' => $brands,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
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
            ->with('success', 'Marca cadastrada.');
    }

    /**
     * Marca e modelos: filtro de modelos pelo nome (?q=). Criar e editar modelo acontece em
     * diálogos na própria página, cada um com o seu error bag (VehicleModelController).
     */
    public function show(Request $request, VehicleBrand $brand): View
    {
        $search = is_string($request->query('q')) ? trim($request->query('q')) : '';

        $brand->loadCount('models');
        $brand->load(['models' => fn ($query) => $query
            ->when($search !== '', fn ($models) => $models->whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%']))
            ->orderBy('name')]);

        return view('admin.brands.show', [
            'brand' => $brand,
            'search' => $search,
        ]);
    }

    public function edit(VehicleBrand $brand): View
    {
        return view('admin.brands.edit', compact('brand'));
    }

    public function update(Request $request, VehicleBrand $brand): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:vehicle_brands,name,'.$brand->id],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $brand->update([
            'name' => trim($data['name']),
            'is_active' => $request->boolean('is_active'),
        ]);

        VehicleCatalogService::clearCache();

        return redirect()->route('admin.brands.show', $brand)
            ->with('success', 'Marca atualizada.');
    }

    public function destroy(VehicleBrand $brand): RedirectResponse
    {
        $brand->delete();

        VehicleCatalogService::clearCache();

        return redirect()->route('admin.brands.index')
            ->with('success', 'Marca excluída.');
    }
}
