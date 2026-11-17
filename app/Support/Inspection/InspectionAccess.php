<?php

namespace App\Support\Inspection;

use App\Features\VehicleInspections;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Model;
use Laravel\Pennant\Feature;

/**
 * "Esta conta pode emitir laudo de vistoria cautelar?" — a única resposta do sistema. Policy,
 * formulário, rotas e API perguntam aqui.
 *
 * Escopos: o próprio usuário e a oficina dele. Loja não entra: o lojista é quem *lê* o laudo na hora
 * de comprar, e deixá-lo atestar o carro que ele mesmo vende é o conflito de interesse que o laudo
 * existe para resolver. Ver quem emitiu continua sendo regra de histórico, não desta flag.
 */
final class InspectionAccess
{
    public static function allowsIssuing(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        foreach (self::scopesOf($user) as $scope) {
            if (Feature::for($scope)->active(VehicleInspections::class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Os models em que a funcionalidade vale para este usuário: ele mesmo e a oficina da conta.
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

        return $scopes;
    }

    /**
     * Valor da funcionalidade só neste model, sem somar os outros: é o que o admin liga e desliga.
     */
    public static function isActiveFor(Model $scope): bool
    {
        return (bool) Feature::for($scope)->active(VehicleInspections::class);
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
            Feature::for($list)->load([VehicleInspections::class]);
        }
    }

    public static function set(Model $scope, bool $enabled): void
    {
        $enabled
            ? Feature::for($scope)->activate(VehicleInspections::class)
            : Feature::for($scope)->deactivate(VehicleInspections::class);
    }
}
