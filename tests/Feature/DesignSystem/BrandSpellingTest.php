<?php

namespace Tests\Feature\DesignSystem;

use App\Support\DocumentTitle;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A marca se escreve "RevisaLog" em todo texto que a pessoa vê: <title>, telas, alt, e-mails
 * (cabeçalho, rodapé, remetente e saudação), PDF, push e textos legais. Domínio e endereços
 * continuam em minúsculas (revisalog.com.br), assim como identificadores de código.
 */
class BrandSpellingTest extends TestCase
{
    public function test_visible_text_uses_the_official_spelling(): void
    {
        $offenders = [];

        foreach ($this->visibleTextSources() as $path) {
            foreach (explode("\n", File::get($path)) as $index => $line) {
                if (preg_match('/\bRevisalog\b|\bREVISALOG\b|\bRevisa Log\b/', $line)) {
                    $offenders[] = str_replace(base_path().'/', '', $path).':'.($index + 1);
                }
            }
        }

        $this->assertSame([], $offenders, 'Escreva RevisaLog (L maiúsculo) no texto visível.');
    }

    public function test_title_mail_sender_and_env_example_use_revisalog(): void
    {
        $this->assertSame('RevisaLog', DocumentTitle::BRAND);
        $this->assertSame('RevisaLog', config('mail.from.name'));
        $this->assertMatchesRegularExpression('/^APP_NAME=RevisaLog$/m', File::get(base_path('.env.example')));
        $this->assertStringContainsString('alt="RevisaLog"', File::get(resource_path('views/vendor/mail/html/header.blade.php')));
    }

    /**
     * @return list<string>
     */
    private function visibleTextSources(): array
    {
        $paths = [];

        foreach ([resource_path('views'), resource_path('js'), lang_path(), app_path('Notifications'), app_path('Mail')] as $directory) {
            foreach (File::allFiles($directory) as $file) {
                $paths[] = $file->getPathname();
            }
        }

        return array_merge($paths, [
            app_path('Support/DocumentTitle.php'),
            app_path('Services/WelcomePushNotifier.php'),
            config_path('mail.php'),
            config_path('legal.php'),
        ]);
    }
}
