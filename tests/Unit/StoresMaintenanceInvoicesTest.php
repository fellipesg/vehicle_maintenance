<?php

namespace Tests\Unit;

use App\Http\Controllers\Web\Concerns\StoresMaintenanceInvoices;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StoresMaintenanceInvoicesTest extends TestCase
{
    public function test_ini_size_error_points_to_composer_run_dev(): void
    {
        $message = $this->messageFor(UPLOAD_ERR_INI_SIZE);

        $this->assertSame(
            'O arquivo excede o limite de upload do PHP (2 MB no servidor local). Rode o servidor com composer run dev, que já sobe com 20 MB.',
            $message,
        );
    }

    public function test_form_size_error_uses_the_same_message(): void
    {
        $this->assertSame(
            $this->messageFor(UPLOAD_ERR_INI_SIZE),
            $this->messageFor(UPLOAD_ERR_FORM_SIZE),
        );
    }

    private function messageFor(int $error): string
    {
        $file = new UploadedFile(__FILE__, 'nota.pdf', 'application/pdf', $error, true);

        $subject = new class
        {
            use StoresMaintenanceInvoices;

            public function message(UploadedFile $file): string
            {
                return $this->invoiceUploadErrorMessage($file);
            }
        };

        return $subject->message($file);
    }
}
