<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\View\View;

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

        $pins = Workshop::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name')
            ->get(['id', 'name', 'latitude', 'longitude', 'city', 'street', 'number'])
            ->map(fn (Workshop $workshop) => [
                'id' => $workshop->id,
                'name' => $workshop->name,
                'lat' => (float) $workshop->latitude,
                'lng' => (float) $workshop->longitude,
                'city' => $workshop->city,
                'label' => trim("{$workshop->street}, {$workshop->number} — {$workshop->city}"),
            ])
            ->values();

        return view('admin.maps.workshops', [
            'pins' => $pins,
            'onMapCount' => $onMap,
            'missingCount' => $missing,
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
                'label' => trim(collect([$user->street, $user->number, $user->city])->filter()->implode(', ')),
            ])
            ->values();

        return view('admin.maps.users', [
            'pins' => $pins,
            'onMapCount' => $onMap,
            'missingCount' => $missing,
        ]);
    }
}
