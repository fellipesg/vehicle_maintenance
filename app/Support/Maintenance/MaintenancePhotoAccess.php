<?php

namespace App\Support\Maintenance;

use App\Features\MaintenancePhotos;
use App\Models\Garage;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Model;
use Laravel\Pennant\Feature;

/**
 * "Este usuário tem as fotos das manutenções?" — a única resposta do sistema. Policy, resources,
 * telas, PDF e API perguntam aqui.
 *
 * A funcionalidade é ligada pelo admin em um destes escopos do Pennant, e o usuário a tem se
 * qualquer um estiver ligado:
 *  - o próprio usuário (proprietário, ou qualquer conta);
 *  - a oficina dele (Workshop, quando a conta é de oficina);
 *  - a loja dele (Garage, quando a conta é de lojista).
 *
 * Oficina e loja têm uma linha por conta (user_id), então ligar a oficina equivale a ligar a conta
 * dela; o escopo próprio existe para o admin tratar tudo pela tela que preferir (usuário, oficinas
 * ou lojas) e para o dia em que uma oficina ou loja tiver mais de um usuário. Sem usuário
 * (visitante) a resposta é não.
 */
final class MaintenancePhotoAccess
{
    public static function allows(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        foreach (self::scopesOf($user) as $scope) {
            if (Feature::for($scope)->active(MaintenancePhotos::class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Os models em que a funcionalidade vale para este usuário: ele mesmo e a oficina ou a loja da
     * conta (relações já carregadas ficam em memória).
     *
     * @return list<Model>
     */
    public static function scopesOf(User $user): array
    {
        $scopes = [$user];

        // loadMissing é carga explícita: o preventLazyLoading barraria o acesso direto à relação.
        if ($user->isWorkshop() && $user->loadMissing('workshop')->workshop instanceof Workshop) {
            $scopes[] = $user->workshop;
        }

        if ($user->isGarage() && $user->loadMissing('garage')->garage instanceof Garage) {
            $scopes[] = $user->garage;
        }

        return $scopes;
    }

    /**
     * Valor da funcionalidade só neste model (usuário, oficina ou loja), sem somar os outros: é o
     * que o admin liga e desliga.
     */
    public static function isActiveFor(Model $scope): bool
    {
        return (bool) Feature::for($scope)->active(MaintenancePhotos::class);
    }

    /**
     * Carrega de uma vez o valor de vários models (listas do admin), sem uma consulta por linha.
     *
     * @param  iterable<int, Model>  $scopes
     */
    public static function preload(iterable $scopes): void
    {
        $list = [];

        foreach ($scopes as $scope) {
            $list[] = $scope;
        }

        if ($list !== []) {
            Feature::for($list)->load([MaintenancePhotos::class]);
        }
    }

    public static function set(Model $scope, bool $enabled): void
    {
        $enabled
            ? Feature::for($scope)->activate(MaintenancePhotos::class)
            : Feature::for($scope)->deactivate(MaintenancePhotos::class);
    }
}
