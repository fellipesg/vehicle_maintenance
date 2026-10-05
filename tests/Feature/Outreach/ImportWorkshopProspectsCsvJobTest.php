<?php

namespace Tests\Feature\Outreach;

use App\Jobs\ImportWorkshopProspectsCsv;
use App\Mail\WorkshopProspectImportFinishedMail;
use App\Models\User;
use App\Models\WorkshopProspect;
use App\Services\Outreach\WorkshopProspectImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class ImportWorkshopProspectsCsvJobTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'cnpj,trade_name,email,phone,cnae,street,number,neighborhood,cep,city,state';

    public function test_it_imports_and_emails_the_counts_to_whoever_uploaded(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['email' => 'admin@revisalog.com.br']);
        WorkshopProspect::factory()->create(['cnpj' => '11111111000111']);
        $csv = implode("\n", [
            self::HEADER,
            '12345678000195,Auto Zé,ze@x.com.br,,4520001,,,,,LONDRINA,PR',
            '11111111000111,Repetida,outra@x.com.br,,4520001,,,,,LONDRINA,PR',
        ])."\n";

        (new ImportWorkshopProspectsCsv($csv, 'oficinas.csv', $admin->id))->handle(app(WorkshopProspectImporter::class));

        $this->assertDatabaseHas('workshop_prospects', ['cnpj' => '12345678000195', 'email' => 'ze@x.com.br']);
        Mail::assertSent(WorkshopProspectImportFinishedMail::class, function (WorkshopProspectImportFinishedMail $mail): bool {
            return $mail->hasTo('admin@revisalog.com.br')
                && $mail->error === null
                && $mail->counts['created'] === 1
                && $mail->counts['duplicates'] === 1
                && $mail->envelope()->subject === 'Importação de oficinas concluída: 1 novas';
        });
    }

    public function test_a_csv_without_the_required_columns_emails_the_reason_and_imports_nothing(): void
    {
        Mail::fake();
        $admin = User::factory()->create();

        (new ImportWorkshopProspectsCsv("nome,telefone\nZé,123\n", 'errado.csv', $admin->id))->handle(app(WorkshopProspectImporter::class));

        $this->assertDatabaseCount('workshop_prospects', 0);
        Mail::assertSent(WorkshopProspectImportFinishedMail::class, fn (WorkshopProspectImportFinishedMail $mail): bool => $mail->hasTo($admin->email)
            && str_contains((string) $mail->error, 'Colunas ausentes')
            && $mail->envelope()->subject === 'Importação de oficinas não concluída');
    }

    public function test_an_unexpected_failure_still_emails_whoever_uploaded(): void
    {
        Mail::fake();
        $admin = User::factory()->create();

        (new ImportWorkshopProspectsCsv('x', 'oficinas.csv', $admin->id))->failed(new RuntimeException('banco caiu'));

        Mail::assertSent(WorkshopProspectImportFinishedMail::class, fn (WorkshopProspectImportFinishedMail $mail): bool => $mail->hasTo($admin->email)
            && $mail->error !== null
            && ! str_contains($mail->error, 'banco caiu'));
    }

    public function test_the_job_runs_once_on_the_database_queue(): void
    {
        $job = new ImportWorkshopProspectsCsv('x', 'oficinas.csv', 1);

        $this->assertSame('database', $job->connection);
        $this->assertSame(1, $job->tries);
        $this->assertSame(300, $job->timeout);
    }

    public function test_the_result_email_renders_the_counts_and_the_error(): void
    {
        $success = (new WorkshopProspectImportFinishedMail('oficinas.csv', ['created' => 1182, 'duplicates' => 15, 'suppressed' => 0, 'customers' => 0, 'invalid' => 0]))->render();
        $this->assertStringContainsString('oficinas.csv', $success);
        $this->assertStringContainsString('1182', $success);
        $this->assertStringContainsString(route('admin.outreach.index'), $success);

        $failure = (new WorkshopProspectImportFinishedMail('errado.csv', null, 'Colunas ausentes no CSV: cnpj.'))->render();
        $this->assertStringContainsString('Colunas ausentes no CSV: cnpj.', $failure);
        $this->assertStringContainsString('não foi importado', $failure);
    }
}
