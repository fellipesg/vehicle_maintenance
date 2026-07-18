<?php

namespace App\Services\Crlv;

use RuntimeException;

class CrlvExerciseValidator
{
    public function isAcceptable(?int $exerciseYear, ?int $referenceYear = null): bool
    {
        if ($exerciseYear === null) {
            return false;
        }

        $referenceYear ??= (int) date('Y');

        return $exerciseYear >= $referenceYear - 1 && $exerciseYear <= $referenceYear + 1;
    }

    public function assertAcceptable(?int $exerciseYear, ?int $referenceYear = null): void
    {
        if ($this->isAcceptable($exerciseYear, $referenceYear)) {
            return;
        }

        $referenceYear ??= (int) date('Y');
        $minimum = $referenceYear - 1;

        throw new RuntimeException(
            "O CRLV-e precisa ser do exercício {$minimum} ou {$referenceYear}. "
            .'Exporte um documento atualizado pelo app Carteira Digital de Trânsito (CDT).'
        );
    }
}
