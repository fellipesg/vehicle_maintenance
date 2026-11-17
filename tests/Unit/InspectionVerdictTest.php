<?php

namespace Tests\Unit;

use App\Enums\InspectionFindingStatus;
use App\Enums\InspectionVerdict;
use PHPUnit\Framework\TestCase;

class InspectionVerdictTest extends TestCase
{
    public function test_only_plain_approval_refuses_to_live_with_a_failed_axis(): void
    {
        $this->assertFalse(InspectionVerdict::Approved->toleratesFailedFindings());
        $this->assertTrue(InspectionVerdict::ApprovedWithRestrictions->toleratesFailedFindings());
        $this->assertTrue(InspectionVerdict::Rejected->toleratesFailedFindings());
    }

    public function test_label_for_reads_a_stored_value_and_ignores_the_unknown(): void
    {
        $this->assertSame('Aprovado com restrições', InspectionVerdict::labelFor('approved_with_restrictions'));
        $this->assertNull(InspectionVerdict::labelFor('talvez'));
        $this->assertNull(InspectionVerdict::labelFor(''));
        $this->assertNull(InspectionVerdict::labelFor(null));
    }

    public function test_values_and_options_cover_every_case_in_order(): void
    {
        $this->assertSame(['approved', 'approved_with_restrictions', 'rejected'], InspectionVerdict::values());
        $this->assertSame(InspectionVerdict::values(), array_keys(InspectionVerdict::options()));
    }

    public function test_only_ok_is_a_positive_claim_about_an_axis(): void
    {
        $this->assertTrue(InspectionFindingStatus::Ok->assertsNothingWrong());

        foreach ([InspectionFindingStatus::NotAssessed, InspectionFindingStatus::Attention, InspectionFindingStatus::Fail] as $status) {
            $this->assertFalse($status->assertsNothingWrong(), $status->value);
        }
    }

    public function test_the_minimal_form_reports_attention_and_failure_only(): void
    {
        $this->assertTrue(InspectionFindingStatus::Attention->isReportable());
        $this->assertTrue(InspectionFindingStatus::Fail->isReportable());
        $this->assertFalse(InspectionFindingStatus::Ok->isReportable());
        $this->assertFalse(InspectionFindingStatus::NotAssessed->isReportable());
    }
}
