<?php

namespace App\Support\Inspection;

use App\Features\VehicleInspections;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Support\Vehicle\IssuerIndependence;
use Illuminate\Database\Eloquent\Model;
use Laravel\Pennant\Feature;

/**
 * "Esta conta pode emitir laudo de vistoria cautelar?" — a única resposta do sistema. Policy,
 * formulário, rotas e API perguntam aqui.
 *
 * Escopos: o próprio usuário e a oficina dele. Loja não entra: o lojista é quem *lê* o laudo na hora
 * de comprar, e deixá-lo atestar o carro que ele mesmo vende é o conflito de interesse que o laudo
 * existe para resolver. Ver quem emitiu continua sendo regra de histórico, não desta flag.
 *
 * Aquele conflito tinha metade escrita: barrava o **papel** (lojista não emite) e não o **dono do
 * veículo**. `allowsIssuingAbout` fecha a outra metade — nem a oficina atesta o carro do tenant dela
 * (App\Support\Vehicle\IssuerIndependence). Quem sabe sobre qual veículo o laudo sai pergunta por
 * ali; `allowsIssuing` continua respondendo a pergunta sem veículo (menu, botão, lista).
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
     * Emitir laudo **sobre este veículo**: a flag da conta e a independência de quem atesta. Veículo
     * nulo é a pergunta genérica (o veículo ainda vai nascer do chassi, e carro novo não tem dono):
     * cai em `allowsIssuing`.
     */
    public static function allowsIssuingAbout(?User $user, ?Vehicle $vehicle): bool
    {
        if (! self::allowsIssuing($user)) {
            return false;
        }

        if ($vehicle === null) {
            return true;
        }

        return IssuerIndependence::workshopMayAttest(
            $user?->loadMissing('workshop')->workshop?->id,
            $vehicle,
        );
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
