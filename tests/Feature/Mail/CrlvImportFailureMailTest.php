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
            'extensao' => 'pdf',
            'tamanho_kb' => 120,
            'hash_arquivo' => 'e3b0c44298fc',
            'usuario_id' => 7,
            'usuario_email' => null,
            'detran_uf' => 'MS|MT',
        ]);

        $this->assertSame([
            ['label' => 'Origem', 'value' => 'user.vehicles.create'],
            ['label' => 'Extensão', 'value' => 'pdf'],
            ['label' => 'Tamanho (KB)', 'value' => '120'],
            ['label' => 'Hash do arquivo', 'value' => 'e3b0c44298fc'],
            ['label' => 'ID do usuário', 'value' => '7'],
            ['label' => 'Detran uf', 'value' => 'MS/MT'],
        ], $mail->contextRows());
    }

    public function test_context_renders_as_a_table(): void
    {
        $html = (new CrlvImportFailureMail('Estrutura do CRLV-e não reconhecida.', [
            'extensao' => 'pdf',
            'usuario_id' => 7,
        ]))->render();

        $this->assertMatchesRegularExpression('/<th[^>]*>Campo<\/th>\s*<th[^>]*>Valor<\/th>/', $html);
        $this->assertMatchesRegularExpression('/<td[^>]*>Extensão<\/td>\s*<td[^>]*>pdf<\/td>/u', $html);
        $this->assertMatchesRegularExpression('/<td[^>]*>ID do usuário<\/td>\s*<td[^>]*>7<\/td>/u', $html);
        $this->assertStringNotContainsString('Usuario id', $html);
        $this->assertStringNotContainsString('Tamanho (KB)', $html);
    }

    /**
     * Nome do arquivo e e-mail da conta nunca viram linha da tabela, mesmo que cheguem no contexto:
     * o nome escolhido por quem envia pode trazer nome ou CPF da pessoa (LGPD).
     */
    public function test_blocked_keys_never_reach_the_table(): void
    {
        $mail = new CrlvImportFailureMail('Estrutura do CRLV-e não reconhecida.', [
            'arquivo' => 'CRLV-e Joao da Silva 000.000.000-00.pdf',
            'nome_arquivo' => 'CRLV-e Joao da Silva.pdf',
            'usuario_email' => 'joao@example.com',
            'usuario_id' => 7,
        ]);

        $this->assertSame([
            ['label' => 'ID do usuário', 'value' => '7'],
        ], $mail->contextRows());

        $html = $mail->render();

        $this->assertStringNotContainsString('Joao da Silva', $html);
        $this->assertStringNotContainsString('000.000.000-00', $html);
        $this->assertStringNotContainsString('joao@example.com', $html);
    }

    public function test_mail_without_context_has_no_table(): void
    {
        $html = (new CrlvImportFailureMail('Arquivo corrompido.'))->render();

        $this->assertStringContainsString('Arquivo corrompido.', $html);
        $this->assertStringNotContainsString('<table class="table"', $html);
        $this->assertStringNotContainsString('<th', $html);
    }
}
