<?php

namespace Tests\Feature\Mail;

use App\Mail\CrlvImportFailureMail;
use Tests\TestCase;

/**
 * Aviso interno de CRLV-e não lido: o contexto sai numa tabela com rótulos em pt-BR, sem as linhas
 * vazias e sem quebrar a tabela quando um valor tem "|".
 */
class CrlvImportFailureMailTest extends TestCase
{
    public function test_context_rows_use_portuguese_labels_and_skip_empty_values(): void
    {
        $mail = new CrlvImportFailureMail('Estrutura do CRLV-e não reconhecida.', [
            'origem' => 'user.vehicles.create',
            'arquivo' => 'CRLV|e.pdf',
            'tamanho_kb' => 120,
            'usuario_id' => 7,
            'usuario_email' => null,
            'detran_uf' => 'MS',
        ]);

        $this->assertSame([
            ['label' => 'Origem', 'value' => 'user.vehicles.create'],
            ['label' => 'Arquivo', 'value' => 'CRLV/e.pdf'],
            ['label' => 'Tamanho (KB)', 'value' => '120'],
            ['label' => 'ID do usuário', 'value' => '7'],
            ['label' => 'Detran uf', 'value' => 'MS'],
        ], $mail->contextRows());
    }

    public function test_context_renders_as_a_table(): void
    {
        $html = (new CrlvImportFailureMail('Estrutura do CRLV-e não reconhecida.', [
            'arquivo' => 'CRLV-e.pdf',
            'usuario_email' => 'ana@example.com',
        ]))->render();

        $this->assertMatchesRegularExpression('/<th[^>]*>Campo<\/th>\s*<th[^>]*>Valor<\/th>/', $html);
        $this->assertMatchesRegularExpression('/<td[^>]*>Arquivo<\/td>\s*<td[^>]*>CRLV-e\.pdf<\/td>/', $html);
        $this->assertMatchesRegularExpression('/<td[^>]*>E-mail do usuário<\/td>\s*<td[^>]*>ana@example\.com<\/td>/u', $html);
        $this->assertStringNotContainsString('Usuario email', $html);
        $this->assertStringNotContainsString('Tamanho (KB)', $html);
    }

    public function test_mail_without_context_has_no_table(): void
    {
        $html = (new CrlvImportFailureMail('Arquivo corrompido.'))->render();

        $this->assertStringContainsString('Arquivo corrompido.', $html);
        $this->assertStringNotContainsString('<table class="table"', $html);
        $this->assertStringNotContainsString('<th', $html);
    }
}
