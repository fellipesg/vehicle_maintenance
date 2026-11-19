<?php

namespace Tests\Unit;

use App\Enums\InspectionFinding;
use App\Enums\InspectionFindingStatus;
use App\Support\Inspection\InspectionFindings;
use PHPUnit\Framework\TestCase;

class InspectionFindingsTest extends TestCase
{
    public function test_normalize_always_returns_every_axis_as_not_assessed_by_default(): void
    {
        $findings = InspectionFindings::normalize([]);

        $this->assertSame(InspectionFinding::values(), array_keys($findings));

        foreach ($findings as $entry) {
            $this->assertSame(InspectionFindingStatus::NotAssessed->value, $entry['status']);
            $this->assertNull($entry['note']);
        }
    }

    public function test_normalize_keeps_known_axes_and_drops_unknown_keys(): void
    {
        $findings = InspectionFindings::normalize([
            'structural' => ['status' => 'fail', 'note' => 'Longarina amassada.'],
            'astrologia' => ['status' => 'fail', 'note' => 'Mercúrio retrógrado.'],
        ]);

        $this->assertSame('fail', $findings['structural']['status']);
        $this->assertSame('Longarina amassada.', $findings['structural']['note']);
        $this->assertArrayNotHasKey('astrologia', $findings);
    }

    public function test_an_unknown_status_becomes_not_assessed_instead_of_passing_through(): void
    {
        $findings = InspectionFindings::normalize([
            'recall' => ['status' => 'aprovadissimo'],
            'paint' => ['status' => 42],
        ]);

        $this->assertSame(InspectionFindingStatus::NotAssessed->value, $findings['recall']['status']);
        $this->assertSame(InspectionFindingStatus::NotAssessed->value, $findings['paint']['status']);
    }

    public function test_a_note_is_trimmed_truncated_and_empty_becomes_null(): void
    {
        $findings = InspectionFindings::normalize([
            'odometer' => ['status' => 'attention', 'note' => '   leitura divergente   '],
            'recall' => ['status' => 'ok', 'note' => '    '],
            'paint' => ['status' => 'attention', 'note' => str_repeat('a', InspectionFindings::MAX_NOTE_LENGTH + 50)],
        ]);

        $this->assertSame('leitura divergente', $findings['odometer']['note']);
        $this->assertNull($findings['recall']['note']);
        $this->assertSame(InspectionFindings::MAX_NOTE_LENGTH, mb_strlen((string) $findings['paint']['note']));
    }

    public function test_normalize_survives_input_that_is_not_an_array(): void
    {
        foreach ([null, 'texto', 7, ['structural' => 'reprovado']] as $input) {
            $findings = InspectionFindings::normalize($input);

            $this->assertSame(InspectionFinding::values(), array_keys($findings));
            $this->assertSame(InspectionFindingStatus::NotAssessed->value, $findings['structural']['status']);
        }
    }

    public function test_failing_lists_only_the_rejected_axes_and_reportable_adds_attention(): void
    {
        $findings = InspectionFindings::normalize([
            'identification' => ['status' => 'fail'],
            'paint' => ['status' => 'attention'],
            'recall' => ['status' => 'ok'],
            'records' => ['status' => 'not_assessed'],
        ]);

        $this->assertSame([InspectionFinding::Identification], InspectionFindings::failing($findings));
        $this->assertSame(
            [InspectionFinding::Paint, InspectionFinding::Identification],
            InspectionFindings::reportable($findings),
        );
    }

    public function test_status_and_note_readers_tolerate_a_missing_axis(): void
    {
        $this->assertSame(
            InspectionFindingStatus::NotAssessed,
            InspectionFindings::statusOf(['paint' => ['status' => 'ok']], InspectionFinding::Structural),
        );
        $this->assertNull(InspectionFindings::noteOf(null, InspectionFinding::Structural));
    }

    public function test_every_axis_has_a_label_and_a_meaning(): void
    {
        foreach (InspectionFinding::cases() as $axis) {
            $this->assertNotSame('', $axis->label(), $axis->value.' sem rótulo');
            $this->assertNotSame('', $axis->meaning(), $axis->value.' sem explicação');
        }
    }
}
