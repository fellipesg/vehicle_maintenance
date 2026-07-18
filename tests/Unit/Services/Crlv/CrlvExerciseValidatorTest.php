<?php

namespace Tests\Unit\Services\Crlv;

use App\Services\Crlv\CrlvExerciseValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrlvExerciseValidatorTest extends TestCase
{
    #[DataProvider('exerciseYearProvider')]
    public function test_validates_exercise_year(int $exerciseYear, int $referenceYear, bool $expected): void
    {
        $validator = new CrlvExerciseValidator;

        $this->assertSame($expected, $validator->isAcceptable($exerciseYear, $referenceYear));
    }

    public static function exerciseYearProvider(): array
    {
        return [
            'current_year' => [2026, 2026, true],
            'previous_year' => [2025, 2026, true],
            'too_old' => [2024, 2026, false],
            'future_year' => [2027, 2026, true],
        ];
    }
}
