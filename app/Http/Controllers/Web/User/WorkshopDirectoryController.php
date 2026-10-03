<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use App\Models\Workshop;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkshopDirectoryController extends Controller
{
    public const PER_PAGE = 24;

    /**
     * Oficinas da rede, renderizadas no servidor. A busca (?busca=, ou ?search= dos links antigos)
     * procura no nome, na cidade e no bairro, como /api/v1/workshops, sem diferenciar maiúsculas.
     */
    public function index(Request $request): View
    {
        $search = $this->searchTerm($request);

        $workshops = Workshop::query()
            ->when($search !== '', function ($query) use ($search): void {
                $needle = '%'.mb_strtolower($search).'%';

                $query->where(function ($query) use ($needle): void {
                    $query->whereRaw('lower(name) like ?', [$needle])
                        ->orWhereRaw('lower(city) like ?', [$needle])
                        ->orWhereRaw('lower(neighborhood) like ?', [$needle]);
                });
            })
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('user.workshops.index', [
            'workshops' => $workshops,
            'search' => $search,
        ]);
    }

    private function searchTerm(Request $request): string
    {
        $value = $request->query('busca', $request->query('search', ''));

        return is_string($value) ? mb_substr(trim($value), 0, 100) : '';
    }
}
