<?php

namespace App\Support\Maintenance;

use App\Enums\Portal;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use Closure;
use Illuminate\Support\Facades\Route;

/**
 * Links padrão dos componentes de domínio (x-vehicle.*, x-maintenance.*, x-provenance-*) em cada
 * portal. Quem chama pode trocar por um Closure, por um padrão com "{id}" ou desligar com false.
 *
 * - Proprietário: user.maintenances.show / user.vehicles.show.
 * - Lojista: garage.maintenances.show (quando a rota existir) / garage.vehicles.show.
 * - Oficina: workshop.maintenances.show só para a OS da própria oficina; a oficina não tem ficha.
 * - Admin: admin.maintenances.show (quando existir) / admin.vehicles.show.
 * - Sem portal (busca pública): nenhum link.
 */
final class MaintenanceLinks
{
    public static function portal(Portal|string|null $portal): ?Portal
    {
        if ($portal instanceof Portal) {
            return $portal;
        }

        return is_string($portal) && $portal !== '' ? Portal::tryFrom($portal) : null;
    }

    public static function detailUrl(Maintenance $maintenance, Portal|string|null $portal, ?User $viewer = null): ?string
    {
        return match (self::portal($portal)) {
            Portal::Owner => self::routeOrNull('user.maintenances.show', $maintenance),
            Portal::Dealer => self::routeOrNull('garage.maintenances.show', $maintenance),
            Portal::Workshop => self::workshopDetailUrl($maintenance, $viewer ?? auth()->user()),
            Portal::Admin => self::routeOrNull('admin.maintenances.show', $maintenance),
            null => null,
        };
    }

    public static function vehicleUrl(Vehicle $vehicle, Portal|string|null $portal): ?string
    {
        $routeName = self::portal($portal)?->vehicleRoute();

        return $routeName === null ? null : self::routeOrNull($routeName, $vehicle);
    }

    /**
     * Resolve a prop de link de um componente num Closure(Model): ?string.
     *
     * - null: o padrão do portal ($default).
     * - false ou '': sem link.
     * - string com "{id}": troca {id} pelo id do modelo ("/usuario/manutencoes/{id}", "#manutencao-{id}").
     * - Closure: chamado com o modelo.
     *
     * @param  Closure(mixed): ?string  $default
     * @return Closure(mixed): ?string
     */
    public static function resolver(Closure|string|bool|null $override, Closure $default): Closure
    {
        if ($override === null || $override === true) {
            return $default;
        }

        if ($override === false || $override === '') {
            return static fn (mixed $model): ?string => null;
        }

        if ($override instanceof Closure) {
            return static function (mixed $model) use ($override): ?string {
                $url = $override($model);

                return is_string($url) && $url !== '' ? $url : null;
            };
        }

        return static fn (mixed $model): ?string => str_replace('{id}', (string) data_get($model, 'id'), $override);
    }

    private static function workshopDetailUrl(Maintenance $maintenance, mixed $viewer): ?string
    {
        if (! $viewer instanceof User || ! $viewer->isWorkshop()) {
            return null;
        }

        $workshopId = $viewer->workshop?->id;

        if ($workshopId === null || (int) $maintenance->workshop_id !== (int) $workshopId) {
            return null;
        }

        return self::routeOrNull('workshop.maintenances.show', $maintenance);
    }

    private static function routeOrNull(string $name, mixed $parameter): ?string
    {
        return Route::has($name) ? route($name, $parameter) : null;
    }
}
