<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\View\View;

/**
 * Mapas do admin, outra forma de ver as listas de oficinas e de proprietários. Os pinos que vão
 * para o navegador (JSON) têm só id, name, lat, lng, city e label (.ai/rules/admin.md); o link de
 * cada cadastro é montado no servidor, na lista ao lado do mapa ($pinLinks), e o popup o reaproveita.
 */
class MapController extends Controller
{
    public function workshops(): View
    {
        $onMap = Workshop::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->count();

        $missing = Workshop::query()
            ->where(function ($query) {
                $query->whereNull('latitude')->orWhereNull('longitude');
            })
            ->count();

        $workshops = Workshop::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name')
            ->get(['id', 'user_id', 'name', 'latitude', 'longitude', 'city', 'street', 'number']);

        $pins = $workshops
            ->map(fn (Workshop $workshop) => [
                'id' => $workshop->id,
                'name' => $workshop->name,
                'lat' => (float) $workshop->latitude,
                'lng' => (float) $workshop->longitude,
                'city' => $workshop->city,
                'label' => self::addressLabel($workshop->street, $workshop->number, $workshop->city),
            ])
            ->values();

        return view('admin.maps.workshops', [
            'pins' => $pins,
            'pinLinks' => $workshops
                ->filter(fn (Workshop $workshop): bool => $workshop->user_id !== null)
                ->mapWithKeys(fn (Workshop $workshop): array => [$workshop->id => route('admin.users.show', $workshop->user_id)])
                ->all(),
            'onMapCount' => $onMap,
            'missingCount' => $missing,
            'missingUrl' => route('admin.workshops.index', ['localizacao' => 'sem-coordenadas']),
        ]);
    }

    public function users(): View
    {
        $onMap = User::query()
            ->where('user_type', 'user')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->count();

        $missing = User::query()
            ->where('user_type', 'user')
            ->where(function ($query) {
                $query->whereNull('latitude')->orWhereNull('longitude');
            })
            ->count();

        $pins = User::query()
            ->where('user_type', 'user')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name')
            ->get(['id', 'name', 'latitude', 'longitude', 'city', 'street', 'number'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'lat' => (float) $user->latitude,
                'lng' => (float) $user->longitude,
                'city' => $user->city ?? '',
                'label' => self::addressLabel($user->street, $user->number, $user->city),
            ])
            ->values();

        return view('admin.maps.users', [
            'pins' => $pins,
            'pinLinks' => $pins->mapWithKeys(fn (array $pin): array => [$pin['id'] => route('admin.users.show', $pin['id'])])->all(),
            'onMapCount' => $onMap,
            'missingCount' => $missing,
            'missingUrl' => route('admin.users.index', ['perfil' => 'proprietarios', 'localizacao' => 'sem-coordenadas']),
        ]);
    }

    /**
     * "Rua, número — cidade", sem separador sobrando quando algum pedaço está vazio.
     */
    private static function addressLabel(?string $street, ?string $number, ?string $city): string
    {
        $streetLine = collect([$street, $number])
            ->map(fn (?string $part): string => trim((string) $part))
            ->filter()
            ->implode(', ');

        return collect([$streetLine, trim((string) $city)])->filter()->implode(' — ');
    }
}
